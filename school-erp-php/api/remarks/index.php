<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $type = sanitize($_GET['type'] ?? '');
    $whereOpts = [];
    $params = [];
    if ($studentId) {
        $whereOpts[] = "r.student_id = ?";
        $params[] = $studentId;
    }
    if ($type) {
        $whereOpts[] = "r.type = ?";
        $params[] = $type;
    }
    
    $role = normalize_role_name(get_current_role());
    $userId = get_current_user_id();
    if ($role === 'student' && db_column_exists('students', 'user_id')) {
        $whereOpts[] = "s.user_id = ?";
        $params[] = $userId;
    } elseif ($role === 'parent' && db_column_exists('students', 'parent_user_id')) {
        $whereOpts[] = "s.parent_user_id = ?";
        $params[] = $userId;
    }

    $where = count($whereOpts) > 0 ? "WHERE " . implode(' AND ', $whereOpts) : "";

    $remarks = db_fetchAll("SELECT r.*, s.name as student_name, u.name as teacher_name FROM remarks r LEFT JOIN students s ON r.student_id = s.id LEFT JOIN users u ON r.teacher_id = u.id $where ORDER BY r.created_at DESC", $params);
    json_response($remarks);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin', 'teacher']);
    $data = get_post_json();
    $id = db_insert(
        "INSERT INTO remarks (student_id, teacher_id, remark, type) VALUES (?,?,?,?)",
        [(int) $data['student_id'], get_current_user_id(), sanitize($data['remark']), sanitize($data['type'] ?? 'general')]
    );
    
    // Notify Parent
    require_once __DIR__ . '/../../includes/notify.php';
    $teacherName = htmlspecialchars(get_authenticated_user()['name']);
    notify_parent_of_student((int)$data['student_id'], 'remark', "New Teacher Remark", "$teacherName added a new remark regarding your child.", get_current_user_id(), 'remarks', $id, '/remarks.php');

    json_response(['success' => true, 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("DELETE FROM remarks WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
