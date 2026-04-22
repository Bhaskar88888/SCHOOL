<?php
/**
 * SSO Redirect — WebView Single Sign-On Entry Point
 * Called by Android WebView:
 *   https://school.hostinger.com/sso-redirect.php?sso_token=xxxx&panel=parent_panel.php
 *
 * 1. Validates the one-time SSO token
 * 2. Creates a PHP session for the user
 * 3. Redirects to the requested panel (no login screen)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

$ssoToken = trim((string)($_GET['sso_token'] ?? ''));
$panel    = basename((string)($_GET['panel'] ?? 'dashboard.php'));  // basename for safety

if (empty($ssoToken)) {
    header('Location: ' . BASE_URL . '/index.php?error=sso_missing');
    exit;
}

if (!db_table_exists('webview_sso_tokens')) {
    header('Location: ' . BASE_URL . '/index.php?error=sso_unavailable');
    exit;
}

// Look up token — must be unused and not expired
$tokenRow = db_fetch(
    "SELECT * FROM webview_sso_tokens WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1",
    [$ssoToken]
);

if (!$tokenRow) {
    header('Location: ' . BASE_URL . '/index.php?error=sso_invalid');
    exit;
}

// Mark token as used (single-use)
db_query("UPDATE webview_sso_tokens SET used = 1 WHERE token = ?", [$ssoToken]);

// Load user
$user = db_fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$tokenRow['user_id']]);
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php?error=sso_user');
    exit;
}

// Create session — exactly the same as normal login
login_user_enhanced($user);

// Update last_login
if (db_column_exists('users', 'last_login_at')) {
    db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
}

// Redirect to dashboard
header('Location: ' . BASE_URL . '/dashboard.php');
exit;
