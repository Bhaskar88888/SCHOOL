<?php
/**
 * api/notifications/index.php — Unified Notification API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

$userId = get_current_user_id();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET handlers ──────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Combined unread count (bell + messages) — used by dashboard polling
    if (isset($_GET['count'])) {
        $notifCount = function_exists('get_unread_notification_count')
            ? get_unread_notification_count($userId)
            : 0;

        $msgCount = 0;
        if (db_table_exists('thread_participants') && db_table_exists('messages')) {
            $msgCount = (int)db_count(
                "SELECT COUNT(DISTINCT tp.thread_id)
                 FROM thread_participants tp
                 JOIN messages m ON m.thread_id = tp.thread_id
                 WHERE tp.user_id = ?
                   AND m.sender_id != ?
                   AND m.is_deleted = 0
                   AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at)",
                [$userId, $userId]
            );
        }

        json_response(['unread' => (int)$notifCount, 'unread_messages' => $msgCount]);
    }

    // List notifications
    if (isset($_GET['list'])) {
        $limit = min((int)($_GET['limit'] ?? 15), 50);

        if (db_table_exists('notifications_enhanced')) {
            $rows = db_fetchAll(
                "SELECT n.*, u.name AS sender_name
                 FROM notifications_enhanced n
                 LEFT JOIN users u ON n.sender_id = u.id
                 WHERE n.recipient_id = ?
                 ORDER BY n.created_at DESC
                 LIMIT ?",
                [$userId, $limit]
            );
            json_response($rows);
        }

        // Fallback legacy table
        if (db_table_exists('notifications')) {
            $rows = db_fetchAll(
                "SELECT * FROM notifications
                 WHERE target_user = ? OR target_user IS NULL
                 ORDER BY created_at DESC LIMIT ?",
                [$userId, $limit]
            );
            json_response($rows);
        }

        json_response([]);
    }

    json_response(['error' => 'Unknown action'], 400);
}

// ── PUT: mark read ────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();

    $data = get_post_json();

    // Mark all read
    if (!empty($data['all'])) {
        if (db_table_exists('notifications_enhanced')) {
            db_query(
                "UPDATE notifications_enhanced SET is_read=1, read_at=NOW() WHERE recipient_id=? AND is_read=0",
                [$userId]
            );
        } elseif (db_table_exists('notifications')) {
            db_query(
                "UPDATE notifications SET is_read=1 WHERE (target_user=? OR target_user IS NULL) AND is_read=0",
                [$userId]
            );
        }
        json_response(['success' => true]);
    }

    // Mark single notification read
    $id = (int)($data['id'] ?? 0);
    if ($id > 0) {
        if (db_table_exists('notifications_enhanced')) {
            db_query(
                "UPDATE notifications_enhanced SET is_read=1, read_at=NOW() WHERE id=? AND recipient_id=?",
                [$id, $userId]
            );
        } elseif (db_table_exists('notifications')) {
            db_query("UPDATE notifications SET is_read=1 WHERE id=? AND target_user=?", [$id, $userId]);
        }
        json_response(['success' => true]);
    }

    json_response(['error' => 'ID or `all` flag required'], 400);
}

json_response(['error' => 'Method not allowed'], 405);
