<?php
/**
 * Chatbot Analytics API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
require_role(['admin', 'superadmin']);

$period = $_GET['period'] ?? '30'; // days

// Total conversations
$total = db_count("SELECT COUNT(*) FROM chatbot_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$period]);

// By intent
$intents = db_fetchAll(
    "SELECT intent, COUNT(*) as count FROM chatbot_logs 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
     GROUP BY intent ORDER BY count DESC LIMIT 10",
    [$period]
);

// By language
$languages = db_fetchAll(
    "SELECT language, COUNT(*) as count FROM chatbot_logs 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
     GROUP BY language",
    [$period]
);

// By role
$roles = db_fetchAll(
    "SELECT user_role, COUNT(*) as count FROM chatbot_logs 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
     GROUP BY user_role ORDER BY count DESC",
    [$period]
);

// Average response time
$avgTime = db_fetch(
    "SELECT AVG(response_time) as avg_time FROM chatbot_logs 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
    [$period]
)['avg_time'] ?? 0;

// Daily usage
$daily = db_fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count FROM chatbot_logs 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
     GROUP BY DATE(created_at) ORDER BY date",
    [$period]
);

// Top messages
$topMessages = db_fetchAll(
    "SELECT message, COUNT(*) as count FROM chatbot_logs 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
     GROUP BY message ORDER BY count DESC LIMIT 10",
    [$period]
);

json_response([
    'period' => $period,
    'total' => (int)$total,
    'avgResponseTime' => round($avgTime, 2),
    'intents' => $intents,
    'languages' => $languages,
    'roles' => $roles,
    'dailyUsage' => $daily,
    'topMessages' => $topMessages,
]);
