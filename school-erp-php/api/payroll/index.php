<?php
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

function payroll_month_number($value)
{
    if ($value === null || $value === '') {
        return (int) date('n');
    }

    if (is_numeric($value)) {
        $month = (int) $value;
        return max(1, min(12, $month));
    }

    $map = [
        'january' => 1,
        'february' => 2,
        'march' => 3,
        'april' => 4,
        'may' => 5,
        'june' => 6,
        'july' => 7,
        'august' => 8,
        'september' => 9,
        'october' => 10,
        'november' => 11,
        'december' => 12,
    ];

    $normalized = strtolower(trim((string) $value));
    return $map[$normalized] ?? (int) date('n');
}

function payroll_month_label($monthNumber)
{
    static $months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
    ];

    return $months[(int) $monthNumber] ?? 'Unknown';
}

function payroll_decimal($value)
{
    if ($value === null || $value === '') {
        return 0;
    }
    return round((float) $value, 2);
}

function payroll_working_days($year, $monthNumber)
{
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNumber, $year);
    $workingDays = 0;
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $weekDay = (int) date('w', strtotime(sprintf('%04d-%02d-%02d', $year, $monthNumber, $day)));
        if ($weekDay !== 0) {
            $workingDays++;
        }
    }
    return max(1, $workingDays);
}

function payroll_payload(array $data)
{
    return db_filter_data_for_table('payroll', [
        'staff_id' => (int) ($data['staff_id'] ?? 0),
        'month' => payroll_month_number($data['month'] ?? date('n')),
        'year' => (int) ($data['year'] ?? date('Y')),
        'basic_salary' => payroll_decimal($data['basic_salary'] ?? 0),
        'allowances' => payroll_decimal($data['allowances'] ?? 0),
        'deductions' => payroll_decimal($data['deductions'] ?? 0),
        'status' => sanitize($data['status'] ?? 'pending'),
        'paid_date' => !empty($data['paid_date']) ? sanitize($data['paid_date']) : null,
    ]);
}

