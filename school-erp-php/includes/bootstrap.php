<?php
/**
 * bootstrap.php — Schema guarantee layer
 * School ERP PHP v3.0
 *
 * Called once per request (via auth.php). Ensures all dynamic tables exist
 * using CREATE TABLE IF NOT EXISTS — idempotent and safe.
 */

// Counters table (auto-ID: ADM, EMP, RCP, PAY)
if (function_exists('ensure_counters_table')) {
    ensure_counters_table();
}

// Notifications enhanced table
if (function_exists('ensure_notifications_enhanced_schema')) {
    ensure_notifications_enhanced_schema();
}

// In-App Messaging tables (Phase 2)
if (!function_exists('ensure_messages_schema')) {
    function ensure_messages_schema(): void
    {
        static $ensured = false;
        if ($ensured) return;

        db_query("CREATE TABLE IF NOT EXISTS message_threads (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            subject     VARCHAR(255) NOT NULL DEFAULT 'No Subject',
            type        ENUM('direct','group','announcement') DEFAULT 'direct',
            created_by  INT NOT NULL,
            created_at  DATETIME DEFAULT NOW(),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        db_query("CREATE TABLE IF NOT EXISTS thread_participants (
            thread_id    INT NOT NULL,
            user_id      INT NOT NULL,
            last_read_at DATETIME DEFAULT NULL,
            PRIMARY KEY (thread_id, user_id),
            FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        db_query("CREATE TABLE IF NOT EXISTS messages (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            thread_id   INT NOT NULL,
            sender_id   INT NOT NULL,
            body        TEXT NOT NULL,
            is_deleted  TINYINT(1) DEFAULT 0,
            created_at  DATETIME DEFAULT NOW(),
            FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ensured = true;
    }
}
ensure_messages_schema();
