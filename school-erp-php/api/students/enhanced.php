<?php
/**
 * Students Enhanced API - Stats, Search, Promote
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Stats
if (isset($_GET['action']) && $_GET['action'] === 'stats') {
    $stats = db_fetch("SELECT 
        COUNT(*) as total_students,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_count,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_count,
        COUNT(CASE WHEN transport_required = 1 THEN 1 END) as transport_count,
        COUNT(CASE WHEN hostel_required = 1 THEN 1 END) as hostel_count");
    
    // By class
    $byClass = db_fetchAll("SELECT c.name, COUNT(s.id) as student_count 
                            FROM classes c 
                            LEFT JOIN students s ON s.class_id = c.id AND s.is_active = 1 
                            GROUP BY c.id, c.name 
                            ORDER BY c.name");
    
    json_response(['summary' => $stats, 'byClass' => $byClass]);
}

// GET - Search
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $query = $_GET['q'] ?? '';
    $classId = $_GET['class_id'] ?? null;
    $section = $_GET['section'] ?? null;
    $gender = $_GET['gender'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $where = ['s.is_active = 1'];
    $params = [];
    
    if ($query) {
        $where[] = '(s.name LIKE ? OR s.admission_no LIKE ? OR s.parent_name LIKE ?)';
        $params[] = "%$query%";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }
    
    if ($classId) {
        $where[] = 's.class_id = ?';
        $params[] = $classId;
    }
    
    if ($section) {
        $where[] = 's.section = ?';
        $params[] = $section;
    }
    
    if ($gender) {
        $where[] = 's.gender = ?';
        $params[] = $gender;
    }
    
    $whereClause = implode(' AND ', $where);
    $sql = "SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE $whereClause ORDER BY s.name LIMIT $limit";
    
    $students = db_fetchAll($sql, $params);
    json_response(['students' => $students, 'total' => count($students)]);
}

// GET - Students by class
if (preg_match('/\/class\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches)) {
    $classId = $matches[1];
    $students = db_fetchAll("SELECT * FROM students WHERE class_id = ? AND is_active = 1 ORDER BY name", [$classId]);
    json_response(['students' => $students]);
}

// PUT - Promote student
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'promote') {
    require_role(['admin', 'superadmin']);
    $data = get_post_json();
    
    if (empty($data['student_id']) || empty($data['new_class_id'])) {
        json_response(['error' => 'student_id and new_class_id required'], 400);
    }
    
    $student = db_fetch("SELECT * FROM students WHERE id = ?", [$data['student_id']]);
    if (!$student) {
        json_response(['error' => 'Student not found'], 404);
    }
    
    $oldClass = $student['class_id'];
    db_query("UPDATE students SET class_id = ?, section = ? WHERE id = ?", [$data['new_class_id'], $data['section'] ?? null, $data['student_id']]);
    
    audit_log('PROMOTE', 'students', $data['student_id'], ['class_id' => $oldClass], ['class_id' => $data['new_class_id']]);
    json_response(['message' => 'Student promoted']);
}

json_response(['message' => 'Use ?action=stats|search or /class/:id or PUT ?action=promote']);
