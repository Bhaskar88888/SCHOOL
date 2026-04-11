<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_auth();
header('Content-Type: application/json');

function hr_nullable_text($value, $default = null)
{
    if ($value === null) {
        return $default;
    }

    $value = trim((string) $value);
    return $value === '' ? $default : sanitize($value);
}

function hr_decimal($value)
{
    if ($value === null || $value === '') {
        return 0;
    }
    return round((float) $value, 2);
}

function hr_int($value, $default = 0)
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (int) $value;
}

function hr_email($value)
{
    return strtolower(trim((string) $value));
}

function hr_field_types()
{
    return [
        'name' => 'text',
        'email' => 'email',
        'phone' => 'text',
        'role' => 'text',
        'employee_id' => 'text',
        'employment_type' => 'text',
        'department' => 'text',
        'designation' => 'text',
        'joining_date' => 'date',
        'date_of_birth' => 'date',
        'gender' => 'text',
        'blood_group' => 'text',
        'highest_qualification' => 'text',
        'experience_years' => 'int',
        'aadhaar' => 'text',
        'pan' => 'text',
        'emergency_contact_name' => 'text',
        'emergency_contact_phone' => 'text',
        'address_line1' => 'text',
        'address_line2' => 'text',
        'city' => 'text',
        'state' => 'text',
        'pincode' => 'text',
        'basic_salary' => 'decimal',
        'hra' => 'decimal',
        'da' => 'decimal',
        'conveyance' => 'decimal',
        'medical_allowance' => 'decimal',
        'special_allowance' => 'decimal',
        'pf_deduction' => 'decimal',
        'esi_deduction' => 'decimal',
        'tax_deduction' => 'decimal',
        'bank_name' => 'text',
        'account_number' => 'text',
        'ifsc_code' => 'text',
        'casual_leave_balance' => 'int',
        'earned_leave_balance' => 'int',
        'sick_leave_balance' => 'int',
        'hr_notes' => 'text',
    ];
}

function build_staff_payload(array $data, $isCreate = false)
{
    $payload = [];
    foreach (hr_field_types() as $field => $type) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        $value = $data[$field];
        if ($type === 'int') {
            $payload[$field] = hr_int($value);
        } elseif ($type === 'decimal') {
            $payload[$field] = hr_decimal($value);
        } elseif ($type === 'email') {
            $payload[$field] = hr_email($value);
        } else {
            $payload[$field] = hr_nullable_text($value);
        }
    }

    if ($isCreate && empty($payload['employee_id']) && db_column_exists('users', 'employee_id')) {
        $payload['employee_id'] = generate_auto_id('employee', 'EMP');
    }

    if (!isset($payload['casual_leave_balance'])) {
        $payload['casual_leave_balance'] = 12;
    }
    if (!isset($payload['earned_leave_balance'])) {
        $payload['earned_leave_balance'] = 15;
    }
    if (!isset($payload['sick_leave_balance'])) {
        $payload['sick_leave_balance'] = 10;
    }

    if (db_column_exists('users', 'staff_address')) {
        $payload['staff_address'] = json_encode([
            'line1' => $payload['address_line1'] ?? '',
            'line2' => $payload['address_line2'] ?? '',
            'city' => $payload['city'] ?? '',
            'state' => $payload['state'] ?? '',
            'pincode' => $payload['pincode'] ?? '',
        ]);
    }

    return db_filter_data_for_table('users', $payload);
}

