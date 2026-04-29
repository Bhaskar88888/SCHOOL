<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

$userId = get_current_user_id();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $select = ['id', 'name', 'email', 'role'];
    foreach (['phone', 'created_at', 'avatar', 'portal_generated', 'password_change_required'] as $column) {
        if (db_column_exists('users', $column)) {
            $select[] = $column;
        }
    }

    $user = db_fetch(
        "SELECT " . implode(', ', $select) . " FROM users WHERE id = ?",
        [$userId]
    );

    if (!$user) {
        json_response(['error' => 'User not found'], 404);
    }

    $user['role'] = normalize_role_name($user['role'] ?? '');
    $user['portal_generated'] = !empty($user['portal_generated']) ? 1 : 0;
    $user['password_change_required'] = !empty($user['password_change_required']) ? 1 : 0;
    $user['is_default_password'] = $user['portal_generated'] || $user['password_change_required'];

    json_response($user);
}

if ($method === 'POST') {
    $data = get_post_json();
    $name = normalize_text_input($data['name'] ?? '');
    $email = trim((string) ($data['email'] ?? ''));
    $phone = normalize_text_input($data['phone'] ?? '');
    $wantsPasswordChange = !empty($data['new_password']);

    if ($name === '' || $email === '') {
        json_response(['error' => 'Name and email are required'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['error' => 'Please enter a valid email address'], 400);
    }

    $existing = db_fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($existing) {
        json_response(['error' => 'Email already in use by another account'], 400);
    }

    if ($wantsPasswordChange) {
        require_once __DIR__ . '/../../includes/validator.php';
        Validator::reset();
        Validator::password($data['new_password']);
        if (Validator::hasErrors()) {
            json_response(['error' => Validator::errors()['password'] ?? 'Invalid password'], 400);
        }

        if (isset($data['confirm_password']) && $data['new_password'] !== $data['confirm_password']) {
            json_response(['error' => 'Passwords do not match'], 400);
        }

        $currentPassword = (string) ($data['current_password'] ?? ($data['old_password'] ?? ''));
        if ($currentPassword === '') {
            json_response(['error' => 'Current password is required'], 400);
        }

        $user = db_fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            json_response(['error' => 'Current password incorrect'], 400);
        }
    }

    db_beginTransaction();

    try {
        $profileData = db_filter_data_for_table('users', [
            'name' => $name,
            'email' => $email,
            'phone' => $phone === '' ? null : $phone,
        ]);

        if (!empty($profileData)) {
            $setParts = [];
            $params = [];
            foreach ($profileData as $column => $value) {
                $setParts[] = "$column = ?";
                $params[] = $value;
            }
            $params[] = $userId;
            db_query("UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?", $params);

            if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $userId) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone === '' ? null : $phone;
            }
        }

        if ($wantsPasswordChange) {
            $updates = ['password = ?'];
            $params = [password_hash($data['new_password'], PASSWORD_DEFAULT)];

            if (db_column_exists('users', 'password_change_required')) {
                $updates[] = 'password_change_required = 0';
            }
            if (db_column_exists('users', 'portal_generated')) {
                $updates[] = 'portal_generated = 0';
            }

            $params[] = $userId;
            db_query("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            audit_log('PASSWORD_CHANGE', 'profile', $userId);
        }

        db_commit();
        json_response(['success' => true]);
    } catch (Throwable $e) {
        db_rollback();
        json_response(['error' => 'Failed to update profile'], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
