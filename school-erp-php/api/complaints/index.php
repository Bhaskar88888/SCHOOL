<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = sanitize($_GET['status'] ?? '');
    $where = $status ? "WHERE c.status = '$status'" : "";
    $complaints = db_fetchAll("SELECT c.*, u.name as submitted_by_name, a.name as assigned_to_name FROM complaints c LEFT JOIN users u ON c.submitted_by = u.id LEFT JOIN users a ON c.assigned_to = a.id $where ORDER BY c.created_at DESC");
    json_response($complaints);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    if (empty($data['title'])) json_response(['error' => 'Title required'], 400);
    $id = db_insert("INSERT INTO complaints (title, description, category, priority, submitted_by) VALUES (?,?,?,?,?)",
        [sanitize($data['title']), sanitize($data['description'] ?? ''), sanitize($data['category'] ?? 'general'), sanitize($data['priority'] ?? 'medium'), get_current_user_id()]);
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin','admin']);
    $data = get_post_json();
    $id   = (int)$data['id'];
    $status = sanitize($data['status'] ?? 'pending');
    $resolved = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
    db_query("UPDATE complaints SET status=?, assigned_to=?, resolved_at=? WHERE id=?",
        [$status, (int)($data['assigned_to'] ?? 0), $resolved, $id]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
