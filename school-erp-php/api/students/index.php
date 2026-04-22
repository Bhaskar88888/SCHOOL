<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

function truthy_flag($value)
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function nullable_text($value, $default = null)
{
    if ($value === null) {
        return $default;
    }

    $value = sanitize((string) $value);
    return $value === '' ? $default : $value;
}

function student_field_types()
{
    return [
        'user_id' => 'int',
        'name' => 'text',
        'admission_no' => 'text',
        'class_id' => 'int',
        'parent_user_id' => 'int',
        'section' => 'text',
        'roll_number' => 'text',
        'dob' => 'date',
        'gender' => 'text',
        'phone' => 'text',
        'email' => 'text',
        'parent_name' => 'text',
        'parent_phone' => 'text',
        'parent_email' => 'text',
        'father_name' => 'text',
        'father_occupation' => 'text',
        'mother_name' => 'text',
        'mother_phone' => 'text',
        'guardian_name' => 'text',
        'guardian_phone' => 'text',
        'aadhaar' => 'text',
        'blood_group' => 'text',
        'nationality' => 'text',
        'religion' => 'text',
        'category' => 'text',
        'mother_tongue' => 'text',
        'address' => 'text',
        'address_line1' => 'text',
        'address_line2' => 'text',
        'city' => 'text',
        'state' => 'text',
        'pincode' => 'text',
        'transport_required' => 'bool',
        'hostel_required' => 'bool',
        'medical_conditions' => 'text',
        'allergies' => 'text',
        'apaar_id' => 'text',
        'pen' => 'text',
        'enrollment_no' => 'text',
        'previous_school' => 'text',
        'emergency_contact_name' => 'text',
        'emergency_contact_phone' => 'text',
        'admission_notes' => 'text',
    ];
}

function build_student_payload(array $data, array $existing = [])
{
    $payload = [];
    foreach (student_field_types() as $field => $type) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        $value = $data[$field];
        if ($type === 'int') {
            $payload[$field] = ($value === '' || $value === null) ? null : (int) $value;
        } elseif ($type === 'bool') {
            $payload[$field] = truthy_flag($value);
        } else {
            $payload[$field] = nullable_text($value);
        }
    }

    if (empty($payload['parent_name'])) {
        $payload['parent_name'] = $payload['guardian_name']
            ?? $payload['father_name']
            ?? $payload['mother_name']
            ?? ($existing['parent_name'] ?? null);
    }

    if (empty($payload['phone']) && !empty($payload['parent_phone'])) {
        $payload['phone'] = $payload['parent_phone'];
    }
    if (empty($payload['parent_phone']) && !empty($payload['phone'])) {
        $payload['parent_phone'] = $payload['phone'];
    }

    if (empty($payload['email']) && !empty($payload['parent_email'])) {
        $payload['email'] = $payload['parent_email'];
    }
    if (empty($payload['parent_email']) && !empty($payload['email'])) {
        $payload['parent_email'] = $payload['email'];
    }

    if (empty($payload['address']) && !empty($payload['address_line1'])) {
        $payload['address'] = implode(', ', array_filter([
            $payload['address_line1'] ?? '',
            $payload['address_line2'] ?? '',
            $payload['city'] ?? '',
            $payload['state'] ?? '',
            $payload['pincode'] ?? '',
        ]));
    }

    if (empty($payload['address_line1']) && !empty($payload['address'])) {
        $payload['address_line1'] = $payload['address'];
    }

    if (empty($payload['nationality'])) {
        $payload['nationality'] = $existing['nationality'] ?? 'Indian';
    }

    if (!empty($payload['parent_user_id']) && db_column_exists('students', 'parent_user_id')) {
        $parentUser = db_fetch(
            "SELECT name, phone, email FROM users WHERE id = ? AND role = ?",
            [$payload['parent_user_id'], storage_role_name('parent')]
        );
        if ($parentUser) {
            if (empty($payload['parent_name'])) {
                $payload['parent_name'] = $parentUser['name'] ?? null;
            }
            if (empty($payload['parent_phone'])) {
                $payload['parent_phone'] = $parentUser['phone'] ?? null;
            }
            if (empty($payload['parent_email'])) {
                $payload['parent_email'] = $parentUser['email'] ?? null;
            }
        }
    }

    if (empty($payload['admission_no']) && db_column_exists('students', 'admission_no')) {
        $payload['admission_no'] = generate_auto_id('admission', 'ADM');
    }

    return db_filter_data_for_table('students', $payload);
}

