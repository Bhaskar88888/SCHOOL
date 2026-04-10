<?php
/**
 * Notifications API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// GET - List notifications
if ($method === 'GET') {
    $userId = get_current_user_id();
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    // Use enhanced table if exists
    if (db_table_exists('notifications_enhanced')) {
        $sql = "SELECT n.*, u.name as sender_name 
                FROM notifications_enhanced n 
                LEFT JOIN users u ON n.sender_id = u.id 
                WHERE n.recipient_id = ? 
                ORDER BY n.created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $countSql = "SELECT COUNT(*) FROM notifications_enhanced WHERE recipient_id = ?";
        $params = [$userId];
    } else {
        $sql = "SELECT * FROM notifications 
                WHERE target_user = ? OR target_user IS NULL
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $countSql = "SELECT COUNT(*) FROM notifications WHERE target_user = ? OR target_user IS NULL";
        $params = [$userId];
    }
    
    $notifications = db_fetchAll($sql, $params);
    $total = db_count($countSql, $params);
    $unreadCount = get_unread_notification_count($userId);
    
    json_response([
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ]
    ]);
}

// POST - Mark as read
if ($method === 'POST') {
    $data = get_post_json();
    $userId = get_current_user_id();
    
    if (isset($data['mark_all']) && $data['mark_all']) {
        // Mark all as read
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
        
        json_response(['message' => 'All notifications marked as read']);
    }
    
    if (empty($data['id'])) {
        json_response(['error' => 'Notification ID is required'], 400);
    }
    
    // Mark single as read
    if (db_table_exists('notifications_enhanced')) {
        db_query(
            "UPDATE notifications_enhanced SET is_read = 1, read_at = NOW() WHERE id = ? AND recipient_id = ?",
            [$data['id'], $userId]
        );
    } else {
        db_query(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND target_user = ?",
            [$data['id'], $userId]
        );
    }
    
    json_response(['message' => 'Notification marked as read']);
}

// DELETE - Delete notification
if ($method === 'DELETE') {
    $data = get_post_json();
    $userId = get_current_user_id();
    
    if (empty($data['id'])) {
        json_response(['error' => 'Notification ID is required'], 400);
    }
    
    if (db_table_exists('notifications_enhanced')) {
        db_query(
            "DELETE FROM notifications_enhanced WHERE id = ? AND recipient_id = ?",
            [$data['id'], $userId]
        );
    } else {
        db_query(
            "DELETE FROM notifications WHERE id = ? AND target_user = ?",
            [$data['id'], $userId]
        );
    }
    
    json_response(['message' => 'Notification deleted']);
}
