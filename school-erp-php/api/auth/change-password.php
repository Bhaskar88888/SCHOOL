<?php
/**
 * Auth Change Password API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    json_response(['error' => 'Method not allowed'], 405);
}

$data = get_post_json();

Validator::required($data, ['old_password', 'new_password']);

if (Validator::hasErrors()) {
    json_response(['errors' => Validator::errors()], 422);
}

$userId = get_current_user_id();
$user = db_fetch("SELECT password FROM users WHERE id = ?", [$userId]);

if (!$user || !password_verify($data['old_password'], $user['password'])) {
    json_response(['error' => 'Current password is incorrect'], 400);
}

if (strlen($data['new_password']) < 8) {
    json_response(['error' => 'New password must be at least 8 characters'], 400);
}

$hashedPassword = password_hash($data['new_password'], PASSWORD_BCRYPT);

db_query("UPDATE users SET password = ?, password_change_required = 0 WHERE id = ?", [$hashedPassword, $userId]);
audit_log('PASSWORD_CHANGE', 'auth', $userId);

json_response(['message' => 'Password changed successfully']);
