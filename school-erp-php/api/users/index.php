<?php
/**
 * Users Management API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
require_role(['admin', 'superadmin', 'hr']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

$requestData = get_post_json();
$method = $_SERVER['REQUEST_METHOD'];
// Disable _method spoofing for security — use proper REST method
// if ($method === 'POST' && strtoupper($requestData['_method'] ?? '') === 'DELETE') {
//     $method = 'DELETE';
// }

if ($method === 'GET') {
    handle_users_list();
}

if ($method === 'POST') {
    handle_users_save($requestData);
}

if ($method === 'DELETE') {
    handle_users_delete($requestData);
}

json_response(['error' => 'Method not allowed'], 405);

function handle_users_list()
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = pagination_limit($_GET['limit'] ?? null);
    $offset = ($page - 1) * $limit;
    $search = trim((string) ($_GET['search'] ?? ''));
    $role = normalize_role_name($_GET['role'] ?? '');
    $userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    $where = ['1=1'];
    $params = [];

    if ($userId > 0) {
        $where[] = 'u.id = ?';
        $params[] = $userId;
    }

    if ($search !== '') {
        $searchParts = ['u.name LIKE ?', 'u.email LIKE ?'];
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;

        if (db_column_exists('users', 'employee_id')) {
            $searchParts[] = 'u.employee_id LIKE ?';
            $params[] = $searchParam;
        }
        if (db_column_exists('users', 'phone')) {
            $searchParts[] = 'u.phone LIKE ?';
            $params[] = $searchParam;
        }

        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    if ($role !== '') {
        if ($role === 'accounts') {
            $where[] = '(u.role = ? OR u.role = ?)';
            $params[] = 'accounts';
            $params[] = 'accountant';
        } else {
            $where[] = 'u.role = ?';
            $params[] = storage_role_name($role);
        }
    }

    $select = [
        'u.id',
        'u.name',
        'u.email',
        'u.role',
        user_column_expr('employee_id'),
        user_column_expr('department'),
        user_column_expr('designation'),
        user_column_expr('phone'),
        user_column_expr('avatar'),
        user_column_expr('is_active', '1'),
        user_column_expr('created_at'),
    ];

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT " . implode(', ', $select) . "
            FROM users u
            WHERE $whereClause
            ORDER BY " . (db_column_exists('users', 'created_at') ? 'u.created_at DESC' : 'u.id DESC') . "
            LIMIT $limit OFFSET $offset";

    $countSql = "SELECT COUNT(*) FROM users u WHERE $whereClause";

    $users = array_map('normalize_user_record', db_fetchAll($sql, $params));

    if ($userId > 0) {
        $user = $users[0] ?? null;
        if (!$user) {
            json_response(['error' => 'User not found'], 404);
        }
        json_response(['user' => $user]);
    }

    $total = (int) db_count($countSql, $params);
    json_response([
        'users' => $users,
        'data' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
        ],
    ]);
}

function handle_users_save(array $data)
{
    Validator::reset();

    $id = isset($data['id']) ? (int) $data['id'] : 0;
    if ($id > 0) {
        $existing = db_fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$existing) {
            json_response(['error' => 'User not found'], 404);
        }

        $updateData = [];

        if (array_key_exists('name', $data)) {
            $name = Validator::sanitize($data['name']);
            if ($name === '') {
                Validator::required(['name' => ''], ['name']);
            } else {
                $updateData['name'] = $name;
            }
        }

        if (array_key_exists('email', $data)) {
            Validator::email($data['email']);
            $email = trim((string) $data['email']);
            if ($email !== '' && strcasecmp($email, (string) $existing['email']) !== 0) {
                $duplicate = db_fetch("SELECT id FROM users WHERE email = ? AND id <> ?", [$email, $id]);
                if ($duplicate) {
                    json_response(['error' => 'Email already exists'], 409);
                }
            }
            $updateData['email'] = $email;
        }

        if (array_key_exists('role', $data)) {
            $role = normalize_role_name($data['role']);
            if ($role === 'superadmin' && normalize_role_name(get_current_role()) !== 'superadmin') {
                json_response(['error' => 'Only superadmins can assign the superadmin role'], 403);
            }
            Validator::in($role, all_school_roles(), 'role');
            $updateData['role'] = storage_role_name($role);
        }

        if (array_key_exists('phone', $data)) {
            $updateData['phone'] = nullable_text($data['phone']);
        }

        if (array_key_exists('employee_id', $data)) {
            $updateData['employee_id'] = nullable_text($data['employee_id']);
        }

        if (array_key_exists('department', $data)) {
            $updateData['department'] = nullable_text($data['department']);
        }

        if (array_key_exists('designation', $data)) {
            $updateData['designation'] = nullable_text($data['designation']);
        }

        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }

        if (!empty($data['password'])) {
            Validator::password($data['password']);
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (Validator::hasErrors()) {
            json_response(['errors' => Validator::errors()], 422);
        }

        $resolvedRole = normalize_role_name($updateData['role'] ?? ($existing['role'] ?? ''));
        if (role_requires_employee_id($resolvedRole)) {
            if (array_key_exists('employee_id', $updateData) && empty($updateData['employee_id'])) {
                $updateData['employee_id'] = !empty($existing['employee_id']) ? $existing['employee_id'] : generate_unique_employee_id();
            } elseif (!array_key_exists('employee_id', $updateData) && empty($existing['employee_id'])) {
                $updateData['employee_id'] = generate_unique_employee_id();
            }
        }

        if (array_key_exists('employee_id', $updateData) && !empty($updateData['employee_id']) && employee_id_exists($updateData['employee_id'], $id)) {
            json_response(['error' => 'Employee ID already exists'], 409);
        }

        $updateData = db_filter_data_for_table('users', $updateData);
        if (empty($updateData)) {
            json_response(['error' => 'No data to update'], 400);
        }

        $setParts = [];
        $params = [];
        foreach ($updateData as $column => $value) {
            $setParts[] = "`$column` = ?";
            $params[] = $value;
        }
        $params[] = $id;

        db_query("UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?", $params);
        sync_parent_student_links($id, $resolvedRole, $data);
        audit_log('UPDATE', 'users', $id, normalize_user_record($existing), $updateData);

        $saved = db_fetch("SELECT * FROM users WHERE id = ?", [$id]);
        json_response([
            'message' => 'User updated successfully',
            'id' => $id,
            'user' => normalize_user_record($saved),
        ]);
    }

    Validator::required($data, ['name', 'email', 'password', 'role']);
    Validator::email($data['email'] ?? '');
    Validator::password($data['password'] ?? '');
    $normalizedRole = normalize_role_name($data['role'] ?? '');
    if ($normalizedRole === 'superadmin' && normalize_role_name(get_current_role()) !== 'superadmin') {
        json_response(['error' => 'Only superadmins can create a superadmin user'], 403);
    }
    Validator::in($normalizedRole, all_school_roles(), 'role');

    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }

    $email = trim((string) $data['email']);
    $existing = db_fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        json_response(['error' => 'Email already exists'], 409);
    }

    $employeeId = nullable_text($data['employee_id'] ?? '');
    if (role_requires_employee_id($normalizedRole) && empty($employeeId)) {
        $employeeId = generate_unique_employee_id();
    }
    if (!empty($employeeId) && employee_id_exists($employeeId)) {
        json_response(['error' => 'Employee ID already exists'], 409);
    }

    $insertData = [
        'name' => Validator::sanitize($data['name']),
        'email' => $email,
        'password' => password_hash($data['password'], PASSWORD_BCRYPT),
        'role' => storage_role_name($normalizedRole),
        'employee_id' => $employeeId,
        'department' => nullable_text($data['department'] ?? ''),
        'designation' => nullable_text($data['designation'] ?? ''),
        'phone' => nullable_text($data['phone'] ?? ''),
        'is_active' => array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
    ];

    $insertData = db_filter_data_for_table('users', $insertData);
    $columns = array_keys($insertData);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $params = array_values($insertData);

    $userId = db_insert(
        "INSERT INTO users (" . implode(', ', array_map(function ($column) {
            return "`$column`";
        }, $columns)) . ") VALUES ($placeholders)",
        $params
    );

    sync_parent_student_links($userId, $normalizedRole, $data);

    $created = db_fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    audit_log('CREATE', 'users', $userId, null, normalize_user_record($created));

    json_response([
        'message' => 'User created successfully',
        'id' => $userId,
        'user' => normalize_user_record($created),
    ], 201);
}

function handle_users_delete(array $data)
{
    $id = isset($data['id']) ? (int) $data['id'] : 0;
    if ($id <= 0) {
        json_response(['error' => 'User ID is required'], 400);
    }

    if ($id === (int) get_current_user_id()) {
        json_response(['error' => 'Cannot delete your own account'], 400);
    }

    $user = db_fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        json_response(['error' => 'User not found'], 404);
    }

    db_query("UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
    audit_log('ARCHIVE', 'users', $id, normalize_user_record($user), ['is_active' => 0]);

    json_response(['message' => 'User deactivated successfully']);
}

function user_column_expr($column, $fallback = 'NULL')
{
    if (db_column_exists('users', $column)) {
        return 'u.`' . $column . '`';
    }
    return $fallback . ' AS `' . $column . '`';
}

function normalize_user_record($user)
{
    if (!$user) {
        return null;
    }

    return [
        'id' => (int) ($user['id'] ?? 0),
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'role' => normalize_role_name($user['role'] ?? ''),
        'employee_id' => $user['employee_id'] ?? null,
        'department' => $user['department'] ?? null,
        'designation' => $user['designation'] ?? null,
        'phone' => $user['phone'] ?? null,
        'avatar' => $user['avatar'] ?? null,
        'is_active' => isset($user['is_active']) ? (int) $user['is_active'] : 1,
        'created_at' => $user['created_at'] ?? null,
    ];
}

function nullable_text($value)
{
    $value = Validator::sanitize((string) $value);
    return $value === '' ? null : $value;
}

function role_requires_employee_id($role)
{
    return in_array(normalize_role_name($role), [
        'superadmin',
        'admin',
        'teacher',
        'staff',
        'hr',
        'accounts',
        'librarian',
        'canteen',
        'conductor',
        'driver',
    ], true);
}

function employee_id_exists($employeeId, $excludeId = 0)
{
    if (!db_column_exists('users', 'employee_id') || empty($employeeId)) {
        return false;
    }

    if ($excludeId > 0) {
        return (bool) db_fetch(
            "SELECT id FROM users WHERE employee_id = ? AND id <> ?",
            [$employeeId, $excludeId]
        );
    }

    return (bool) db_fetch("SELECT id FROM users WHERE employee_id = ?", [$employeeId]);
}

function generate_unique_employee_id()
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $employeeId = generate_auto_id('employee', 'EMP');
        if (!employee_id_exists($employeeId)) {
            return $employeeId;
        }
    }

    throw new RuntimeException('Unable to generate a unique employee ID.');
}

function sync_parent_student_links($userId, $role, array $data)
{
    if (normalize_role_name($role) !== 'parent' || !db_column_exists('students', 'parent_user_id')) {
        return;
    }

    $studentIds = [];
    if (!empty($data['student_id'])) {
        $studentIds[] = (int) $data['student_id'];
    }
    if (!empty($data['student_ids']) && is_array($data['student_ids'])) {
        foreach ($data['student_ids'] as $studentId) {
            if ((int) $studentId > 0) {
                $studentIds[] = (int) $studentId;
            }
        }
    }

    $studentIds = array_values(array_unique(array_filter($studentIds)));
    if (empty($studentIds)) {
        return;
    }

    foreach ($studentIds as $studentId) {
        db_query("UPDATE students SET parent_user_id = ? WHERE id = ?", [$userId, $studentId]);
    }
}
