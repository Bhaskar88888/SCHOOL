<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Single class
    if (isset($_GET['id'])) {
        $class = db_fetch("SELECT c.*, u.name as teacher_name FROM classes c LEFT JOIN users u ON c.teacher_id = u.id WHERE c.id = ?", [(int) $_GET['id']]);
        if (!$class)
            json_response(['error' => 'Class not found'], 404);
        $students = db_fetchAll("SELECT id, name, admission_no, roll_number FROM students WHERE class_id = ? AND is_active=1 ORDER BY name", [$class['id']]);
        $subjects = db_fetchAll("SELECT cs.*, u.name as teacher_name FROM class_subjects cs LEFT JOIN users u ON cs.teacher_id = u.id WHERE cs.class_id = ?", [$class['id']]);
        $class['students'] = $students;
        $class['subjects'] = $subjects;
        json_response($class);
    }

    $classes = db_fetchAll("SELECT c.*, u.name as teacher_name, COUNT(s.id) as student_count FROM classes c LEFT JOIN users u ON c.teacher_id = u.id LEFT JOIN students s ON s.class_id = c.id AND s.is_active=1 GROUP BY c.id ORDER BY c.name");
    json_response($classes);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    if (empty($data['name']))
        json_response(['error' => 'Class name required'], 400);
    $id = db_insert(
        "INSERT INTO classes (name, section, teacher_id, capacity) VALUES (?,?,?,?)",
        [sanitize($data['name']), sanitize($data['section'] ?? ''), (int) ($data['teacher_id'] ?? 0), (int) ($data['capacity'] ?? 40)]
    );
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    if (empty($data['id']))
        json_response(['error' => 'Class ID required'], 400);
    $updates = [];
    $params = [];
    foreach (['name', 'section', 'teacher_id', 'capacity'] as $f) {
        if (isset($data[$f])) {
            $updates[] = "$f = ?";
            $params[] = is_string($data[$f]) ? sanitize($data[$f]) : $data[$f];
        }
    }
    if (empty($updates))
        json_response(['error' => 'No data to update'], 400);
    $params[] = (int) $data['id'];
    db_query("UPDATE classes SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    json_response(['success' => true]);
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("DELETE FROM classes WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
