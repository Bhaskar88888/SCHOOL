<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['POST', 'PUT'], true)) {
    json_response(['error' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/../../includes/csrf.php';
CSRFProtection::verifyToken();

$data = get_post_json();
$currentPassword = (string) ($data['current_password'] ?? ($data['old_password'] ?? ''));
$newPassword = (string) ($data['new_password'] ?? '');
$confirmPassword = (string) ($data['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    json_response(['error' => 'Current password, new password, and confirmation are required'], 400);
}

if ($newPassword !== $confirmPassword) {
    json_response(['error' => 'Passwords do not match'], 400);
}

Validator::reset();
Validator::password($newPassword);
if (Validator::hasErrors()) {
    json_response(['error' => Validator::errors()['password'] ?? 'Invalid password'], 400);
}

$userId = get_current_user_id();
$user = db_fetch("SELECT password FROM users WHERE id = ?", [$userId]);
if (!$user || !password_verify($currentPassword, $user['password'])) {
    json_response(['error' => 'Current password is incorrect'], 400);
}

$updates = ['password = ?'];
$params = [password_hash($newPassword, PASSWORD_BCRYPT)];

if (db_column_exists('users', 'password_change_required')) {
    $updates[] = 'password_change_required = 0';
}
if (db_column_exists('users', 'portal_generated')) {
    $updates[] = 'portal_generated = 0';
}

$params[] = $userId;
db_query("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", $params);

audit_log('PASSWORD_CHANGE', 'profile', $userId);

json_response([
    'success' => true,
    'message' => 'Password changed successfully',
]);
