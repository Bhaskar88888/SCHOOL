<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $role   = sanitize($_GET['role'] ?? '');
    $where  = "WHERE is_active = 1 AND role != 'student' AND (name LIKE ? OR email LIKE ?)";
    $params = [$search, $search];
    if ($role) { $where .= " AND role = ?"; $params[] = $role; }
    $staff = db_fetchAll("SELECT id, name, email, role, phone, created_at FROM users $where ORDER BY name", $params);
    json_response($staff);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin']);
    $data = get_post_json();
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        json_response(['error' => 'Name, email and password required'], 400);
    }
    if (db_fetch("SELECT id FROM users WHERE email = ?", [$data['email']])) {
        json_response(['error' => 'Email already exists'], 409);
    }
    $id = db_insert("INSERT INTO users (name, email, password, role, phone, is_active) VALUES (?,?,?,?,?,1)",
        [sanitize($data['name']), sanitize($data['email']), password_hash($data['password'], PASSWORD_BCRYPT),
         sanitize($data['role'] ?? 'teacher'), sanitize($data['phone'] ?? '')]);
    json_response(['success' => true, 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin','admin']);
    $data = get_post_json();
    $updates = [];$params = [];
    if (!empty($data['name']))  { $updates[] = 'name=?';  $params[] = sanitize($data['name']); }
    if (!empty($data['email'])) { $updates[] = 'email=?'; $params[] = sanitize($data['email']); }
    if (!empty($data['role']))  { $updates[] = 'role=?';  $params[] = sanitize($data['role']); }
    if (!empty($data['phone'])) { $updates[] = 'phone=?'; $params[] = sanitize($data['phone']); }
    if (!empty($data['password'])) { $updates[] = 'password=?'; $params[] = password_hash($data['password'], PASSWORD_BCRYPT); }
    if (!$updates) json_response(['error' => 'Nothing to update'], 400);
    $params[] = (int)$data['id'];
    db_query("UPDATE users SET " . implode(',', $updates) . " WHERE id=?", $params);
    json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin','admin']);
    db_query("UPDATE users SET is_active = 0 WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
