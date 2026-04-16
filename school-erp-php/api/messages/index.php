<?php
/**
 * api/messages/index.php — In-App Direct Messaging API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = get_current_user_id();
$role   = normalize_role_name(get_current_role());

// ─── Allowed recipients by role ──────────────────────────────────────────────
function get_allowed_recipients(int $userId, string $role): array
{
    switch ($role) {
        case 'superadmin':
        case 'admin':
            return db_fetchAll("SELECT id, name, role FROM users WHERE is_active=1 AND id != ? ORDER BY name", [$userId]);

        case 'teacher':
            // Parents of students + admins
            $parents = db_fetchAll(
                "SELECT DISTINCT u.id, u.name, u.role
                 FROM users u
                 JOIN students s ON s.parent_user_id = u.id
                 WHERE s.is_active = 1 AND u.is_active = 1
                 UNION
                 SELECT id, name, role FROM users WHERE role IN ('admin','superadmin') AND is_active=1
                 ORDER BY name"
            );
            return $parents;

        case 'parent':
            // Class teachers of my children + admins
            $classTeacherCol = db_column_exists('classes','teacher_id') ? 'teacher_id'
                : (db_column_exists('classes','class_teacher_id') ? 'class_teacher_id' : null);
            if (!$classTeacherCol) {
                return db_fetchAll("SELECT id, name, role FROM users WHERE role IN ('admin','superadmin') AND is_active=1 ORDER BY name");
            }
            return db_fetchAll(
                "SELECT DISTINCT u.id, u.name, u.role
                 FROM users u
                 JOIN classes c ON c.$classTeacherCol = u.id
                 JOIN students s ON s.class_id = c.id
                 WHERE s.parent_user_id = ? AND s.is_active = 1 AND u.is_active = 1
                 UNION
                 SELECT id, name, role FROM users WHERE role IN ('admin','superadmin') AND is_active=1
                 ORDER BY name",
                [$userId]
            );

        case 'student':
            return db_fetchAll("SELECT id, name, role FROM users WHERE role IN ('teacher','admin','superadmin') AND is_active=1 ORDER BY name");

        default:
            return db_fetchAll("SELECT id, name, role FROM users WHERE role IN ('admin','superadmin') AND is_active=1 ORDER BY name");
    }
}

// ─── GET handlers ─────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Allowed recipients list for compose modal
    if (isset($_GET['recipients'])) {
        json_response(get_allowed_recipients($userId, $role));
    }

    // Unread message count
    if (isset($_GET['unread_count'])) {
        if (!db_table_exists('thread_participants')) { json_response(['count' => 0]); }
        $count = db_count(
            "SELECT COUNT(DISTINCT tp.thread_id)
             FROM thread_participants tp
             JOIN messages m ON m.thread_id = tp.thread_id
             WHERE tp.user_id = ?
               AND m.sender_id != ?
               AND m.is_deleted = 0
               AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at)",
            [$userId, $userId]
        );
        json_response(['count' => (int)$count]);
    }

    // List threads
    if (isset($_GET['threads'])) {
        if (!db_table_exists('message_threads')) { json_response([]); }
        $threads = db_fetchAll(
            "SELECT mt.id, mt.subject, mt.type, mt.created_at,
                    (SELECT m2.body FROM messages m2 WHERE m2.thread_id=mt.id AND m2.is_deleted=0 ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
                    (SELECT m2.created_at FROM messages m2 WHERE m2.thread_id=mt.id AND m2.is_deleted=0 ORDER BY m2.created_at DESC LIMIT 1) AS last_message_at,
                    (SELECT u2.name FROM messages m2 JOIN users u2 ON m2.sender_id=u2.id WHERE m2.thread_id=mt.id AND m2.is_deleted=0 ORDER BY m2.created_at DESC LIMIT 1) AS last_sender,
                    (SELECT COUNT(*) FROM messages m3 WHERE m3.thread_id=mt.id AND m3.sender_id!=? AND m3.is_deleted=0 AND (tp.last_read_at IS NULL OR m3.created_at > tp.last_read_at)) AS unread_count
             FROM message_threads mt
             JOIN thread_participants tp ON tp.thread_id=mt.id AND tp.user_id=?
             ORDER BY COALESCE(last_message_at, mt.created_at) DESC",
            [$userId, $userId]
        );
        json_response($threads);
    }

    // Get messages in a thread
    if (isset($_GET['thread_id'])) {
        $threadId = (int)$_GET['thread_id'];
        // Verify participation
        if (!db_table_exists('thread_participants')) { json_response(['error' => 'Messaging not set up'], 503); }
        $part = db_fetch("SELECT * FROM thread_participants WHERE thread_id=? AND user_id=?", [$threadId, $userId]);
        if (!$part) { json_response(['error' => 'Access denied'], 403); }

        // Mark as read
        db_query("UPDATE thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?", [$threadId, $userId]);

        $thread = db_fetch("SELECT * FROM message_threads WHERE id=?", [$threadId]);
        $messages = db_fetchAll(
            "SELECT m.*, u.name AS sender_name, u.role AS sender_role
             FROM messages m JOIN users u ON m.sender_id=u.id
             WHERE m.thread_id=? AND m.is_deleted=0
             ORDER BY m.created_at ASC",
            [$threadId]
        );
        $participants = db_fetchAll(
            "SELECT u.id, u.name, u.role FROM thread_participants tp JOIN users u ON tp.user_id=u.id WHERE tp.thread_id=?",
            [$threadId]
        );

        json_response(['thread' => $thread, 'messages' => $messages, 'participants' => $participants]);
    }

    json_response(['error' => 'Unknown GET action'], 400);
}

// ─── POST: new thread or reply ────────────────────────────────────────────────
if ($method === 'POST') {
    $data = get_post_json();

    // Reply to existing thread
    if (!empty($data['thread_id'])) {
        $threadId = (int)$data['thread_id'];
        $part = db_fetch("SELECT * FROM thread_participants WHERE thread_id=? AND user_id=?", [$threadId, $userId]);
        if (!$part) { json_response(['error' => 'Thread not found or access denied'], 403); }

        $body = trim($data['body'] ?? '');
        if ($body === '') { json_response(['error' => 'Message body required'], 400); }

        $msgId = db_insert("INSERT INTO messages (thread_id, sender_id, body) VALUES (?,?,?)", [$threadId, $userId, $body]);

        // Notify all other participants
        $parts = db_fetchAll("SELECT user_id FROM thread_participants WHERE thread_id=? AND user_id!=?", [$threadId, $userId]);
        $thread = db_fetch("SELECT subject FROM message_threads WHERE id=?", [$threadId]);
        $senderName = get_authenticated_user()['name'] ?? 'Someone';
        foreach ($parts as $p) {
            notify_user(
                (int)$p['user_id'], 'message_reply', 'New Message from ' . $senderName,
                substr($body, 0, 120), $userId, 'messages', $threadId, '/messages.php?thread=' . $threadId
            );
        }

        json_response(['success' => true, 'message_id' => $msgId]);
    }

    // New thread
    $recipientId = (int)($data['recipient_id'] ?? 0);
    $subject     = trim($data['subject'] ?? 'No Subject');
    $body        = trim($data['body'] ?? '');

    if ($recipientId <= 0) { json_response(['error' => 'Recipient required'], 400); }
    if ($body === '')      { json_response(['error' => 'Message body required'], 400); }

    // Verify recipient is allowed
    $allowed = get_allowed_recipients($userId, $role);
    $allowedIds = array_column($allowed, 'id');
    if (!in_array((string)$recipientId, array_map('strval', $allowedIds))) {
        json_response(['error' => 'You are not allowed to message this user'], 403);
    }

    db_beginTransaction();
    try {
        $threadId = db_insert("INSERT INTO message_threads (subject, type, created_by) VALUES (?,?,?)", [$subject, 'direct', $userId]);
        db_query("INSERT INTO thread_participants (thread_id, user_id) VALUES (?,?)", [$threadId, $userId]);
        db_query("INSERT INTO thread_participants (thread_id, user_id) VALUES (?,?)", [$threadId, $recipientId]);
        $msgId = db_insert("INSERT INTO messages (thread_id, sender_id, body) VALUES (?,?,?)", [$threadId, $userId, $body]);
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        json_response(['error' => 'Failed to send message'], 500);
    }

    $senderName = get_authenticated_user()['name'] ?? 'Someone';
    notify_user($recipientId, 'message_new', 'New Message from ' . $senderName, substr($body, 0, 120), $userId, 'messages', $threadId, '/messages.php?thread=' . $threadId);

    json_response(['success' => true, 'thread_id' => $threadId, 'message_id' => $msgId]);
}

// ─── PUT: mark thread as read ─────────────────────────────────────────────────
if ($method === 'PUT') {
    $data = get_post_json();
    $threadId = (int)($data['thread_id'] ?? 0);
    if ($threadId > 0) {
        db_query("UPDATE thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?", [$threadId, $userId]);
        json_response(['success' => true]);
    }
    json_response(['error' => 'Thread ID required'], 400);
}

json_response(['error' => 'Method not allowed'], 405);
