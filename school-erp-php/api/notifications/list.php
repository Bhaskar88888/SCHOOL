<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_current_user();
    $notifications = db_fetchAll("SELECT * FROM notifications WHERE target_user IS NULL OR target_user = ? ORDER BY created_at DESC LIMIT 20", [$user['id']]);
    json_response($notifications);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    if ($data['action'] ?? '' === 'mark_read') {
        db_query("UPDATE notifications SET is_read=1 WHERE id=?", [(int)$data['id']]);
        json_response(['success' => true]);
    }
}
json_response(['error' => 'Method not allowed'], 405);
