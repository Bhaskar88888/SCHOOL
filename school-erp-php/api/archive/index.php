<?php
/**
 * Archive API - Archived Students, Staff, Fees, Exams
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$action = $_GET['action'] ?? 'students';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : PAGINATION_DEFAULT;
$offset = ($page - 1) * $limit;

// Archive Students
if ($action === 'students') {
    if (!db_table_exists('archived_students')) {
        json_response(['error' => 'Archive table not set up'], 500);
    }
    
    $search = $_GET['search'] ?? '';
    $where = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where[] = '(name LIKE ? OR admission_no LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT * FROM archived_students WHERE $whereClause ORDER BY archived_at DESC LIMIT $limit OFFSET $offset";
    $countSql = "SELECT COUNT(*) FROM archived_students WHERE $whereClause";
    
    $archived = db_fetchAll($sql, $params);
    $total = db_count($countSql, $params);
    
    json_response([
        'archived' => $archived,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ]
    ]);
}

// Archive Staff
if ($action === 'staff') {
    if (!db_table_exists('archived_staff')) {
        json_response(['error' => 'Archive table not set up'], 500);
    }
    
    $search = $_GET['search'] ?? '';
    $where = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where[] = '(name LIKE ? OR email LIKE ? OR employee_id LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT * FROM archived_staff WHERE $whereClause ORDER BY archived_at DESC LIMIT $limit OFFSET $offset";
    $countSql = "SELECT COUNT(*) FROM archived_staff WHERE $whereClause";
    
    $archived = db_fetchAll($sql, $params);
    $total = db_count($countSql, $params);
    
    json_response([
        'archived' => $archived,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ]
    ]);
}

// Archive Fees
if ($action === 'fees') {
    $search = $_GET['search'] ?? '';
    $where = ['f.is_active = 0'];
    $params = [];
    
    if (!empty($search)) {
        $where[] = '(s.name LIKE ? OR f.receipt_no LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT f.*, s.name as student_name, s.admission_no 
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            WHERE $whereClause 
            ORDER BY f.created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $countSql = "SELECT COUNT(*) FROM fees f LEFT JOIN students s ON f.student_id = s.id WHERE $whereClause";
    
    $archived = db_fetchAll($sql, $params);
    $total = db_count($countSql, $params);
    
    json_response([
        'archived' => $archived,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ]
    ]);
}

// Archive Exams
if ($action === 'exams') {
    $search = $_GET['search'] ?? '';
    $where = ['e.is_archived = 1'];
    $params = [];
    
    if (!empty($search)) {
        $where[] = '(e.name LIKE ? OR e.subject LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!db_column_exists('exams', 'is_archived')) {
        // If column doesn't exist, use date filter (exams older than 1 year)
        $where = ["e.exam_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)"];
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT e.*, c.name as class_name 
            FROM exams e 
            LEFT JOIN classes c ON e.class_id = c.id 
            WHERE $whereClause 
            ORDER BY e.exam_date DESC 
            LIMIT $limit OFFSET $offset";
    
    $countSql = "SELECT COUNT(*) FROM exams e WHERE $whereClause";
    
    $archived = db_fetchAll($sql, $params);
    $total = db_count($countSql, $params);
    
    json_response([
        'archived' => $archived,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ]
    ]);
}
