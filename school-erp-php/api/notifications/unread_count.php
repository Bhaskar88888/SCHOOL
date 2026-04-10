<?php
/**
 * Notification Unread Count API
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_auth();

$userId = get_current_user_id();
$count = get_unread_notification_count($userId);

json_response(['unreadCount' => $count]);
