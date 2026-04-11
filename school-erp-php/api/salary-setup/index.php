<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
require_role(['admin', 'superadmin', 'accounts', 'hr']);
header('Content-Type: application/json');

function salary_decimal($value)
{
    if ($value === null || $value === '') {
        return 0;
    }
    return round((float) $value, 2);
}

function salary_payload(array $data)
{
    return db_filter_data_for_table('salary_structures', [
        'staff_id' => (int) ($data['staff_id'] ?? 0),
        'basic_salary' => salary_decimal($data['basic_salary'] ?? 0),
        'hra' => salary_decimal($data['hra'] ?? 0),
        'da' => salary_decimal($data['da'] ?? 0),
        'conveyance' => salary_decimal($data['conveyance'] ?? 0),
        'medical_allowance' => salary_decimal($data['medical_allowance'] ?? 0),
        'special_allowance' => salary_decimal($data['special_allowance'] ?? 0),
        'pf_deduction' => salary_decimal($data['pf_deduction'] ?? 0),
        'esi_deduction' => salary_decimal($data['esi_deduction'] ?? 0),
        'tax_deduction' => salary_decimal($data['tax_deduction'] ?? 0),
        'other_deductions' => salary_decimal($data['other_deductions'] ?? 0),
        'effective_from' => sanitize($data['effective_from'] ?? date('Y-m-d')),
    ]);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $staffId = (int) ($_GET['staff_id'] ?? 0);
    $params = [];
    $where = '';

    if ($staffId > 0) {
        $where = 'WHERE ss.staff_id = ?';
        $params[] = $staffId;
    }

    $structures = db_fetchAll(
        "SELECT ss.*, u.name AS staff_name, u.employee_id, u.department, u.designation
         FROM salary_structures ss
         LEFT JOIN users u ON ss.staff_id = u.id
         $where
         ORDER BY ss.effective_from DESC, ss.id DESC",
        $params
    );

    json_response(['structures' => $structures]);
}

if ($method === 'POST') {
    require_role(['admin', 'superadmin', 'accounts']);
    $data = get_post_json();

    if (empty($data['staff_id']) || !isset($data['basic_salary'])) {
        json_response(['error' => 'staff_id and basic_salary are required'], 400);
    }

    $payload = salary_payload($data);
    $columns = array_keys($payload);
    $placeholders = array_fill(0, count($columns), '?');
    $params = array_values($payload);

    if (db_column_exists('salary_structures', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if (db_column_exists('salary_structures', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $id = db_insert(
        "INSERT INTO salary_structures (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")",
        $params
    );

    $userUpdate = db_filter_data_for_table('users', [
        'basic_salary' => $payload['basic_salary'] ?? 0,
        'hra' => $payload['hra'] ?? 0,
        'da' => $payload['da'] ?? 0,
        'conveyance' => $payload['conveyance'] ?? 0,
        'medical_allowance' => $payload['medical_allowance'] ?? 0,
        'special_allowance' => $payload['special_allowance'] ?? 0,
        'pf_deduction' => $payload['pf_deduction'] ?? 0,
        'esi_deduction' => $payload['esi_deduction'] ?? 0,
        'tax_deduction' => $payload['tax_deduction'] ?? 0,
    ]);

    if ($userUpdate) {
        $set = [];
        $userParams = [];
        foreach ($userUpdate as $column => $value) {
            $set[] = "$column = ?";
            $userParams[] = $value;
        }
        $userParams[] = (int) $payload['staff_id'];
        db_query("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?", $userParams);
    }

    audit_log('CREATE', 'salary_structure', $id, null, $payload);
    json_response(['success' => true, 'id' => $id], 201);
}

if ($method === 'PUT') {
    require_role(['admin', 'superadmin', 'accounts']);
    $data = get_post_json();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Structure ID required'], 400);
    }

    $payload = salary_payload($data);
    unset($payload['staff_id']);

    if (!$payload) {
        json_response(['error' => 'No data to update'], 400);
    }

    $set = [];
    $params = [];
    foreach ($payload as $column => $value) {
        $set[] = "$column = ?";
        $params[] = $value;
    }
    if (db_column_exists('salary_structures', 'updated_at')) {
        $set[] = 'updated_at = NOW()';
    }
    $params[] = $id;
    db_query("UPDATE salary_structures SET " . implode(', ', $set) . " WHERE id = ?", $params);

    audit_log('UPDATE', 'salary_structure', $id, null, $payload);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
