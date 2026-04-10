<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = '%' . sanitize($_GET['search'] ?? '') . '%';
$classId = (int)($_GET['class_id'] ?? 0);

$where = "WHERE s.is_active = 1 AND s.name LIKE ?";
$params = [$search];

if ($classId) {
    $where .= " AND s.class_id = ?";
    $params[] = $classId;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['id'])) {
    $total = db_count("SELECT COUNT(*) FROM students s $where", $params);
    $students = db_fetchAll("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id $where ORDER BY s.name ASC LIMIT $limit OFFSET $offset", $params);
    json_response(['data' => $students, 'total' => (int)$total, 'page' => $page, 'pages' => ceil($total / $limit)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $student = db_fetch("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [(int)$_GET['id']]);
    if (!$student) json_response(['error' => 'Student not found'], 404);
    json_response($student);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $required = ['name', 'class_id', 'roll_number', 'dob', 'gender', 'parent_name', 'phone'];
    foreach ($required as $field) {
        if (empty($data[$field])) json_response(['error' => "Field '$field' is required"], 400);
    }
    $id = db_insert("INSERT INTO students (name, class_id, roll_number, dob, gender, parent_name, phone, email, address, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW())",
        [sanitize($data['name']), (int)$data['class_id'], sanitize($data['roll_number']), sanitize($data['dob']), sanitize($data['gender']), sanitize($data['parent_name']), sanitize($data['phone']), sanitize($data['email'] ?? ''), sanitize($data['address'] ?? '')]);
    json_response(['success' => true, 'id' => $id, 'message' => 'Student added successfully']);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $id   = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => 'Student ID required'], 400);
    db_query("UPDATE students SET name=?, class_id=?, roll_number=?, dob=?, gender=?, parent_name=?, phone=?, email=?, address=? WHERE id=?",
        [sanitize($data['name']), (int)$data['class_id'], sanitize($data['roll_number']), sanitize($data['dob']), sanitize($data['gender']), sanitize($data['parent_name']), sanitize($data['phone']), sanitize($data['email'] ?? ''), sanitize($data['address'] ?? ''), $id]);
    json_response(['success' => true, 'message' => 'Student updated']);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if (!$id) json_response(['error' => 'Student ID required'], 400);
    db_query("UPDATE students SET is_active = 0 WHERE id = ?", [$id]);
    json_response(['success' => true, 'message' => 'Student removed']);
}

json_response(['error' => 'Method not allowed'], 405);
