<?php
/**
 * Chatbot History API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$userId = get_current_user_id();
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT id, message, language, intent, response, response_time, created_at 
        FROM chatbot_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT $limit OFFSET $offset";

$countSql = "SELECT COUNT(*) FROM chatbot_logs WHERE user_id = ?";

$history = db_fetchAll($sql, [$userId]);
$total = db_count($countSql, [$userId]);

json_response([
    'history' => $history,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
    ]
]);
