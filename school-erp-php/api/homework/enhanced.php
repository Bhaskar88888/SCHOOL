<?php
/**
 * Homework Enhanced API - My homework (student/parent)
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

// GET - My homework
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'my') {
    $userId = get_current_user_id();
    $role = get_current_role();
    
    if ($role === 'student') {
        $student = db_fetch("SELECT class_id FROM students WHERE user_id = ?", [$userId]);
        $classId = $student['class_id'] ?? null;
        
        if ($classId) {
            $homework = db_fetchAll("SELECT h.*, u.name as teacher_name, c.name as class_name 
                                    FROM homework h 
                                    LEFT JOIN users u ON h.assigned_by = u.id 
                                    LEFT JOIN classes c ON h.class_id = c.id 
                                    WHERE h.class_id = ? 
                                    ORDER BY h.due_date DESC", [$classId]);
        } else {
            $homework = [];
        }
    } elseif ($role === 'parent') {
        $students = db_fetchAll("SELECT class_id FROM students WHERE parent_user_id = ?", [$userId]);
        $classIds = array_column($students, 'class_id');
        
        if (!empty($classIds)) {
            $placeholders = implode(',', array_fill(0, count($classIds), '?'));
            $homework = db_fetchAll("SELECT h.*, u.name as teacher_name, c.name as class_name 
                                    FROM homework h 
                                    LEFT JOIN users u ON h.assigned_by = u.id 
                                    LEFT JOIN classes c ON h.class_id = c.id 
                                    WHERE h.class_id IN ($placeholders) 
                                    ORDER BY h.due_date DESC", $classIds);
        } else {
            $homework = [];
        }
    } else {
        $homework = [];
    }
    
    json_response(['homework' => $homework]);
}

// Include regular homework API
require_once __DIR__ . '/index.php';
