<?php
/**
 * Health Check API
 * School ERP PHP v3.0
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/env.php';

// Database check
$dbStatus = 'disconnected';
$dbError = null;
try {
    require_once __DIR__ . '/../includes/db.php';
    db_query("SELECT 1");
    $dbStatus = 'connected';
} catch (Exception $e) {
    $dbStatus = 'error';
    $dbError = $e->getMessage();
}

// Check critical tables
$criticalTables = ['users', 'students', 'classes', 'fees', 'attendance', 'exams'];
$tableStatus = [];
foreach ($criticalTables as $table) {
    try {
        $exists = db_fetch("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [DB_NAME, $table]);
        $tableStatus[$table] = $exists ? 'OK' : 'MISSING';
    } catch (Exception $e) {
        $tableStatus[$table] = 'ERROR';
    }
}

// System info
$health = [
    'status' => $dbStatus === 'connected' ? 'healthy' : 'degraded',
    'app' => [
        'name' => APP_NAME,
        'version' => APP_VERSION,
        'environment' => APP_ENV ?? 'production',
        'uptime' => time() - (defined('PHP_START_TIME') ? PHP_START_TIME : time()),
    ],
    'database' => [
        'status' => $dbStatus,
        'host' => DB_HOST,
        'database' => DB_NAME,
        'error' => $dbError,
        'tables' => $tableStatus,
    ],
    'php' => [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
    ],
    'timestamp' => date('Y-m-d H:i:s'),
];

// Hide sensitive info from unauthenticated/non-admin users
session_start();
$role = $_SESSION['user_role'] ?? '';
if ($role !== 'superadmin' && $role !== 'admin') {
    unset($health['database']['host']);
    unset($health['database']['database']);
    unset($health['database']['tables']);
    unset($health['php']);
    unset($health['app']['environment']);
}

http_response_code($dbStatus === 'connected' ? 200 : 503);
echo json_encode($health);
