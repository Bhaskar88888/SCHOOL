<?php
/**
 * SSO Redirect - WebView Single Sign-On Entry Point
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

$allowedOrigin = defined('APP_MOBILE_ORIGIN') ? APP_MOBILE_ORIGIN : '*';
$deepLink = defined('APP_DEEPLINK_URL') ? APP_DEEPLINK_URL : 'schoolerp://dashboard';

header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-App-Client');
header('X-App-Deeplink: ' . $deepLink);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$ssoToken = trim((string) ($_GET['sso_token'] ?? $_GET['token'] ?? ''));

if ($ssoToken === '') {
    header('Location: ' . BASE_URL . '/index.php?error=sso_missing');
    exit;
}

if (!db_table_exists('webview_sso_tokens')) {
    header('Location: ' . BASE_URL . '/index.php?error=sso_unavailable');
    exit;
}

$tokenRow = db_fetch(
    "SELECT * FROM webview_sso_tokens WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1",
    [$ssoToken]
);

if (!$tokenRow) {
    header('Location: ' . BASE_URL . '/index.php?error=sso_invalid');
    exit;
}

db_query("UPDATE webview_sso_tokens SET used = 1 WHERE token = ?", [$ssoToken]);

$user = db_fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$tokenRow['user_id']]);
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php?error=sso_user');
    exit;
}

login_user($user);

if (db_column_exists('users', 'last_login_at')) {
    db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
}

header('Location: ' . BASE_URL . '/dashboard.php');
exit;
