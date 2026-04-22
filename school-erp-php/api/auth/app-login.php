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
 *   "user"          : { id, name, role, email },
 *   "portal_url"    : "https://school.hostinger.com/panels/parent_panel.php"
 * }
 *
 * No session cookie is set — pure token auth for mobile apps.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/jwt.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');         // Allow app to call from any origin
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Client');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

// ── Rate limit check (reuse existing lockout mechanism) ───────────────
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

// Update last_login_at
if (db_column_exists('users', 'last_login_at')) {
    db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
}

// ── Generate JWT access token ─────────────────────────────────────────
$accessTtl = JWT::ACCESS_TTL;
$payload   = [
    'iss'     => defined('APP_URL') ? APP_URL : 'school-erp',
    'user_id' => (int)$user['id'],
    'role'    => normalize_role_name($user['role']),
    'name'    => $user['name'],
    'email'   => $user['email'],
    'iat'     => time(),
    'exp'     => time() + $accessTtl,
];
$accessToken = JWT::encode($payload);

// ── Generate refresh token ────────────────────────────────────────────
$refreshToken = bin2hex(random_bytes(48));
$refreshExp   = date('Y-m-d H:i:s', time() + JWT::REFRESH_TTL);

// Upsert into user_tokens
if (db_table_exists('user_tokens')) {
    $existingToken = db_fetch(
        "SELECT id FROM user_tokens WHERE user_id = ? AND (device_token = ? OR device_token IS NULL) LIMIT 1",
        [$user['id'], $deviceToken ?: null]
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

// ── Portal URL — deep-link to user's panel ────────────────────────────
$panelMap = [
    'superadmin' => '/panels/superadmin_panel.php',
    'admin'      => '/panels/admin_panel.php',
    'teacher'    => '/panels/teacher_panel.php',
    'student'    => '/panels/student_panel.php',
    'parent'     => '/panels/parent_panel.php',
    'accounts'   => '/panels/accounts_panel.php',
    'librarian'  => '/panels/librarian_panel.php',
    'hr'         => '/panels/hr_panel.php',
    'canteen'    => '/panels/canteen_panel.php',
    'conductor'  => '/panels/conductor_panel.php',
    'driver'     => '/panels/driver_panel.php',
];
$role      = normalize_role_name($user['role']);
$appUrl    = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
$portalUrl = $appUrl . ($panelMap[$role] ?? '/dashboard.php');

echo json_encode([
    'token'         => $accessToken,
    'refresh_token' => $refreshToken,
    'expires_in'    => $accessTtl,
    'user'          => [
        'id'     => (int)$user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'role'   => $role,
        'avatar' => $user['avatar'] ?? null,
    ],
    'portal_url'    => $portalUrl,
]);
