<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

$userId = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = db_fetch("SELECT id, name, email, role, phone, created_at FROM users WHERE id = ?", [$userId]);
    json_response($user);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    $name = sanitize($data['name'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $phone = sanitize($data['phone'] ?? '');

    // Check if email already exists for another user
    $existing = db_fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($existing) {
        json_response(['error' => 'Email already in use by another account'], 400);
    }

    db_query("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?", [$name, $email, $phone, $userId]);

    // Handle password change if provided
    if (!empty($data['new_password'])) {
        require_once __DIR__ . '/../../includes/validator.php';
        Validator::reset();
        Validator::password($data['new_password']);
        if (Validator::hasErrors()) {
            json_response(['error' => Validator::errors()['password']], 400);
        }

        $oldPass = $data['old_password'] ?? '';
        $user = db_fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        if (!password_verify($oldPass, $user['password'])) {
            json_response(['error' => 'Current password incorrect'], 400);
        }
        $newPass = password_hash($data['new_password'], PASSWORD_DEFAULT);
        db_query("UPDATE users SET password = ? WHERE id = ?", [$newPass, $userId]);
    }

    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