function upsert_salary_structure($staffId, array $data)
{
    if (!db_table_exists('salary_structures')) {
        return;
    }

    $payload = db_filter_data_for_table('salary_structures', [
        'staff_id' => $staffId,
        'basic_salary' => hr_decimal($data['basic_salary'] ?? 0),
        'hra' => hr_decimal($data['hra'] ?? 0),
        'da' => hr_decimal($data['da'] ?? 0),
        'conveyance' => hr_decimal($data['conveyance'] ?? 0),
        'medical_allowance' => hr_decimal($data['medical_allowance'] ?? 0),
        'special_allowance' => hr_decimal($data['special_allowance'] ?? 0),
        'pf_deduction' => hr_decimal($data['pf_deduction'] ?? 0),
        'esi_deduction' => hr_decimal($data['esi_deduction'] ?? 0),
        'tax_deduction' => hr_decimal($data['tax_deduction'] ?? 0),
        'effective_from' => hr_nullable_text($data['salary_effective_from'] ?? ($data['joining_date'] ?? date('Y-m-d'))),
    ]);

    if (!isset($payload['staff_id']) || !isset($payload['effective_from'])) {
        return;
    }

    $existing = db_fetch(
        "SELECT id FROM salary_structures WHERE staff_id = ? ORDER BY effective_from DESC, id DESC LIMIT 1",
        [$staffId]
    );

    if ($existing) {
        $set = [];
        $params = [];
        foreach ($payload as $column => $value) {
            if ($column === 'staff_id') {
                continue;
            }
            $set[] = "$column = ?";
            $params[] = $value;
        }
        if (!$set) {
            return;
        }
        if (db_column_exists('salary_structures', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }
        $params[] = $existing['id'];
        db_query("UPDATE salary_structures SET " . implode(', ', $set) . " WHERE id = ?", $params);
        return;
    }

    $columns = [];
    $placeholders = [];
    $params = [];
    foreach ($payload as $column => $value) {
        $columns[] = $column;
        $placeholders[] = '?';
        $params[] = $value;
    }
    if (db_column_exists('salary_structures', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if (db_column_exists('salary_structures', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    db_insert(
        "INSERT INTO salary_structures (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")",
        $params
    );
}

function insert_user(array $payload, $passwordHash)
{
    $columns = array_keys($payload);
    $placeholders = array_fill(0, count($columns), '?');
    $params = array_values($payload);

    $columns[] = 'password';
    $placeholders[] = '?';
    $params[] = $passwordHash;

    if (db_column_exists('users', 'is_active')) {
        $columns[] = 'is_active';
        $placeholders[] = '1';
    }
    if (db_column_exists('users', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if (db_column_exists('users', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    return db_insert(
        "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")",
        $params
    );
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    require_role(['superadmin', 'admin', 'hr']);
    $search = trim((string) ($_GET['search'] ?? ''));
    $role = trim((string) ($_GET['role'] ?? ''));
    $department = trim((string) ($_GET['department'] ?? ''));

    $where = ["u.is_active = 1", "u.role != 'student'"];
    $params = [];

    if ($search !== '') {
        $where[] = "(u.name LIKE ? OR u.email LIKE ? OR COALESCE(u.phone, '') LIKE ? OR COALESCE(u.employee_id, '') LIKE ?)";
        for ($i = 0; $i < 4; $i++) {
            $params[] = '%' . sanitize($search) . '%';
        }
    }

    if ($role !== '') {
        $where[] = 'u.role = ?';
        $params[] = sanitize($role);
    }

    if ($department !== '') {
        $where[] = 'u.department = ?';
        $params[] = sanitize($department);
    }

    $staff = db_fetchAll(
        "SELECT u.* FROM users u WHERE " . implode(' AND ', $where) . " ORDER BY u.name ASC",
        $params
    );
    json_response($staff);
}

if ($method === 'POST') {
    require_role(['superadmin', 'admin', 'hr']);
    $data = get_post_json();

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        json_response(['error' => 'Name, email, and password are required'], 400);
    }

    $email = hr_email($data['email']);
    if (db_fetch("SELECT id FROM users WHERE email = ?", [$email])) {
        json_response(['error' => 'Email already exists'], 409);
    }

    if (!empty($data['employee_id']) && db_fetch("SELECT id FROM users WHERE employee_id = ?", [sanitize($data['employee_id'])])) {
        json_response(['error' => 'Employee ID already exists'], 409);
    }

    $payload = build_staff_payload(array_merge($data, ['email' => $email]), true);
    $staffId = insert_user($payload, password_hash($data['password'], PASSWORD_BCRYPT));
    upsert_salary_structure($staffId, $data);

    audit_log('CREATE', 'users', $staffId, null, $payload);
    json_response(['success' => true, 'id' => $staffId, 'employee_id' => $payload['employee_id'] ?? null]);
}

if ($method === 'PUT') {
    require_role(['superadmin', 'admin', 'hr']);
    $data = get_post_json();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Staff ID required'], 400);
    }

    $existing = db_fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$existing) {
        json_response(['error' => 'Staff member not found'], 404);
    }

    $email = !empty($data['email']) ? hr_email($data['email']) : null;
    if ($email && db_fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id])) {
        json_response(['error' => 'Email already exists'], 409);
    }

    if (!empty($data['employee_id']) && db_fetch("SELECT id FROM users WHERE employee_id = ? AND id != ?", [sanitize($data['employee_id']), $id])) {
        json_response(['error' => 'Employee ID already exists'], 409);
    }

    $payload = build_staff_payload($email ? array_merge($data, ['email' => $email]) : $data);
    $set = [];
    $params = [];
    foreach ($payload as $column => $value) {
        $set[] = "$column = ?";
        $params[] = $value;
    }

    if (!empty($data['password'])) {
        $set[] = 'password = ?';
        $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
    }

    if (!$set) {
        json_response(['error' => 'Nothing to update'], 400);
    }

    if (db_column_exists('users', 'updated_at')) {
        $set[] = 'updated_at = NOW()';
    }

    $params[] = $id;
    db_query("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?", $params);
    upsert_salary_structure($id, array_merge($existing, $data));

    audit_log('UPDATE', 'users', $id, $existing, $payload);
    json_response(['success' => true]);
}

if ($method === 'DELETE') {
    require_role(['superadmin', 'admin', 'hr']);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Staff ID required'], 400);
    }

    db_query("UPDATE users SET is_active = 0" . (db_column_exists('users', 'updated_at') ? ', updated_at = NOW()' : '') . " WHERE id = ?", [$id]);
    audit_log('ARCHIVE', 'users', $id, null, ['is_active' => 0]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
