<?php
/**
 * Classes Enhanced API - Stats, Teachers, Assign Teacher, Remove Subject
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
        COUNT(*) as total_classes,
        (SELECT COUNT(*) FROM students WHERE is_active = 1) as total_students,
        (SELECT ROUND(AVG(student_count), 2) FROM (SELECT COUNT(s.id) as student_count FROM classes c LEFT JOIN students s ON s.class_id = c.id AND s.is_active = 1 GROUP BY c.id)) as avg_students_per_class");
    
    $distribution = db_fetchAll("SELECT c.name, COUNT(s.id) as student_count 
                                 FROM classes c 
                                 LEFT JOIN students s ON s.class_id = c.id AND s.is_active = 1 
                                 GROUP BY c.id, c.name 
                                 ORDER BY c.name");
    
    json_response(['summary' => $stats, 'distribution' => $distribution]);
}

// GET - Teachers list
if (isset($_GET['action']) && $_GET['action'] === 'teachers') {
    $teachers = db_fetchAll("SELECT id, name, email, employee_id, department, designation 
                             FROM users 
                             WHERE role = 'teacher' AND is_active = 1 
                             ORDER BY name");
    json_response(['teachers' => $teachers]);
}

// POST - Assign teacher to subject
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assign-teacher') {
    require_role(['admin', 'superadmin']);
    $data = get_post_json();
    Validator::required($data, ['class_id', 'subject', 'teacher_id']);
    
    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }
    
    $sql = "INSERT INTO class_subjects (class_id, subject, teacher_id, periods_per_week) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id), periods_per_week = VALUES(periods_per_week)";
    
    $id = db_insert($sql, [$data['class_id'], $data['subject'], $data['teacher_id'], $data['periods_per_week'] ?? 5]);
    audit_log('ASSIGN_TEACHER', 'classes', $data['class_id'], null, $data);
    json_response(['message' => 'Teacher assigned to subject', 'id' => $id], 201);
}

// DELETE - Remove subject from class
if ($method === 'DELETE') {
    require_role(['admin', 'superadmin']);
    $classId = $_GET['class_id'] ?? null;
    $subject = $_GET['subject'] ?? null;
    
    if (!$classId || !$subject) {
        json_response(['error' => 'class_id and subject required'], 400);
    }
    
    db_query("DELETE FROM class_subjects WHERE class_id = ? AND subject = ?", [$classId, $subject]);
    audit_log('REMOVE_SUBJECT', 'classes', $classId, ['subject' => $subject], null);
    json_response(['message' => 'Subject removed from class']);
}

json_response(['message' => 'Use ?action=stats|teachers|assign-teacher']);
