<?php
/**
 * Chatbot History API — GET (paginated history) + DELETE (clear session)
 * School ERP PHP v3.0
 * Matches Node.js chatbotEngine.js: getHistory(), clearHistory(), exportConversation()
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_auth();
header('Content-Type: application/json');

$userId = get_current_user_id();
$role   = get_current_role() ?? 'guest';

// ─── DELETE: clear this user's session history ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $sessionId = session_id();
    if ($sessionId) {
        db_query(
            "DELETE FROM chatbot_logs WHERE user_id = ? AND session_id = ?",
            [$userId, $sessionId]
        );
    }
    json_response(['cleared' => true, 'message' => 'Conversation history cleared.']);
    exit;
}

// ─── GET: paginated history for this user ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
    exit;
}

$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$lang   = $_GET['lang'] ?? 'en';

// Export mode: return all history as flat message pairs for JS exportConversation()
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $rows = db_fetchAll(
        "SELECT message, response, language, intent, created_at
         FROM chatbot_logs
         WHERE user_id = ?
         ORDER BY created_at ASC
         LIMIT 200",
        [$userId]
    );
    // Build Node.js-compatible flat array
    $messages = [];
    foreach ($rows as $row) {
        $messages[] = ['role' => 'user',  'message' => $row['message'],  'timestamp' => $row['created_at']];
        $messages[] = ['role' => 'bot',   'message' => $row['response'], 'timestamp' => $row['created_at'], 'intent' => $row['intent']];
    }
    json_response([
        'language'  => $lang,
        'timestamp' => date('Y-m-d H:i:s'),
        'role'      => $role,
        'messages'  => $messages,
    ]);
    exit;
}

// Standard paginated history
$history = db_fetchAll(
    "SELECT id, message, language, intent, response, response_time, session_id, created_at
     FROM chatbot_logs
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT $limit OFFSET $offset",
    [$userId]
);

$total = db_count("SELECT COUNT(*) FROM chatbot_logs WHERE user_id = ?", [$userId]);

json_response([
    'history' => array_reverse($history ?? []),   // chronological order for UI
    'role'    => $role,
    'pagination' => [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => $total,
        'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
    ],
]);
