<?php
/**
 * Remarks Enhanced API - My, Teacher, Student views
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$userId = get_current_user_id();
$role = get_current_role();

// GET - Enhanced views
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // My remarks (student/parent)
    if ($action === 'my') {
        if ($role === 'student') {
            $student = db_fetch("SELECT id FROM students WHERE user_id = ?", [$userId]);
            $studentId = $student['id'] ?? null;
            $remarks = $studentId ? db_fetchAll("SELECT r.*, u.name as teacher_name FROM remarks r LEFT JOIN users u ON r.teacher_id = u.id WHERE r.student_id = ? ORDER BY r.created_at DESC", [$studentId]) : [];
        } elseif ($role === 'parent') {
            $students = db_fetchAll("SELECT id FROM students WHERE parent_user_id = ?", [$userId]);
            $studentIds = array_column($students, 'id');
            $remarks = !empty($studentIds) ? db_fetchAll("SELECT r.*, u.name as teacher_name, s.name as student_name FROM remarks r LEFT JOIN users u ON r.teacher_id = u.id LEFT JOIN students s ON r.student_id = s.id WHERE r.student_id IN (" . implode(',', array_fill(0, count($studentIds), '?')) . ") ORDER BY r.created_at DESC", $studentIds) : [];
        } else {
            $remarks = [];
        }
        json_response(['remarks' => $remarks]);
    }
    
    // Teacher's remarks
    if ($action === 'teacher') {
        $remarks = db_fetchAll("SELECT r.*, s.name as student_name, c.name as class_name 
                               FROM remarks r 
                               LEFT JOIN students s ON r.student_id = s.id 
                               LEFT JOIN classes c ON s.class_id = c.id 
                               WHERE r.teacher_id = ? 
                               ORDER BY r.created_at DESC 
                               LIMIT 100", [$userId]);
        json_response(['remarks' => $remarks]);
    }
    
    // Student remarks
    if (preg_match('/\/student\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches)) {
        $studentId = $matches[1];
        $remarks = db_fetchAll("SELECT r.*, u.name as teacher_name FROM remarks r LEFT JOIN users u ON r.teacher_id = u.id WHERE r.student_id = ? ORDER BY r.created_at DESC", [$studentId]);
        json_response(['remarks' => $remarks]);
    }
    
    // Regular remarks list
    $remarks = db_fetchAll("SELECT r.*, u.name as teacher_name, s.name as student_name FROM remarks r LEFT JOIN users u ON r.teacher_id = u.id LEFT JOIN students s ON r.student_id = s.id ORDER BY r.created_at DESC LIMIT 100");
    json_response(['remarks' => $remarks]);
}
