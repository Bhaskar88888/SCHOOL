<?php
/**
 * Resend Staff Credentials
 * POST /api/users/resend-credentials.php
 *
 * Body (JSON): { "user_id": 17 }
 *
 * Roles: superadmin, admin
 * Generates new password and sends via email.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mailer.php';

require_auth();
require_role(['superadmin', 'admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/../../includes/csrf.php';
CSRFProtection::verifyToken();

$data   = get_post_json();
$userId = (int)($data['user_id'] ?? 0);

if ($userId <= 0) {
    json_response(['error' => 'user_id required'], 400);
}

$user = db_fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$userId]);
if (!$user) {
    json_response(['error' => 'User not found'], 404);
}

// Don't allow resending to superadmin (security protection)
if (normalize_role_name($user['role']) === 'superadmin' && normalize_role_name(get_current_role()) !== 'superadmin') {
    json_response(['error' => 'Cannot modify superadmin credentials'], 403);
}

// Generate new password
$chars    = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
$password = '';
for ($i = 0; $i < 10; $i++) {
    $password .= $chars[random_int(0, strlen($chars) - 1)];
}
$hash = password_hash($password, PASSWORD_BCRYPT);

db_query(
    "UPDATE users SET password = ?, portal_generated = 1, portal_sent_at = NOW() WHERE id = ?",
    [$hash, $userId]
);

// Send email
$email    = $user['email'] ?? '';
$username = $user['username'] ?? $user['email'] ?? 'N/A';
$name     = $user['name']  ?? 'Staff';
$role     = role_label($user['role'] ?? 'staff');
$schoolName = defined('APP_NAME') ? APP_NAME : 'School ERP';
$loginUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/index.php';

$emailSent = false;
if (!empty($email)) {
    $subject = "[$schoolName] Your Portal Credentials";
    $content = <<<HTML
<p>Dear <strong>{$name}</strong>,</p>
<p>Your portal credentials for <strong>{$schoolName}</strong> have been updated by an administrator.</p>
<div class="cred-box">
  <div class="cred-row">
    <div class="cred-label">Role</div>
    <div class="cred-value">{$role}</div>
  </div>
  <div class="cred-row">
    <div class="cred-label">Username / Email</div>
    <div class="cred-value">{$username}</div>
  </div>
  <div class="cred-row">
    <div class="cred-label">New Password</div>
    <div class="cred-value">{$password}</div>
  </div>
</div>
<a href="{$loginUrl}" class="btn">Login to Portal →</a>
<div class="warning">⚠ Please change your password immediately after logging in.</div>
HTML;
    $htmlBody = Mailer::buildHtml("Your Portal Credentials", $content);
    $emailSent = Mailer::send($email, $subject, $htmlBody);
}

audit_log('RESEND_STAFF_CREDENTIALS', 'users', $userId, null, ['resent_by' => get_current_user_id()]);

json_response([
    'success'    => true,
    'email_sent' => $emailSent,
    'message'    => 'Credentials updated.' . ($emailSent ? ' Email sent.' : ' Email not configured — save password manually: ' . $password),
    // Return temp password only when email is NOT sent (admin needs to communicate manually)
    'temp_password' => $emailSent ? null : $password,
]);
