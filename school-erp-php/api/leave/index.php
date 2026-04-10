<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = sanitize($_GET['status'] ?? '');
    $where  = $status ? "WHERE l.status = '$status'" : "";
    $leaves = db_fetchAll("SELECT l.*, u.name as applicant_name, a.name as approved_by_name FROM leave_applications l LEFT JOIN users u ON l.applicant_id = u.id LEFT JOIN users a ON l.approved_by = a.id $where ORDER BY l.created_at DESC");
    json_response($leaves);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    if (empty($data['from_date']) || empty($data['to_date'])) json_response(['error' => 'Dates required'], 400);
    $id = db_insert("INSERT INTO leave_applications (applicant_id, leave_type, from_date, to_date, reason) VALUES (?,?,?,?,?)",
        [get_current_user_id(), sanitize($data['leave_type'] ?? 'sick'), sanitize($data['from_date']), sanitize($data['to_date']), sanitize($data['reason'] ?? '')]);
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin','admin']);
    $data = get_post_json();
    db_query("UPDATE leave_applications SET status=?, approved_by=? WHERE id=?",
        [sanitize($data['status'] ?? 'pending'), get_current_user_id(), (int)$data['id']]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
