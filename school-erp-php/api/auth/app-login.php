<?php
/**
 * App Login - Mobile/Android JWT Authentication
 * POST /api/auth/app-login.php
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/jwt.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/app_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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
$login = trim((string) ($data['username'] ?? $data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$deviceToken = trim((string) ($data['device_token'] ?? ''));

if ($login === '' || $password === '') {
    http_response_code(400);
    die(json_encode(['error' => 'username and password are required']));
}

if (is_account_locked($login)) {
    http_response_code(429);
    die(json_encode(['error' => 'Account temporarily locked. Try again later.']));
}

$user = find_user_by_login_identifier($login, ['*'], true);

if (!$user || !password_verify($password, $user['password'])) {
    record_failed_login($login);
    http_response_code(401);
    die(json_encode(['error' => 'Invalid credentials']));
}

reset_login_attempts($login);

if (db_column_exists('users', 'last_login_at')) {
    db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
}

$role = normalize_role_name($user['role'] ?? '');
$accessTtl = JWT::ACCESS_TTL;
$jwtPayload = [
    'iss' => defined('APP_URL') ? APP_URL : 'school-erp',
    'user_id' => (int) $user['id'],
    'role' => $role,
    'name' => $user['name'],
    'email' => $user['email'],
    'iat' => time(),
    'exp' => time() + $accessTtl,
];
$accessToken = JWT::encode($jwtPayload);

$refreshToken = bin2hex(random_bytes(48));
$refreshExp = date('Y-m-d H:i:s', time() + JWT::REFRESH_TTL);

if (db_table_exists('user_tokens')) {
    $existingToken = null;

    if ($deviceToken !== '') {
        db_query("DELETE FROM user_tokens WHERE device_token = ? AND user_id != ?", [$deviceToken, $user['id']]);
        $existingToken = db_fetch(
            "SELECT id FROM user_tokens WHERE user_id = ? AND device_token = ? LIMIT 1",
            [$user['id'], $deviceToken]
        );
    }

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

echo json_encode([
    'token' => $accessToken,
    'refresh_token' => $refreshToken,
    'expires_in' => $accessTtl,
    'user' => build_mobile_user_payload($user),
    'portal_url' => build_mobile_portal_url($role),
    'sso_hint' => (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/api/auth/webview-sso.php',
]);
