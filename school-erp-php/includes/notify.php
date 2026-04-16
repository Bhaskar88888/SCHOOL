<?php
/**
 * Notification dispatch helper.
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/auth.php';

if (!function_exists('ensure_notifications_enhanced_schema')) {
    function ensure_notifications_enhanced_schema(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        db_query(
            "CREATE TABLE IF NOT EXISTS notifications_enhanced (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_id INT NOT NULL,
                sender_id INT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) DEFAULT 'info',
                related_module VARCHAR(60) DEFAULT NULL,
                related_id INT DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                read_at DATETIME DEFAULT NULL,
                action_url VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT NOW(),
                FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $alterStatements = [
            "ALTER TABLE notifications_enhanced ADD COLUMN IF NOT EXISTS related_module VARCHAR(60) DEFAULT NULL AFTER type",
            "ALTER TABLE notifications_enhanced ADD COLUMN IF NOT EXISTS related_id INT DEFAULT NULL AFTER related_module",
            "ALTER TABLE notifications_enhanced ADD COLUMN IF NOT EXISTS action_url VARCHAR(255) DEFAULT NULL AFTER read_at",
            "ALTER TABLE notifications_enhanced ADD COLUMN IF NOT EXISTS read_at DATETIME DEFAULT NULL AFTER is_read",
            "ALTER TABLE notifications_enhanced ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT NOW() AFTER action_url",
        ];

        foreach ($alterStatements as $sql) {
            db_query($sql);
        }

        $ensured = true;
    }
}

if (!function_exists('notify_user')) {
    function notify_user(int $recipientId, string $type, string $title, string $message, ?int $senderId = null, string $relatedModule = '', int $relatedId = 0, string $actionUrl = ''): void
    {
        ensure_notifications_enhanced_schema();

        if (db_table_exists('notifications_enhanced')) {
            db_query(
                "INSERT INTO notifications_enhanced (type, title, message, sender_id, recipient_id, related_module, related_id, action_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$type, $title, $message, $senderId, $recipientId, $relatedModule ?: null, $relatedId ?: null, $actionUrl ?: null]
            );
            return;
        }

        db_query(
            "INSERT INTO notifications (title, message, target_user, is_read) VALUES (?, ?, ?, 0)",
            [$title, $message, $recipientId]
        );
    }
}

if (!function_exists('notify_role')) {
    function notify_role(string $role, string $type, string $title, string $message, ?int $senderId = null): void
    {
        $users = db_fetchAll(
            "SELECT id FROM users WHERE role = ? AND is_active = 1",
            [storage_role_name($role)]
        );

        foreach ($users as $user) {
            notify_user((int) $user['id'], $type, $title, $message, $senderId);
        }
    }
}

if (!function_exists('notify_parent_of_student')) {
    function notify_parent_of_student(int $studentId, string $type, string $title, string $message, ?int $senderId = null, string $relatedModule = '', int $relatedId = 0, string $actionUrl = ''): void
    {
        $student = db_fetch("SELECT parent_user_id FROM students WHERE id = ?", [$studentId]);
        if ($student && !empty($student['parent_user_id'])) {
            notify_user((int) $student['parent_user_id'], $type, $title, $message, $senderId, $relatedModule, $relatedId, $actionUrl);
        }
    }
}

if (!function_exists('get_unread_notification_count')) {
    function get_unread_notification_count(int $userId): int
    {
        ensure_notifications_enhanced_schema();

        if (db_table_exists('notifications_enhanced')) {
            return (int) db_count(
                "SELECT COUNT(*) FROM notifications_enhanced WHERE recipient_id = ? AND is_read = 0",
                [$userId]
            );
        }

        return (int) db_count(
            "SELECT COUNT(*) FROM notifications WHERE (target_user = ? OR target_user IS NULL) AND is_read = 0",
            [$userId]
        );
    }
}
