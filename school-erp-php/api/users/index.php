<?php
/**
 * Users Management API
 * School ERP PHP v3.0
 * GET: List users with pagination/search
 * POST: Create/Update user
 * DELETE: Delete user
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

require_auth();
require_role(['admin', 'superadmin', 'hr']);

$method = $_SERVER['REQUEST_METHOD'];

// GET - List users
if ($method === 'GET') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : PAGINATION_DEFAULT;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    
    $where = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where[] = '(name LIKE ? OR email LIKE ? OR employee_id LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($role)) {
        $where[] = 'role = ?';
        $params[] = $role;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT id, name, email, role, employee_id, department, designation, phone, avatar, is_active, created_at 
            FROM users 
            WHERE $whereClause 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $countSql = "SELECT COUNT(*) FROM users WHERE $whereClause";
    
    $users = db_fetchAll($sql, $params);
    $total = db_count($countSql, $params);
    
    json_response([
        'users' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ]
    ]);
}

// POST - Create or Update user
if ($method === 'POST') {
    $data = get_post_json();
    
    $id = $data['id'] ?? null;
    
    if ($id) {
        // Update existing user
        $user = db_fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            json_response(['error' => 'User not found'], 404);
        }
        
        $updates = [];
        $updateParams = [];
        
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $updateParams[] = Validator::sanitize($data['name']);
        }
        
        if (isset($data['email'])) {
            Validator::email($data['email']);
            $updates[] = 'email = ?';
            $updateParams[] = $data['email'];
        }
        
        if (isset($data['role'])) {
            Validator::in($data['role'], all_school_roles(), 'role');
            $updates[] = 'role = ?';
            $updateParams[] = $data['role'];
        }
        
        if (isset($data['phone'])) {
            $updates[] = 'phone = ?';
            $updateParams[] = Validator::sanitize($data['phone']);
        }
        
        if (isset($data['department'])) {
            $updates[] = 'department = ?';
            $updateParams[] = Validator::sanitize($data['department']);
        }
        
        if (isset($data['designation'])) {
            $updates[] = 'designation = ?';
            $updateParams[] = Validator::sanitize($data['designation']);
        }
        
        if (isset($data['is_active'])) {
            $updates[] = 'is_active = ?';
            $updateParams[] = (int)$data['is_active'];
        }
        
        if (!empty($data['password'])) {
            Validator::password($data['password']);
            $updates[] = 'password = ?';
            $updateParams[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (Validator::hasErrors()) {
            json_response(['errors' => Validator::errors()], 422);
        }
        
        if (empty($updates)) {
            json_response(['error' => 'No data to update'], 400);
        }
        
        $updateParams[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        db_query($sql, $updateParams);
        
        audit_log('UPDATE', 'users', $id, $user, $data);
        
        json_response(['message' => 'User updated successfully', 'id' => $id]);
    } else {
        // Create new user
        Validator::required($data, ['name', 'email', 'password', 'role']);
        Validator::email($data['email']);
        Validator::password($data['password']);
        Validator::in($data['role'], all_school_roles(), 'role');
        
        if (Validator::hasErrors()) {
            json_response(['errors' => Validator::errors()], 422);
        }
        
        // Check if email already exists
        $existing = db_fetch("SELECT id FROM users WHERE email = ?", [$data['email']]);
        if ($existing) {
            json_response(['error' => 'Email already exists'], 409);
        }
        
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (name, email, password, role, employee_id, department, designation, phone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            Validator::sanitize($data['name']),
            $data['email'],
            $hashedPassword,
            $data['role'],
            $data['employee_id'] ?? null,
            $data['department'] ?? null,
            $data['designation'] ?? null,
            $data['phone'] ?? null,
        ];
        
        $userId = db_insert($sql, $params);
        
        audit_log('CREATE', 'users', $userId, null, $data);
        
        json_response(['message' => 'User created successfully', 'id' => $userId], 201);
    }
}

// DELETE - Delete user
if ($method === 'DELETE') {
    $data = get_post_json();
    $id = $data['id'] ?? null;
    
    if (!$id) {
        json_response(['error' => 'User ID is required'], 400);
    }
    
    $user = db_fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        json_response(['error' => 'User not found'], 404);
    }
    
    // Prevent self-deletion
    if ($id == get_current_user_id()) {
        json_response(['error' => 'Cannot delete your own account'], 400);
    }
    
    db_query("DELETE FROM users WHERE id = ?", [$id]);
    
    audit_log('DELETE', 'users', $id, $user, null);
    
    json_response(['message' => 'User deleted successfully']);
}
