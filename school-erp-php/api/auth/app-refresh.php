<?php
/**
 * App Token Refresh
 * POST /api/auth/app-refresh.php
 *
 * Body (JSON): { "refresh_token": "<token>" }
 *
 * Response: same as app-login.php (new access + refresh tokens)
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
    http_response_code(405); die(json_encode(['error' => 'Method not allowed']));
}

$data         = get_post_json();
$refreshToken = trim((string)($data['refresh_token'] ?? ''));

if (empty($refreshToken)) {
    http_response_code(400);
    die(json_encode(['error' => 'refresh_token required']));
}

if (!db_table_exists('user_tokens')) {
    http_response_code(500);
    die(json_encode(['error' => 'Token store not available. Run schema migration.']));
}

// Look up refresh token
$tokenRow = db_fetch(
    "SELECT * FROM user_tokens WHERE refresh_token = ? AND expires_at > NOW() LIMIT 1",
    [$refreshToken]
);

if (!$tokenRow) {
    http_response_code(401);
    die(json_encode(['error' => 'Invalid or expired refresh token']));
}

// Load user
$user = db_fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$tokenRow['user_id']]);
if (!$user) {
    http_response_code(401);
    die(json_encode(['error' => 'User not found or inactive']));
}

// Issue new tokens
$accessTtl   = JWT::ACCESS_TTL;
$payload     = [
    'iss'     => defined('APP_URL') ? APP_URL : 'school-erp',
    'user_id' => (int)$user['id'],
    'role'    => normalize_role_name($user['role']),
    'name'    => $user['name'],
    'email'   => $user['email'],
    'iat'     => time(),
    'exp'     => time() + $accessTtl,
];
$newAccess  = JWT::encode($payload);
$newRefresh = bin2hex(random_bytes(48));
$refreshExp = date('Y-m-d H:i:s', time() + JWT::REFRESH_TTL);

db_query(
    "UPDATE user_tokens SET refresh_token = ?, expires_at = ? WHERE id = ?",
    [$newRefresh, $refreshExp, $tokenRow['id']]
);

echo json_encode([
    'token'         => $newAccess,
    'refresh_token' => $newRefresh,
    'expires_in'    => $accessTtl,
    'user'          => [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => normalize_role_name($user['role']),
    ],
]);
