<?php
/**
 * Auth Create Staff API - Create staff with full profile
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_auth();
require_role(['admin', 'superadmin', 'hr']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$data = get_post_json();

Validator::required($data, ['name', 'email', 'password', 'role']);
Validator::email($data['email']);
Validator::password($data['password']);

if (Validator::hasErrors()) {
    json_response(['errors' => Validator::errors()], 422);
}

// Check if email exists
$existing = db_fetch("SELECT id FROM users WHERE email = ?", [$data['email']]);
if ($existing) {
    json_response(['error' => 'Email already exists'], 409);
}

$employeeId = generate_auto_id('employee', 'EMP');
$hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

$sql = "INSERT INTO users (employee_id, name, email, password, role, phone, department, designation, basic_salary, hra, da, date_of_birth, blood_group, highest_qualification, experience_years, joining_date, emergency_contact_name, emergency_contact_phone, staff_address, casual_leave_balance, earned_leave_balance, sick_leave_balance) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$params = [
    $employeeId,
    Validator::sanitize($data['name']),
    $data['email'],
    $hashedPassword,
    $data['role'],
    $data['phone'] ?? null,
    $data['department'] ?? null,
    $data['designation'] ?? null,
    $data['basic_salary'] ?? 0,
    $data['hra'] ?? 0,
    $data['da'] ?? 0,
    $data['date_of_birth'] ?? null,
    $data['blood_group'] ?? null,
    $data['highest_qualification'] ?? null,
    $data['experience_years'] ?? null,
    $data['joining_date'] ?? date('Y-m-d'),
    $data['emergency_contact_name'] ?? null,
    $data['emergency_contact_phone'] ?? null,
    isset($data['address']) ? json_encode($data['address']) : null,
    12, // casual leave
    15, // earned leave
    10, // sick leave
];

$staffId = db_insert($sql, $params);
audit_log('CREATE_STAFF', 'auth', $staffId, null, $data);

json_response(['message' => 'Staff created successfully', 'id' => $staffId, 'employee_id' => $employeeId], 201);