function payroll_upsert(array $payload)
{
    $columns = array_keys($payload);
    $placeholders = array_fill(0, count($columns), '?');
    $params = array_values($payload);

    if (db_column_exists('payroll', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if (db_column_exists('payroll', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $updates = [];
    foreach (array_keys($payload) as $column) {
        $updates[] = "$column = VALUES($column)";
    }
    if (db_column_exists('payroll', 'updated_at')) {
        $updates[] = 'updated_at = NOW()';
    }

    db_query(
        "INSERT INTO payroll (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")
         ON DUPLICATE KEY UPDATE " . implode(', ', $updates),
        $params
    );
}

function find_salary_structure($staffId, $effectiveDate)
{
    return db_fetch(
        "SELECT * FROM salary_structures WHERE staff_id = ? AND effective_from <= ? ORDER BY effective_from DESC, id DESC LIMIT 1",
        [$staffId, $effectiveDate]
    ) ?: db_fetch(
        "SELECT * FROM salary_structures WHERE staff_id = ? ORDER BY effective_from DESC, id DESC LIMIT 1",
        [$staffId]
    );
}

$method = $_SERVER['REQUEST_METHOD'];
$currentRole = normalize_role_name(get_current_role());
$currentUserId = get_current_user_id();
$canManagePayroll = role_matches($currentRole, ['superadmin', 'admin', 'accounts']);
$canViewAll = role_matches($currentRole, ['superadmin', 'admin', 'accounts', 'hr']);

if ($method === 'GET') {
    $monthNumber = payroll_month_number($_GET['month'] ?? date('n'));
    $year = (int) ($_GET['year'] ?? date('Y'));
    $myOnly = isset($_GET['my']) && $_GET['my'] == '1';
    $staffId = (int) ($_GET['staff_id'] ?? 0);

    $where = ['p.year = ?', 'p.month = ?'];
    $params = [$year, $monthNumber];

    if ($myOnly || !$canViewAll) {
        $where[] = 'p.staff_id = ?';
        $params[] = $currentUserId;
    } elseif ($staffId > 0) {
        $where[] = 'p.staff_id = ?';
        $params[] = $staffId;
    }

    $rows = db_fetchAll(
        "SELECT p.*, u.name AS staff_name, u.role, u.department, u.designation, u.employee_id
         FROM payroll p
         LEFT JOIN users u ON p.staff_id = u.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY p.year DESC, p.month DESC, p.id DESC",
        $params
    );

    $mapped = array_map(function ($row) {
        $monthNumber = payroll_month_number($row['month'] ?? date('n'));
        $row['month_number'] = $monthNumber;
        $row['month_label'] = payroll_month_label($monthNumber);
        $row['net_salary'] = $row['net_salary'] ?? ((float) ($row['basic_salary'] ?? 0) + (float) ($row['allowances'] ?? 0) - (float) ($row['deductions'] ?? 0));
        return $row;
    }, $rows);

    json_response(['data' => $mapped]);
}

if ($method === 'POST') {
    $data = get_post_json();
    $action = $_GET['action'] ?? ($data['action'] ?? '');

    if ($action === 'generate_batch') {
        require_role(['superadmin', 'admin', 'accounts']);

        $monthNumber = payroll_month_number($data['month'] ?? $data['month_number'] ?? date('n'));
        $year = (int) ($data['year'] ?? date('Y'));
        $targetStaffId = (int) ($data['staff_id'] ?? 0);
        $workingDays = payroll_working_days($year, $monthNumber);
        $startDate = sprintf('%04d-%02d-01', $year, $monthNumber);
        $endDate = date('Y-m-t', strtotime($startDate));

        $where = ["is_active = 1", "role NOT IN ('student', 'parent', 'superadmin')"];
        $params = [];
        if ($targetStaffId > 0) {
            $where[] = 'id = ?';
            $params[] = $targetStaffId;
        }

        $staffRows = db_fetchAll("SELECT id, name, role FROM users WHERE " . implode(' AND ', $where) . " ORDER BY name ASC", $params);
        $generated = 0;
        $skipped = 0;

        foreach ($staffRows as $staff) {
            $structure = find_salary_structure($staff['id'], $endDate);
            if (!$structure) {
                $skipped++;
                continue;
            }

            $workedDays = (int) db_count(
                "SELECT COUNT(*) FROM staff_attendance_enhanced WHERE staff_id = ? AND date BETWEEN ? AND ? AND status IN ('present', 'late', 'half_day', 'half-day')",
                [$staff['id'], $startDate, $endDate]
            );

            $factor = min(1, $workedDays / $workingDays);

            $basicSalary = payroll_decimal(($structure['basic_salary'] ?? 0) * $factor);
            $allowances = payroll_decimal(
                (($structure['hra'] ?? 0) + ($structure['da'] ?? 0) + ($structure['conveyance'] ?? 0) +
                    ($structure['medical_allowance'] ?? 0) + ($structure['special_allowance'] ?? 0)) * $factor
            );
            $deductions = payroll_decimal(
                (($structure['pf_deduction'] ?? 0) + ($structure['esi_deduction'] ?? 0) +
                    ($structure['tax_deduction'] ?? 0) + ($structure['other_deductions'] ?? 0)) * $factor
            );

            payroll_upsert([
                'staff_id' => (int) $staff['id'],
                'month' => $monthNumber,
                'year' => $year,
                'basic_salary' => $basicSalary,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'status' => 'pending',
                'paid_date' => null,
            ]);
            $generated++;
        }

        audit_log('GENERATE', 'payroll', null, null, ['month' => $monthNumber, 'year' => $year, 'generated' => $generated, 'skipped' => $skipped]);
        json_response([
            'success' => true,
            'message' => "Payroll generated: $generated created, $skipped skipped.",
            'generated' => $generated,
            'skipped' => $skipped,
        ]);
    }

    require_role(['superadmin', 'admin', 'accounts']);
    $payload = payroll_payload($data);
    if (empty($payload['staff_id'])) {
        json_response(['error' => 'Staff ID required'], 400);
    }

    payroll_upsert($payload);
    audit_log('CREATE', 'payroll', null, null, $payload);
    json_response(['success' => true]);
}

if ($method === 'PUT') {
    $data = get_post_json();
    $action = $_GET['action'] ?? ($data['action'] ?? '');

    if ($action === 'mark_paid') {
        require_role(['superadmin', 'admin', 'accounts']);
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            json_response(['error' => 'Payroll ID required'], 400);
        }

        db_query(
            "UPDATE payroll SET status = 'paid', paid_date = ?" . (db_column_exists('payroll', 'updated_at') ? ', updated_at = NOW()' : '') . " WHERE id = ?",
            [date('Y-m-d'), $id]
        );
        audit_log('UPDATE', 'payroll', $id, null, ['status' => 'paid']);
        json_response(['success' => true]);
    }

    require_role(['superadmin', 'admin', 'accounts']);
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Payroll ID required'], 400);
    }

    $updates = [];
    $params = [];
    foreach (['basic_salary', 'allowances', 'deductions', 'status', 'paid_date'] as $field) {
        if (!array_key_exists($field, $data)) {
            continue;
        }
        $updates[] = "$field = ?";
        $params[] = in_array($field, ['basic_salary', 'allowances', 'deductions'], true)
            ? payroll_decimal($data[$field])
            : ($field === 'status' ? sanitize($data[$field]) : ($data[$field] ?: null));
    }

    if (!$updates) {
        json_response(['error' => 'Nothing to update'], 400);
    }
    if (db_column_exists('payroll', 'updated_at')) {
        $updates[] = 'updated_at = NOW()';
    }
    $params[] = $id;
    db_query("UPDATE payroll SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    audit_log('UPDATE', 'payroll', $id, null, $data);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
