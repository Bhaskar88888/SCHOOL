<?php
/**
 * WebView SSO — Single Sign-On for Android WebView
 * POST /api/auth/webview-sso.php
 *
 * The Android app calls this with a valid JWT (Bearer) to get a
 * short-lived one-time SSO token. The app then loads the WebView at:
 *   https://school.hostinger.com/panels/parent_panel.php?sso_token=<token>
 *
 * The panel PHP detects the sso_token param, validates it, sets a
 * normal PHP session, and redirects to the panel (no login screen shown).
 *
 * Token lifetime: 5 minutes, single-use.
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

// Validate Bearer JWT
$jwtPayload = JWT::requireBearer();
$userId     = (int)($jwtPayload['user_id'] ?? 0);

if (!$userId) {
    http_response_code(401);
    die(json_encode(['error' => 'Invalid token payload']));
}

if (!db_table_exists('webview_sso_tokens')) {
    http_response_code(500);
    die(json_encode(['error' => 'SSO table not found. Run schema/parent_credentials.sql migration.']));
}

// Clean up expired tokens first
db_query("DELETE FROM webview_sso_tokens WHERE expires_at < NOW()");

// Generate new SSO token
$ssoToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + JWT::SSO_TTL);

db_query(
    "INSERT INTO webview_sso_tokens (token, user_id, expires_at) VALUES (?, ?, ?)",
    [$ssoToken, $userId, $expiresAt]
);

// Build WebView URL (to the user's panel)
$appUrl  = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
$webviewUrl = $appUrl . '/sso-redirect.php?sso_token=' . urlencode($ssoToken);

echo json_encode([
    'sso_token'   => $ssoToken,
    'webview_url' => $webviewUrl,
    'expires_in'  => JWT::SSO_TTL,
]);
