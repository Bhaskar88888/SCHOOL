<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = sanitize($_GET['month'] ?? date('F'));
    $year  = (int)($_GET['year'] ?? date('Y'));
    $rows  = db_fetchAll("SELECT p.*, u.name as staff_name, u.role FROM payroll p LEFT JOIN users u ON p.staff_id = u.id WHERE p.month = ? AND p.year = ? ORDER BY u.name", [$month, $year]);
    json_response($rows);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin','accountant']);
    $data = get_post_json();
    $staffId = (int)($data['staff_id'] ?? 0);
    if (!$staffId) json_response(['error' => 'Staff ID required'], 400);
    $month = sanitize($data['month'] ?? date('F'));
    $year  = (int)($data['year'] ?? date('Y'));
    // Upsert
    db_query("INSERT INTO payroll (staff_id, month, year, basic_salary, allowances, deductions, status, paid_date) VALUES (?,?,?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE basic_salary=VALUES(basic_salary), allowances=VALUES(allowances), deductions=VALUES(deductions), status=VALUES(status), paid_date=VALUES(paid_date)",
        [$staffId, $month, $year, (float)($data['basic_salary'] ?? 0), (float)($data['allowances'] ?? 0),
         (float)($data['deductions'] ?? 0), sanitize($data['status'] ?? 'pending'), $data['paid_date'] ?? null]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
