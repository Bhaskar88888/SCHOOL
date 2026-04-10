<?php
/**
 * Notifications Mark All Read API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$userId = get_current_user_id();

// PUT/POST - Mark all as read
if ($method === 'PUT' || $method === 'POST') {
    $data = get_post_json();
    
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

json_response(['error' => 'Method not allowed'], 405);
