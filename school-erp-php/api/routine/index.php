<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $classId = (int)($_GET['class_id'] ?? 0);
    $where = $classId ? "WHERE r.class_id = $classId" : "";
    $routine = db_fetchAll("SELECT r.*, c.name as class_name, u.name as teacher_name FROM routine r LEFT JOIN classes c ON r.class_id = c.id LEFT JOIN users u ON r.teacher_id = u.id $where ORDER BY FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.start_time");
    json_response($routine);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin']);
    $data = get_post_json();
    $id = db_insert("INSERT INTO routine (class_id, day, subject, teacher_id, start_time, end_time, room) VALUES (?,?,?,?,?,?,?)",
        [(int)$data['class_id'], sanitize($data['day']), sanitize($data['subject']), (int)($data['teacher_id']??0), $data['start_time']??null, $data['end_time']??null, sanitize($data['room']??'')]);
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin','admin']);
    db_query("DELETE FROM routine WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
