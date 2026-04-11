<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = sanitize($_GET['status'] ?? '');
    $category = sanitize($_GET['category'] ?? '');

    $whereClauses = [];
    $params = [];

    if ($status) {
        $whereClauses[] = "c.status = ?";
        $params[] = $status;
    }
    if ($category) {
        $whereClauses[] = "c.category = ?";
        $params[] = $category;
    }

    $whereStr = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

    $complaints = db_fetchAll("SELECT c.*, u.name as submitted_by_name, a.name as assigned_to_name FROM complaints c LEFT JOIN users u ON c.submitted_by = u.id LEFT JOIN users a ON c.assigned_to = a.id $whereStr ORDER BY c.created_at DESC", $params);
    json_response($complaints);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    if (empty($data['title']))
        json_response(['error' => 'Title required'], 400);

    $role = normalize_role_name(get_current_role());
    $type = 'general';
    $targetUserId = null;
    $assignedToRole = 'superadmin';

    // Role-based complaint routing
    if ($role === 'teacher' && !empty($data['student_id'])) {
        $student = db_fetch("SELECT parent_user_id FROM students WHERE id=?", [(int) $data['student_id']]);
        if ($student && $student['parent_user_id']) {
            $type = 'teacher_to_parent';
            $targetUserId = $student['parent_user_id'];
            $assignedToRole = 'parent';
        }
    } elseif ($role === 'parent' && !empty($data['class_id'])) {
        $class = db_fetch("SELECT class_teacher_id FROM classes WHERE id=?", [(int) $data['class_id']]);
        if ($class && $class['class_teacher_id']) {
            $type = 'parent_to_teacher';
            $targetUserId = $class['class_teacher_id'];
            $assignedToRole = 'teacher';
        }
    } elseif ($role === 'student') {
        $type = 'student_to_admin';
    }

    $id = db_insert(
        "INSERT INTO complaints (title, description, category, priority, submitted_by, type, target_user_id, assigned_to_role, raised_by_role) VALUES (?,?,?,?,?,?,?,?,?)",
        [sanitize($data['title']), sanitize($data['description'] ?? ''), sanitize($data['category'] ?? 'general'), sanitize($data['priority'] ?? 'medium'), get_current_user_id(), $type, $targetUserId, $assignedToRole, $role]
    );
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin', 'admin', 'hr']);
    $data = get_post_json();
    $id = (int) $data['id'];
    $status = sanitize($data['status'] ?? 'pending');
    $resolved = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
    db_query(
        "UPDATE complaints SET status=?, assigned_to=?, resolved_at=? WHERE id=?",
        [$status, (int) ($data['assigned_to'] ?? 0), $resolved, $id]
    );
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
