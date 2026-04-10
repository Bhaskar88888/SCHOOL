<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_role(['superadmin','admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    $email    = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $name     = trim($data['name'] ?? '');

    if (!$email || !$password || !$name) {
        json_response(['error' => 'Email, name and password required'], 400);
    }

    // Check duplicate
    $exists = db_fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($exists) {
        json_response(['error' => 'Email already registered'], 409);
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $role   = sanitize($data['role'] ?? 'teacher');

    $id = db_insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())",
        [$name, $email, $hashed, $role]);

    json_response(['success' => true, 'message' => 'User created', 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $users = db_fetchAll("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
    json_response($users);
}

json_response(['error' => 'Method not allowed'], 405);
