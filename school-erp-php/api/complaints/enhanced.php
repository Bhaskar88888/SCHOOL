<?php
/**
 * Complaints Enhanced API - Staff Targets, My complaints
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$userId = get_current_user_id();
$role = get_current_role();

// GET - Staff targets (for parents)
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'staff-targets') {
    $staff = db_fetchAll("SELECT id, name, role, department, designation 
                          FROM users 
                          WHERE role IN ('teacher', 'admin', 'conductor', 'driver') AND is_active = 1 
                          ORDER BY name");
    json_response(['staff' => $staff]);
}

// GET - My complaints
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'my') {
    $complaints = db_fetchAll("SELECT c.*, u.name as target_name 
                               FROM complaints c 
                               LEFT JOIN users u ON c.target_user_id = u.id 
                               WHERE c.submitted_by = ? OR c.user_id = ?
                               ORDER BY c.created_at DESC", [$userId, $userId]);
    json_response(['complaints' => $complaints]);
}

// Include regular complaints API
require_once __DIR__ . '/index.php';