function student_scope_sql(&$params)
{
    $role = normalize_role_name(get_current_role());
    $userId = get_current_user_id();

    if ($role === 'student' && db_column_exists('students', 'user_id')) {
        $params[] = $userId;
        return ' AND s.user_id = ?';
    }

    if ($role === 'parent') {
        if (db_column_exists('students', 'parent_user_id')) {
            $params[] = $userId;
            return ' AND s.parent_user_id = ?';
        }

        if (db_column_exists('students', 'parent_phone')) {
            $user = db_fetch("SELECT phone FROM users WHERE id = ?", [$userId]);
            if (!empty($user['phone'])) {
                $params[] = $user['phone'];
                return ' AND s.parent_phone = ?';
            }
        }

        return ' AND 1 = 0';
    }

    return '';
}

function insert_row($table, array $payload)
{
    $columns = [];
    $placeholders = [];
    $params = [];

    foreach ($payload as $column => $value) {
        $columns[] = $column;
        $placeholders[] = '?';
        $params[] = $value;
    }

    if (db_column_exists($table, 'is_active') && !array_key_exists('is_active', $payload)) {
        $columns[] = 'is_active';
        $placeholders[] = '1';
    }
    if (db_column_exists($table, 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if (db_column_exists($table, 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    return db_insert($sql, $params);
}

function update_row($table, array $payload, $id)
{
    $set = [];
    $params = [];

    foreach ($payload as $column => $value) {
        $set[] = "$column = ?";
        $params[] = $value;
    }

    if (db_column_exists($table, 'updated_at')) {
        $set[] = 'updated_at = NOW()';
    }

    $params[] = $id;
    db_query("UPDATE $table SET " . implode(', ', $set) . " WHERE id = ?", $params);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && !isset($_GET['id'])) {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = pagination_limit($_GET['limit'] ?? null);
    $offset = ($page - 1) * $limit;
    $search = trim((string) ($_GET['search'] ?? ''));
    $classId = (int) ($_GET['class_id'] ?? 0);
    $parentUserId = (int) ($_GET['parent_user_id'] ?? 0);
    $gender = trim((string) ($_GET['gender'] ?? ''));

    $where = ['s.is_active = 1'];
    $params = [];

    if ($search !== '') {
        $where[] = "(s.name LIKE ? OR COALESCE(s.admission_no, '') LIKE ? OR COALESCE(s.roll_number, '') LIKE ? OR COALESCE(s.parent_name, '') LIKE ? OR COALESCE(c.name, '') LIKE ?)";
        for ($i = 0; $i < 5; $i++) {
            $params[] = '%' . sanitize($search) . '%';
        }
    }

    if ($classId > 0) {
        $where[] = 's.class_id = ?';
        $params[] = $classId;
    }

    if ($parentUserId > 0 && db_column_exists('students', 'parent_user_id')) {
        $where[] = 's.parent_user_id = ?';
        $params[] = $parentUserId;
    }

    if ($gender !== '') {
        $where[] = 's.gender = ?';
        $params[] = sanitize($gender);
    }

    $scope = student_scope_sql($params);
    $whereSql = 'WHERE ' . implode(' AND ', $where) . $scope;

    $total = db_count(
        "SELECT COUNT(*) FROM students s LEFT JOIN classes c ON s.class_id = c.id $whereSql",
        $params
    );
    $students = db_fetchAll(
        "SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id $whereSql ORDER BY s.name ASC LIMIT $limit OFFSET $offset",
        $params
    );

    json_response([
        'data' => $students,
        'total' => (int) $total,
        'page' => $page,
        'pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
    ]);
}

if ($method === 'GET' && isset($_GET['id'])) {
    $params = [(int) $_GET['id']];
    $scope = student_scope_sql($params);
    $student = db_fetch(
        "SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?" . $scope,
        $params
    );

    if (!$student) {
        json_response(['error' => 'Student not found'], 404);
    }

    // Attendance stats
    $attTotal = db_count("SELECT COUNT(*) FROM attendance WHERE student_id=?", [$student['id']]);
    $attPresent = db_count("SELECT COUNT(*) FROM attendance WHERE student_id=? AND status='present'", [$student['id']]);
    $student['attendanceStats'] = [
        'total' => (int) $attTotal,
        'present' => (int) $attPresent,
        'percentage' => $attTotal > 0 ? round(($attPresent / $attTotal) * 100) : 0,
    ];
    // Recent fee payments
    $student['recentFeePayments'] = db_fetchAll(
        "SELECT * FROM fees WHERE student_id=? ORDER BY created_at DESC LIMIT 10",
        [$student['id']]
    );

    json_response($student);
}

if ($method === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $payload = build_student_payload($data);

    foreach (['name', 'class_id', 'dob', 'gender'] as $required) {
        if (empty($payload[$required])) {
            json_response(['error' => "Field '$required' is required"], 400);
        }
    }

    $id = insert_row('students', $payload);
    audit_log('CREATE', 'students', $id, null, $payload);

    // ── Auto-create parent portal account ──────────────────────────────
    // Only runs when parent_email or parent_phone is provided AND the
    // admin did not already manually link a parent account.
    if (empty($payload['parent_user_id'] ?? null)) {
        require_once __DIR__ . '/../../includes/parent_credentials.php';
        $parentEmail = $payload['parent_email'] ?? $data['parent_email'] ?? '';
        $parentPhone = $payload['parent_phone'] ?? $data['parent_phone'] ?? '';
        $parentName  = $payload['parent_name']  ?? $data['parent_name']  ?? 'Parent';
        $admNo       = $payload['admission_no'] ?? $data['admission_no'] ?? (string)$id;

        $parentUserId = ParentCredentials::ensureAccount($parentEmail, $parentPhone, $admNo, $parentName);

        if ($parentUserId && db_column_exists('students', 'parent_user_id')) {
            db_query("UPDATE students SET parent_user_id = ? WHERE id = ?", [$parentUserId, $id]);
        }
    }
    // ── End parent account auto-creation ──────────────────────────────

    json_response(['success' => true, 'id' => $id, 'message' => 'Student saved successfully. Parent credentials sent.']);
}

if ($method === 'PUT') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Student ID required'], 400);
    }

    $existing = db_fetch("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$existing) {
        json_response(['error' => 'Student not found'], 404);
    }

    $payload = build_student_payload($data, $existing);
    if (!$payload) {
        json_response(['error' => 'Nothing to update'], 400);
    }

    update_row('students', $payload, $id);
    audit_log('UPDATE', 'students', $id, $existing, $payload);
    json_response(['success' => true, 'message' => 'Student updated']);
}

if ($method === 'DELETE') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $id = (int) ($data['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        json_response(['error' => 'Student ID required'], 400);
    }

    $updates = ['is_active = 0'];
    $params = [];

    if (db_column_exists('students', 'discharge_date')) {
        $updates[] = 'discharge_date = ?';
        $params[] = nullable_text($data['discharge_date'] ?? date('Y-m-d'));
    }

    if (db_column_exists('students', 'discharge_reason')) {
        $updates[] = 'discharge_reason = ?';
        $params[] = nullable_text($data['discharge_reason'] ?? '');
    }

    if (db_column_exists('students', 'updated_at')) {
        $updates[] = 'updated_at = NOW()';
    }

    $params[] = $id;
    db_query("UPDATE students SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    if (db_column_exists('fees', 'is_active')) {
        db_query("UPDATE fees SET is_active = 0 WHERE student_id = ?", [$id]);
    }
    audit_log('ARCHIVE', 'students', $id, null, ['discharge_reason' => $data['discharge_reason'] ?? null]);
    json_response(['success' => true, 'message' => 'Student archived']);
}

json_response(['error' => 'Method not allowed'], 405);
