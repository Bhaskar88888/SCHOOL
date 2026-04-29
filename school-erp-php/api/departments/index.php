<?php
/**
 * Departments API
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit_logger.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_auth();

// Only superadmin, admin, and HR can manage departments
require_role(['superadmin', 'admin', 'hr']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    CSRFProtection::verifyToken();
}

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================================
// GET: List departments
// ============================================================================
if ($method === 'GET') {
    $departments = db_fetchAll(
        "SELECT d.*, u.name AS head_name,
                (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = 1) AS staff_count
         FROM departments d
         LEFT JOIN users u ON d.head_user_id = u.id
         WHERE d.is_active = 1
         ORDER BY d.name ASC"
    );

    // Also fetch staff eligible for department head
    $staff = db_fetchAll("SELECT id, name, role FROM users WHERE role NOT IN ('student', 'parent') AND is_active = 1 ORDER BY name ASC");

    json_response([
        'data'  => $departments,
        'staff' => $staff,
        'total' => count($departments)
    ]);
}

// ============================================================================
// POST: Create department
// ============================================================================
if ($method === 'POST') {
    $data = get_post_json();
    $name = trim($data['name'] ?? '');
    $code = trim($data['code'] ?? '');
    $head = !empty($data['head_user_id']) ? (int)$data['head_user_id'] : null;
    $desc = trim($data['description'] ?? '');

    if (empty($name)) {
        json_response(['error' => 'Department name is required'], 400);
    }

    $id = db_insert(
        "INSERT INTO departments (name, code, head_user_id, description) VALUES (?, ?, ?, ?)",
        [$name, $code, $head, $desc]
    );

    audit_log('CREATE', 'departments', $id, null, ['name' => $name, 'code' => $code]);
    json_response(['success' => true, 'id' => $id, 'message' => 'Department created successfully']);
}

// ============================================================================
// PUT: Update department
// ============================================================================
if ($method === 'PUT') {
    $data = get_post_json();
    $id   = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $code = trim($data['code'] ?? '');
    $head = !empty($data['head_user_id']) ? (int)$data['head_user_id'] : null;
    $desc = trim($data['description'] ?? '');

    if ($id <= 0) {
        json_response(['error' => 'Department ID is required'], 400);
    }
    if (empty($name)) {
        json_response(['error' => 'Department name is required'], 400);
    }

    db_query(
        "UPDATE departments SET name = ?, code = ?, head_user_id = ?, description = ? WHERE id = ?",
        [$name, $code, $head, $desc, $id]
    );

    audit_log('UPDATE', 'departments', $id, null, ['name' => $name]);
    json_response(['success' => true, 'message' => 'Department updated successfully']);
}

// ============================================================================
// DELETE: Archive department
// ============================================================================
if ($method === 'DELETE') {
    $data = get_post_json();
    $id   = (int)($data['id'] ?? ($_GET['id'] ?? 0));

    if ($id <= 0) {
        json_response(['error' => 'Department ID is required'], 400);
    }

    // Check if there are active staff in this department before deleting
    $staffCount = db_count("SELECT COUNT(*) FROM users WHERE department_id = ? AND is_active = 1", [$id]);
    if ($staffCount > 0) {
        json_response(['error' => "Cannot delete department. There are $staffCount active staff members assigned to it. Reassign them first."], 400);
    }

    db_query("UPDATE departments SET is_active = 0 WHERE id = ?", [$id]);
    audit_log('ARCHIVE', 'departments', $id);
    json_response(['success' => true, 'message' => 'Department archived']);
}

json_response(['error' => 'Method not allowed'], 405);
