<?php
/**
 * Salary Setup API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
require_role(['admin', 'superadmin', 'accounts', 'hr']);
$method = $_SERVER['REQUEST_METHOD'];

// GET - List salary structures
if ($method === 'GET') {
    $staffId = $_GET['staff_id'] ?? null;
    
    if ($staffId) {
        $structures = db_fetchAll(
            "SELECT ss.*, u.name as staff_name, u.employee_id 
             FROM salary_structures ss 
             LEFT JOIN users u ON ss.staff_id = u.id 
             WHERE ss.staff_id = ? 
             ORDER BY ss.effective_from DESC",
            [$staffId]
        );
        json_response(['structures' => $structures]);
    }
    
    $sql = "SELECT ss.*, u.name as staff_name, u.employee_id, u.department, u.designation 
            FROM salary_structures ss 
            LEFT JOIN users u ON ss.staff_id = u.id 
            WHERE u.is_active = 1 
            ORDER BY u.name, ss.effective_from DESC";
    
    $structures = db_fetchAll($sql);
    json_response(['structures' => $structures]);
}

// POST - Create salary structure
if ($method === 'POST') {
    require_role(['admin', 'superadmin', 'accounts']);
    
    $data = get_post_json();
    Validator::required($data, ['staff_id', 'basic_salary', 'effective_from']);
    
    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }
    
    $sql = "INSERT INTO salary_structures (staff_id, basic_salary, hra, da, conveyance, medical_allowance, special_allowance, pf_deduction, tax_deduction, effective_from) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $data['staff_id'],
        $data['basic_salary'],
        $data['hra'] ?? 0,
        $data['da'] ?? 0,
        $data['conveyance'] ?? 0,
        $data['medical_allowance'] ?? 0,
        $data['special_allowance'] ?? 0,
        $data['pf_deduction'] ?? 0,
        $data['tax_deduction'] ?? 0,
        $data['effective_from'],
    ];
    
    $id = db_insert($sql, $params);
    
    // Also update user's salary fields
    db_query("UPDATE users SET basic_salary = ?, hra = ?, da = ?, conveyance = ?, medical_allowance = ?, special_allowance = ?, pf_deduction = ?, tax_deduction = ? WHERE id = ?",
        [$data['basic_salary'], $data['hra'] ?? 0, $data['da'] ?? 0, $data['conveyance'] ?? 0, $data['medical_allowance'] ?? 0, $data['special_allowance'] ?? 0, $data['pf_deduction'] ?? 0, $data['tax_deduction'] ?? 0, $data['staff_id']]);
    
    audit_log('CREATE', 'salary_structure', $id, null, $data);
    json_response(['message' => 'Salary structure created', 'id' => $id], 201);
}

// PUT - Update salary structure
if ($method === 'PUT') {
    require_role(['admin', 'superadmin', 'accounts']);
    
    $data = get_post_json();
    if (empty($data['id'])) {
        json_response(['error' => 'Structure ID required'], 400);
    }
    
    $updates = [];
    $params = [];
    
    foreach (['basic_salary', 'hra', 'da', 'conveyance', 'medical_allowance', 'special_allowance', 'pf_deduction', 'tax_deduction', 'effective_from'] as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        json_response(['error' => 'No data to update'], 400);
    }
    
    $params[] = $data['id'];
    db_query("UPDATE salary_structures SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    
    audit_log('UPDATE', 'salary_structure', $data['id'], null, $data);
    json_response(['message' => 'Salary structure updated']);
}
