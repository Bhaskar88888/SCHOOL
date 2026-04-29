<?php
/**
 * App Login — Mobile/Android JWT Authentication
 * POST /api/auth/app-login.php
 *
 * Body (JSON): {
 *   "username"     : "PRN-4F8A2C-1042" OR email,
 *   "password"     : "Xk9pQwR2mN",
 *   "device_token" : "FCM_DEVICE_TOKEN"   (optional)
 * }
 *
 * Response: {
 *   "token"         : "<JWT>",
 *   "refresh_token" : "<refresh>",
 *   "expires_in"    : 2592000,
 *   "user"          : { id, name, role, email, student_uid, employee_id, children[] },
 *   "portal_url"    : "https://school.hostinger.com/sso-redirect.php?..."
 * }
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/jwt.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Client');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$data = get_post_json();

$login       = trim((string)($data['username'] ?? $data['email'] ?? ''));
$password    = (string)($data['password']     ?? '');
$deviceToken = trim((string)($data['device_token'] ?? ''));

if (empty($login) || empty($password)) {
    http_response_code(400);
    die(json_encode(['error' => 'username and password are required']));
}

// ── Rate limit check ──────────────────────────────────────────────────
if (is_account_locked($login)) {
    http_response_code(429);
    die(json_encode(['error' => 'Account temporarily locked. Try again later.']));
}

// ── Find user by email OR username ────────────────────────────────────
$user = db_fetch(
    "SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1",
    [$login, $login]
);

if (!$user || !password_verify($password, $user['password'])) {
    record_failed_login($login);
    http_response_code(401);
    die(json_encode(['error' => 'Invalid credentials']));
}

reset_login_attempts($login);

// Update last_login_at + clear portal_generated flag
$updates = [];
$uparams = [];
if (db_column_exists('users', 'last_login_at')) {
    $updates[] = 'last_login_at = NOW()';
}
if (db_column_exists('users', 'portal_generated') && !empty($user['portal_generated'])) {
    // Don't clear here — let them change password first; just mark first login
}
if ($updates) {
    db_query("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", array_merge($uparams, [$user['id']]));
}

$role = normalize_role_name($user['role']);

// ── Build role-specific extra data ────────────────────────────────────
$extraUserData = [];

if ($role === 'student') {
    $sRow = db_fetch(
        "SELECT s.student_uid, s.admission_no, s.class_id, c.name AS class_name, COALESCE(c.section,'') AS section
         FROM students s LEFT JOIN classes c ON s.class_id=c.id
         WHERE s.user_id=? AND s.is_active=1 LIMIT 1",
        [$user['id']]
    );
    if ($sRow) {
        $extraUserData['student_uid']  = $sRow['student_uid']  ?? null;
        $extraUserData['admission_no'] = $sRow['admission_no'] ?? null;
        $extraUserData['class_name']   = $sRow['class_name']   ?? null;
        $extraUserData['section']      = $sRow['section']      ?? null;
        $extraUserData['class_id']     = (int)($sRow['class_id'] ?? 0);
    }
}

if ($role === 'parent') {
    $children = db_fetchAll(
        "SELECT s.id, s.name, s.student_uid, s.admission_no, s.class_id,
                c.name AS class_name, COALESCE(c.section,'') AS section
         FROM students s LEFT JOIN classes c ON s.class_id=c.id
         WHERE s.parent_user_id=? AND s.is_active=1 ORDER BY s.name",
        [$user['id']]
    );
    // Fallback: match by phone
    if (empty($children) && db_column_exists('students','parent_phone') && !empty($user['phone'])) {
        $children = db_fetchAll(
            "SELECT s.id, s.name, s.student_uid, s.admission_no, s.class_id,
                    c.name AS class_name, COALESCE(c.section,'') AS section
             FROM students s LEFT JOIN classes c ON s.class_id=c.id
             WHERE s.parent_phone=? AND s.is_active=1 ORDER BY s.name",
            [$user['phone']]
        );
    }
    $extraUserData['children']      = $children ?: [];
    $extraUserData['children_count']= count($children);
    $extraUserData['is_default_password'] = !empty($user['portal_generated']) ? true : false;
}

if (in_array($role, ['teacher','staff','hr','accounts','librarian','canteen','conductor','driver'])) {
    $extraUserData['employee_id']   = $user['employee_id'] ?? null;
    $extraUserData['department_id'] = $user['department_id'] ?? null;
}

// ── Generate JWT access token ─────────────────────────────────────────
$accessTtl   = JWT::ACCESS_TTL;
$jwtPayload  = [
    'iss'     => defined('APP_URL') ? APP_URL : 'school-erp',
    'user_id' => (int)$user['id'],
    'role'    => $role,
    'name'    => $user['name'],
    'email'   => $user['email'],
    'iat'     => time(),
    'exp'     => time() + $accessTtl,
];
$accessToken = JWT::encode($jwtPayload);

// ── Generate refresh token ────────────────────────────────────────────
$refreshToken = bin2hex(random_bytes(48));
$refreshExp   = date('Y-m-d H:i:s', time() + JWT::REFRESH_TTL);

if (db_table_exists('user_tokens')) {
    $existingToken = db_fetch(
        "SELECT id FROM user_tokens WHERE user_id = ? LIMIT 1",
        [$user['id']]
    );
    if ($existingToken) {
        db_query(
            "UPDATE user_tokens SET refresh_token = ?, device_token = ?, expires_at = ? WHERE id = ?",
            [$refreshToken, $deviceToken ?: null, $refreshExp, $existingToken['id']]
        );
    } else {
        db_query(
            "INSERT INTO user_tokens (user_id, refresh_token, device_token, expires_at) VALUES (?, ?, ?, ?)",
            [$user['id'], $refreshToken, $deviceToken ?: null, $refreshExp]
        );
    }
}

// ── Portal URL (WebView SSO deep-link) ───────────────────────────────
$panelMap = [
    'superadmin' => '/dashboard.php',
    'admin'      => '/dashboard.php',
    'teacher'    => '/dashboard.php',
    'student'    => '/dashboard.php',
    'parent'     => '/dashboard.php',
    'staff'      => '/dashboard.php',
    'accounts'   => '/dashboard.php',
    'librarian'  => '/dashboard.php',
    'hr'         => '/dashboard.php',
    'canteen'    => '/dashboard.php',
    'conductor'  => '/dashboard.php',
    'driver'     => '/dashboard.php',
];
$appUrl    = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
$portalUrl = $appUrl . ($panelMap[$role] ?? '/dashboard.php');

echo json_encode([
    'token'         => $accessToken,
    'refresh_token' => $refreshToken,
    'expires_in'    => $accessTtl,
    'user'          => array_merge([
        'id'     => (int)$user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'phone'  => $user['phone'] ?? null,
        'role'   => $role,
        'avatar' => $user['avatar'] ?? null,
    ], $extraUserData),
    'portal_url'    => $portalUrl,
    'sso_hint'      => $appUrl . '/api/auth/webview-sso.php',
]);

