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
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

Validator::reset();
$dataArray = is_array($data) ? $data : [];
Validator::required($dataArray, ['name', 'email', 'password', 'role']);
if (!empty($dataArray['email'])) {
    Validator::email((string) $dataArray['email']);
}
if (!empty($dataArray['password'])) {
    Validator::password((string) $dataArray['password']);
}

if (Validator::hasErrors()) {
    json_response(['errors' => Validator::errors()], 422);
}

$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$name = Validator::sanitize($data['name'] ?? '');
$role = storage_role_name(normalize_role_name($data['role'] ?? 'teacher'));

// Check if email exists
$existing = db_fetch("SELECT id FROM users WHERE email = ?", [$email]);
if ($existing) {
    json_response(['error' => 'Email already exists'], 409);
}

$employeeId = generate_auto_id('employee', 'EMP');
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$staffAddress = null;
if (isset($data['address'])) {
    $staffAddress = is_array($data['address']) ? json_encode($data['address']) : Validator::sanitize($data['address']);
} elseif (isset($data['staff_address'])) {
    $staffAddress = Validator::sanitize($data['staff_address']);
}

$payload = db_filter_data_for_table('users', [
    'employee_id' => $employeeId,
    'name' => $name,
    'email' => $email,
    'password' => $hashedPassword,
    'role' => $role,
    'phone' => $data['phone'] ?? null,
    'department' => $data['department'] ?? null,
    'designation' => $data['designation'] ?? null,
    'basic_salary' => (float) ($data['basic_salary'] ?? 0),
    'hra' => (float) ($data['hra'] ?? 0),
    'da' => (float) ($data['da'] ?? 0),
    'medical_allowance' => (float) ($data['medical_allowance'] ?? 0),
    'special_allowance' => (float) ($data['special_allowance'] ?? 0),
    'pf_deduction' => (float) ($data['pf_deduction'] ?? 0),
    'esi_deduction' => (float) ($data['esi_deduction'] ?? 0),
    'tax_deduction' => (float) ($data['tax_deduction'] ?? 0),
    'date_of_birth' => $data['date_of_birth'] ?? null,
    'blood_group' => $data['blood_group'] ?? null,
    'highest_qualification' => $data['highest_qualification'] ?? null,
    'experience_years' => ($data['experience_years'] ?? '') !== '' ? (int) $data['experience_years'] : 0,
    'joining_date' => $data['joining_date'] ?? date('Y-m-d'),
    'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
    'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
    'staff_address' => $staffAddress,
    'address_line1' => is_array($data['address'] ?? null) ? ($data['address']['line1'] ?? null) : null,
    'address_line2' => is_array($data['address'] ?? null) ? ($data['address']['line2'] ?? null) : null,
    'city' => is_array($data['address'] ?? null) ? ($data['address']['city'] ?? null) : null,
    'state' => is_array($data['address'] ?? null) ? ($data['address']['state'] ?? null) : null,
    'pincode' => is_array($data['address'] ?? null) ? ($data['address']['pincode'] ?? null) : null,
    'casual_leave_balance' => 12,
    'earned_leave_balance' => 15,
    'sick_leave_balance' => 10,
    'is_active' => 1,
]);

$columns = array_keys($payload);
$placeholders = array_fill(0, count($columns), '?');
$staffId = db_insert(
    'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
    array_values($payload)
);
audit_log('CREATE_STAFF', 'auth', $staffId, null, $data);

json_response(['message' => 'Staff created successfully', 'id' => $staffId, 'employee_id' => $employeeId], 201);
