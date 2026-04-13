<?php
/**
 * Notification dispatch helper.
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Dispatch a notification to a specific user.
 */
function notify_user(int $recipientId, string $type, string $title, string $message, ?int $senderId = null, string $relatedModule = '', int $relatedId = 0, string $actionUrl = ''): void {
    if (db_table_exists('notifications_enhanced')) {
        db_query(
            "INSERT INTO notifications_enhanced (type, title, message, sender_id, recipient_id, related_module, related_id, action_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$type, $title, $message, $senderId, $recipientId, $relatedModule, $relatedId, $actionUrl]
        );
    } else {
        // Fallback for older systems
        db_query(
            "INSERT INTO notifications (title, message, target_user, is_read) VALUES (?, ?, ?, 0)",
            [$title, $message, $recipientId]
        );
    }
}

/**
 * Notify all users with a specific role.
 */
function notify_role(string $role, string $type, string $title, string $message, ?int $senderId = null): void {
    $users = db_fetchAll("SELECT id FROM users WHERE role = ? AND is_active = 1", [$role]);
    foreach ($users as $user) {
        notify_user((int)$user['id'], $type, $title, $message, $senderId);
    }
}

/**
 * Notify a student's parent(s).
 */
function notify_parent_of_student(int $studentId, string $type, string $title, string $message, ?int $senderId = null, string $relatedModule = '', int $relatedId = 0): void {
    $student = db_fetch("SELECT parent_user_id, name FROM students WHERE id = ?", [$studentId]);
    if ($student && !empty($student['parent_user_id'])) {
        notify_user((int)$student['parent_user_id'], $type, $title, $message, $senderId, $relatedModule, $relatedId);
    }
}

/**
 * Get unread count for user (used by topbar badge).
 */
function get_unread_notification_count(int $userId): int {
    if (db_table_exists('notifications_enhanced')) {
        return (int)db_count("SELECT COUNT(*) FROM notifications_enhanced WHERE recipient_id=? AND is_read=0", [$userId]);
    }
    return (int)db_count("SELECT COUNT(*) FROM notifications WHERE (target_user=? OR target_user IS NULL) AND is_read=0", [$userId]);
}
