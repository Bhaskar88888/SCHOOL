<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $classId = (int) ($_GET['class_id'] ?? 0);
    $where = $classId ? "WHERE h.class_id = $classId" : "";
    $hws = db_fetchAll("SELECT h.*, c.name as class_name, u.name as assigned_by_name FROM homework h LEFT JOIN classes c ON h.class_id = c.id LEFT JOIN users u ON h.assigned_by = u.id $where ORDER BY h.due_date, h.created_at DESC");
    json_response($hws);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin', 'teacher']);
    $data = get_post_json();
    $id = db_insert(
        "INSERT INTO homework (title, description, class_id, subject, due_date, assigned_by) VALUES (?,?,?,?,?,?)",
        [sanitize($data['title']), sanitize($data['description'] ?? ''), (int) $data['class_id'], sanitize($data['subject'] ?? ''), $data['due_date'] ?? null, get_current_user_id()]
    );
    
    // Notify students and parents of the class
    require_once __DIR__ . '/../../includes/notify.php';
    $teacherName = htmlspecialchars(get_authenticated_user()['name']);
    $students = db_fetchAll("SELECT id, user_id FROM students WHERE class_id = ? AND is_active = 1", [(int)$data['class_id']]);
    
    foreach ($students as $s) {
        // Notify Student
        if ($s['user_id']) {
            notify_user($s['user_id'], 'homework', "New Homework: " . sanitize($data['subject'] ?? 'General'), "$teacherName assigned new homework. Due: " . ($data['due_date'] ?? 'N/A'), get_current_user_id(), 'homework', $id, '/homework.php');
        }
        // Notify Parent
        notify_parent_of_student($s['id'], 'homework', "New Homework: " . sanitize($data['subject'] ?? 'General'), "$teacherName assigned new homework for your child. Due: " . ($data['due_date'] ?? 'N/A'), get_current_user_id(), 'homework', $id, '/homework.php');
    }

    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin', 'teacher']);
    db_query("DELETE FROM homework WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
