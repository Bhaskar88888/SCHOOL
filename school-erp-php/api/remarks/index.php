<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $studentId = (int)($_GET['student_id'] ?? 0);
    $where = $studentId ? "WHERE r.student_id = $studentId" : "";
    $remarks = db_fetchAll("SELECT r.*, s.name as student_name, u.name as teacher_name FROM remarks r LEFT JOIN students s ON r.student_id = s.id LEFT JOIN users u ON r. teacher_id = u.id $where ORDER BY r.created_at DESC");
    json_response($remarks);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin', 'teacher']);
    $data = get_post_json();
    $id = db_insert("INSERT INTO remarks (student_id, teacher_id, remark, type) VALUES (?,?,?,?)",
        [(int)$data['student_id'], get_current_user_id(), sanitize($data['remark']), sanitize($data['type'] ?? 'general')]);
    json_response(['success' => true, 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("DELETE FROM remarks WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
