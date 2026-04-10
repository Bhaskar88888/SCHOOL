<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $notices = db_fetchAll("SELECT n.*, u.name as created_by_name FROM notices n LEFT JOIN users u ON n.created_by = u.id WHERE n.is_active = 1 ORDER BY n.created_at DESC");
    json_response($notices);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin','teacher']);
    $data = get_post_json();
    if (empty($data['title']) || empty($data['content'])) json_response(['error' => 'Title and content required'], 400);
    $id = db_insert("INSERT INTO notices (title, content, target_roles, is_active, created_by) VALUES (?,?,?,1,?)",
        [sanitize($data['title']), $data['content'], sanitize($data['target_roles'] ?? 'all'), get_current_user_id()]);
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin','admin']);
    db_query("UPDATE notices SET is_active = 0 WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
