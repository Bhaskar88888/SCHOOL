<?php
/**
 * Enhanced Exam API with Analytics, Report Cards, Bulk Results
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notify.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$currentRole = get_current_role();

if ($method !== 'GET' && $method !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

// GET - Enhanced endpoints
if ($method === 'GET') {
    // Analytics
    if (isset($_GET['analytics'])) {
        $classId = (int) ($_GET['class_id'] ?? 0);
        $where = ['1=1'];
        $params = [];

        if ($classId) {
            $where[] = 'e.class_id = ?';
            $params[] = $classId;
        }

        $whereClause = implode(' AND ', $where);

        $analytics = db_fetchAll("SELECT 
            e.name as exam_name, e.subject, e.exam_date,
            ROUND(AVG((er.marks_obtained / NULLIF(e.max_marks, 0)) * 100), 2) as avg_percentage,
            ROUND(MAX((er.marks_obtained / NULLIF(e.max_marks, 0)) * 100), 2) as max_percentage,
            ROUND(MIN((er.marks_obtained / NULLIF(e.max_marks, 0)) * 100), 2) as min_percentage,
            COUNT(CASE WHEN er.status = 'pass' THEN 1 END) as passed,
            COUNT(CASE WHEN er.status = 'fail' THEN 1 END) as failed,
            COUNT(*) as total_students,
            ROUND(COUNT(CASE WHEN er.status = 'pass' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as pass_rate
            FROM exam_results er 
            LEFT JOIN exams e ON er.exam_id = e.id 
            WHERE $whereClause 
            GROUP BY e.id 
            ORDER BY e.exam_date DESC");

        // Grade distribution
        $gradeDist = db_fetchAll("SELECT grade, COUNT(*) as count FROM exam_results WHERE grade IS NOT NULL AND grade != '' GROUP BY grade ORDER BY grade");

        json_response(['analytics' => $analytics, 'gradeDistribution' => $gradeDist]);
    }

    // Report card for student
    if (isset($_GET['report_card'])) {
        $studentId = $_GET['student_id'] ?? null;
        if (!$studentId) {
            json_response(['error' => 'student_id required'], 400);
        }

        $student = db_fetch("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$studentId]);
        if (!$student) {
            json_response(['error' => 'Student not found'], 404);
        }

        $results = db_fetchAll("SELECT er.*, e.name as exam_name, e.subject, e.exam_date, e.max_marks, e.pass_marks,
                                       ROUND((er.marks_obtained / NULLIF(e.max_marks, 0)) * 100, 2) as percentage
                               FROM exam_results er 
                               LEFT JOIN exams e ON er.exam_id = e.id 
                               WHERE er.student_id = ? 
                               ORDER BY e.exam_date DESC", [$studentId]);

        json_response(['student' => $student, 'results' => $results]);
    }

    // Student's all results
    if (preg_match('/\/results\/student\/(\d+)$/', $path, $matches)) {
        $studentId = $matches[1];
        $results = db_fetchAll("SELECT er.*, e.name as exam_name, e.subject, e.exam_date, e.max_marks, e.pass_marks,
                                       ROUND((er.marks_obtained / NULLIF(e.max_marks, 0)) * 100, 2) as percentage 
                               FROM exam_results er 
                               LEFT JOIN exams e ON er.exam_id = e.id 
                               WHERE er.student_id = ? 
                               ORDER BY e.exam_date DESC", [$studentId]);

        $summary = db_fetch("SELECT 
            COUNT(*) as total_exams,
            ROUND(AVG((er.marks_obtained / NULLIF(e.max_marks, 0)) * 100), 2) as avg_percentage,
            COUNT(CASE WHEN er.status = 'pass' THEN 1 END) as passed,
            COUNT(CASE WHEN er.status = 'fail' THEN 1 END) as failed
            FROM exam_results er
            LEFT JOIN exams e ON er.exam_id = e.id
            WHERE er.student_id = ?", [$studentId]);

        json_response(['results' => $results, 'summary' => $summary]);
    }

    // Results for specific exam
    if (preg_match('/\/results\/exam\/(\d+)$/', $path, $matches)) {
        $examId = $matches[1];
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        
        $search = '%' . sanitize($_GET['search'] ?? '') . '%';
        $params = [$examId, $search, $limit, $offset];

        $results = db_fetchAll("SELECT er.*, s.name as student_name, s.admission_no, e.max_marks,
                                       ROUND((er.marks_obtained / NULLIF(e.max_marks, 0)) * 100, 2) as percentage
                               FROM exam_results er 
                               LEFT JOIN students s ON er.student_id = s.id 
                               LEFT JOIN exams e ON er.exam_id = e.id
                               WHERE er.exam_id = ? AND s.name LIKE ?
                               ORDER BY s.name 
                               LIMIT ? OFFSET ?", $params);
                               
        $total = db_count("SELECT COUNT(*) FROM exam_results er LEFT JOIN students s ON er.student_id = s.id WHERE er.exam_id = ? AND s.name LIKE ?", [$examId, $search]);

        json_response([
            'results' => $results,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    // Default GET exams list with pagination & search
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $classId = (int)($_GET['class_id'] ?? 0);

    $where = ["(e.name LIKE ? OR e.subject LIKE ?)"];
    $params = [$search, $search];

    if ($classId) {
        $where[] = "e.class_id = ?";
        $params[] = $classId;
    }
    
    $whereSql = implode(' AND ', $where);
    $total = db_count("SELECT COUNT(*) FROM exams e WHERE $whereSql", $params);
    $params[] = $limit;
    $params[] = $offset;
    
    $exams = db_fetchAll("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE $whereSql ORDER BY e.exam_date DESC LIMIT ? OFFSET ?", $params);
    
    json_response(['exams' => $exams, 'total' => $total, 'pages' => ceil($total / $limit)]);
}

// POST - Bulk save results
if ($method === 'POST') {
    require_role(['admin', 'superadmin', 'teacher']);
    $data = get_post_json();

    // Bulk results
    if (isset($data['results']) && is_array($data['results'])) {
        $saved = 0;
        foreach ($data['results'] as $result) {
            if (empty($result['exam_id']) || empty($result['student_id'])) {
                continue;
            }

            $examId = (int)$result['exam_id'];
            $studentId = (int)$result['student_id'];
            
            $exam = db_fetch("SELECT max_marks, pass_marks FROM exams WHERE id = ?", [$examId]);
            if (!$exam) continue;

            $maxMarks = (float)($exam['max_marks'] ?? 100);
            $passMarks = (float)($exam['pass_marks'] ?? 33);
            
            $statusInput = strtolower($result['status'] ?? '');
            $marksInput = isset($result['marks_obtained']) && is_string($result['marks_obtained']) ? strtolower($result['marks_obtained']) : '';

            if ($statusInput === 'absent' || $marksInput === 'absent') {
                $marks = 0; 
                $status = 'absent';
                $grade = 'F'; 
            } else {
                $marks = (float) ($result['marks_obtained'] ?? 0);
                if ($marks < 0) $marks = 0;
                if ($marks > $maxMarks) $marks = $maxMarks;
                
                $grade = calculate_grade($marks, $maxMarks);
                $status = ($marks >= $passMarks) ? 'pass' : 'fail';
            }

            db_query("INSERT INTO exam_results (exam_id, student_id, marks_obtained, grade, status, entered_by) 
                      VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE 
                      marks_obtained = VALUES(marks_obtained), 
                      grade = VALUES(grade), 
                      status = VALUES(status),
                      entered_by = VALUES(entered_by)",
                [$examId, $studentId, ($status === 'absent' ? null : $marks), $grade, $status, get_current_user_id()]
            );
            $saved++;
        }
        if ($saved > 0 && !empty($data['results'][0]['exam_id'])) {
            $examId = (int)$data['results'][0]['exam_id'];
            $examInfo = db_fetch("SELECT name FROM exams WHERE id = ?", [$examId]);
            $examName = $examInfo ? $examInfo['name'] : 'Exam';
            
            foreach ($data['results'] as $result) {
                if (empty($result['student_id'])) continue;
                $studentId = (int)$result['student_id'];
                
                // Notify parent
                notify_parent_of_student($studentId, 'exam_result', "Exam Result Published", "Results for $examName have been published.", get_current_user_id(), 'exams', $examId, '/exams.php?student_id='.$studentId);
                
                // Notify student
                $student = db_fetch("SELECT user_id FROM students WHERE id = ? AND user_id IS NOT NULL", [$studentId]);
                if ($student) {
                    notify_user($student['user_id'], 'exam_result', "Exam Result Published", "Your results for $examName have been published.", get_current_user_id(), 'exams', $examId, '/exams.php?student_id='.$studentId);
                }
            }
        }

        json_response(['success' => true, 'saved' => $saved]);
    }

    // Single exam creation
    if (isset($data['name']) && isset($data['class_id'])) {
        $sql = "INSERT INTO exams (name, class_id, subject, exam_date, start_time, end_time, max_marks, pass_marks, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $data['name'],
            $data['class_id'],
            $data['subject'] ?? null,
            $data['exam_date'],
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
            (int)($data['max_marks'] ?? 100),
            (int)($data['pass_marks'] ?? 33),
            $data['description'] ?? null,
        ];

        $examId = db_insert($sql, $params);
        audit_log('CREATE', 'exams', $examId, null, $data);
        json_response(['message' => 'Exam created', 'id' => $examId], 201);
    }

    // Single result save
    if (isset($data['exam_id']) && isset($data['student_id'])) {
        $examId = (int)$data['exam_id'];
        $studentId = (int)$data['student_id'];
        
        $exam = db_fetch("SELECT max_marks, pass_marks FROM exams WHERE id = ?", [$examId]);
        if (!$exam) json_response(['error' => 'Exam not found'], 404);

        $maxMarks = (float)($exam['max_marks'] ?? 100);
        $passMarks = (float)($exam['pass_marks'] ?? 33);

        $statusInput = strtolower($data['status'] ?? '');
        $marksInput = isset($data['marks_obtained']) && is_string($data['marks_obtained']) ? strtolower($data['marks_obtained']) : '';

        if ($statusInput === 'absent' || $marksInput === 'absent') {
            $marks = null;
            $status = 'absent';
            $grade = 'F';
        } else {
            $marks = (float) ($data['marks_obtained'] ?? 0);
            if ($marks < 0) $marks = 0;
            if ($marks > $maxMarks) $marks = $maxMarks;
            
            $grade = calculate_grade($marks, $maxMarks);
            $status = ($marks >= $passMarks) ? 'pass' : 'fail';
        }

        db_query("INSERT INTO exam_results (exam_id, student_id, marks_obtained, grade, status, entered_by) 
                  VALUES (?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE 
                  marks_obtained = VALUES(marks_obtained), 
                  grade = VALUES(grade), 
                  status = VALUES(status),
                  entered_by = VALUES(entered_by)",
            [$examId, $studentId, $marks, $grade, $status, get_current_user_id()]
        );
        
        $examName = $exam['name'] ?? 'Exam';
        notify_parent_of_student($studentId, 'exam_result', "Result Updated", "Results for $examName have been updated.", get_current_user_id(), 'exams', $examId, '/exams.php?student_id='.$studentId);

        json_response(['success' => true]);
    }

    json_response(['error' => 'Invalid request'], 400);
}

// PUT - Update exam schedule or result
if ($method === 'PUT') {
    $data = get_post_json();
    $id = $data['id'] ?? null;

    if (!$id) {
        json_response(['error' => 'ID required'], 400);
    }

    // Check if it's an exam or result
    $exam = db_fetch("SELECT id FROM exams WHERE id = ?", [$id]);
    if ($exam) {
        require_role(['admin', 'superadmin']);

        $updates = [];
        $params = [];

        foreach (['name', 'class_id', 'subject', 'exam_date', 'start_time', 'end_time', 'max_marks', 'pass_marks', 'description'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            db_query("UPDATE exams SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            audit_log('UPDATE', 'exams', $id, null, $data);
            json_response(['message' => 'Exam updated']);
        }

        json_response(['error' => 'No data to update'], 400);
    }

    $result = db_fetch("SELECT er.*, e.max_marks, e.pass_marks FROM exam_results er LEFT JOIN exams e ON er.exam_id = e.id WHERE er.id = ?", [$id]);
    if ($result) {
        $updates = [];
        $params = [];
        
        $maxMarks = (float)($result['max_marks'] ?? 100);
        $passMarks = (float)($result['pass_marks'] ?? 33);

        if (array_key_exists('marks_obtained', $data) || isset($data['status'])) {
            $statusInput = strtolower($data['status'] ?? $result['status']);
            $marksInput = isset($data['marks_obtained']) && is_string($data['marks_obtained']) ? strtolower($data['marks_obtained']) : '';

            if ($statusInput === 'absent' || $marksInput === 'absent') {
                $updates[] = "marks_obtained = NULL";
                $updates[] = "status = 'absent'";
                $updates[] = "grade = 'F'";
            } else {
                $marks = (float) ($data['marks_obtained'] ?? $result['marks_obtained'] ?? 0);
                if ($marks < 0) $marks = 0;
                if ($marks > $maxMarks) $marks = $maxMarks;
                
                $grade = calculate_grade($marks, $maxMarks);
                $status = ($marks >= $passMarks) ? 'pass' : 'fail';
                
                $updates[] = "marks_obtained = ?";
                $params[] = $marks;
                
                $updates[] = "grade = ?";
                $params[] = $grade;
                
                $updates[] = "status = ?";
                $params[] = $status;
            }
        }
        
        if (isset($data['remarks'])) {
            $updates[] = "remarks = ?";
            $params[] = $data['remarks'];
        }

        if (!empty($updates)) {
            $updates[] = "entered_by = ?";
            $params[] = get_current_user_id();

            $params[] = $id;
            db_query("UPDATE exam_results SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            audit_log('UPDATE', 'exam_results', $id, null, $data);
            json_response(['message' => 'Result updated']);
        }

        json_response(['error' => 'No data to update'], 400);
    }

    json_response(['error' => 'Not found'], 404);
}

// DELETE - Delete exam or result
if ($method === 'DELETE') {
    require_role(['admin', 'superadmin']);

    $id = $_GET['id'] ?? null;
    if (!$id) {
        json_response(['error' => 'ID required'], 400);
    }

    // Check if it's an exam or result
    $exam = db_fetch("SELECT id FROM exams WHERE id = ?", [$id]);
    if ($exam) {
        db_query("DELETE FROM exam_results WHERE exam_id = ?", [$id]);
        db_query("DELETE FROM exams WHERE id = ?", [$id]);
        audit_log('DELETE', 'exams', $id);
        json_response(['message' => 'Exam deleted']);
    }

    $result = db_fetch("SELECT id FROM exam_results WHERE id = ?", [$id]);
    if ($result) {
        db_query("DELETE FROM exam_results WHERE id = ?", [$id]);
        audit_log('DELETE', 'exam_results', $id);
        json_response(['message' => 'Result deleted']);
    }

    json_response(['error' => 'Not found'], 404);
}

json_response(['error' => 'Method not allowed'], 405);
