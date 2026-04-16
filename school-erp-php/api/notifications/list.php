<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notify.php';

require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

ensure_notifications_enhanced_schema();

$userId = get_current_user_id();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (db_table_exists('notifications_enhanced')) {
        $notifications = db_fetchAll(
            "SELECT n.*, u.name AS sender_name
             FROM notifications_enhanced n
             LEFT JOIN users u ON n.sender_id = u.id
             WHERE n.recipient_id = ?
             ORDER BY n.created_at DESC
             LIMIT 20",
            [$userId]
        );
    } else {
        $notifications = db_fetchAll(
            "SELECT * FROM notifications
             WHERE target_user IS NULL OR target_user = ?
             ORDER BY created_at DESC
             LIMIT 20",
            [$userId]
        );
    }

    json_response($notifications);
}

if ($method === 'POST') {
    $data = get_post_json();

    if (($data['action'] ?? '') === 'mark_read' && !empty($data['id'])) {
        if (db_table_exists('notifications_enhanced')) {
            db_query(
                "UPDATE notifications_enhanced SET is_read = 1, read_at = NOW() WHERE id = ? AND recipient_id = ?",
                [(int) $data['id'], $userId]
            );
        } else {
            db_query(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND target_user = ?",
                [(int) $data['id'], $userId]
            );
        }

        json_response(['success' => true]);
    }

    if (($data['action'] ?? '') === 'mark_all_read') {
        if (db_table_exists('notifications_enhanced')) {
            db_query(
                "UPDATE notifications_enhanced SET is_read = 1, read_at = NOW() WHERE recipient_id = ? AND is_read = 0",
                [$userId]
            );
        } else {
            db_query(
                "UPDATE notifications SET is_read = 1 WHERE target_user = ? AND is_read = 0",
                [$userId]
            );
        }

        json_response(['success' => true]);
    }
}

json_response(['error' => 'Method not allowed'], 405);
