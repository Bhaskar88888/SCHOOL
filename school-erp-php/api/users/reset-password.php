<?php
/**
 * Admin Reset User Password API
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
require_role(['superadmin', 'admin', 'hr']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

CSRFProtection::verifyToken();

$data = get_post_json();
$userId = (int)($data['id'] ?? 0);
$newPassword = trim($data['password'] ?? '');

if ($userId <= 0 || empty($newPassword)) {
    json_response(['error' => 'User ID and new password are required'], 400);
}

if (strlen($newPassword) < 8) {
    json_response(['error' => 'Password must be at least 8 characters long'], 400);
}

$user = db_fetch("SELECT id, name, role FROM users WHERE id = ?", [$userId]);
if (!$user) {
    json_response(['error' => 'User not found'], 404);
}

// Don't let non-superadmins reset superadmin passwords
if ($user['role'] === 'superadmin' && get_current_role() !== 'superadmin') {
    json_response(['error' => 'Permission denied. Cannot reset superadmin password.'], 403);
}

$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
$updates = ['password = ?'];
if (db_column_exists('users', 'password_change_required')) {
    $updates[] = 'password_change_required = 1';
}
if (db_column_exists('users', 'portal_generated')) {
    $updates[] = 'portal_generated = 1';
}

db_query("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", [$hashedPassword, $userId]);
audit_log('PASSWORD_RESET', 'users', $userId, null, ['set_by' => get_current_user_id()]);

json_response(['success' => true, 'message' => 'Password reset successfully for ' . $user['name']]);
