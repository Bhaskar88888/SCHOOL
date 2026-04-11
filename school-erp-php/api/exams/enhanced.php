<?php
/**
 * Enhanced Exam API with Analytics, Report Cards, Bulk Results
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$currentRole = get_current_role();

// GET - Enhanced endpoints
if ($method === 'GET') {
    // Analytics
    if (isset($_GET['analytics'])) {
        $classId = (int)($_GET['class_id'] ?? 0);
        $where = ['1=1'];
        $params = [];

        if ($classId) {
            $where[] = 'e.class_id = ?';
            $params[] = $classId;
        }

        $whereClause = implode(' AND ', $where);

        $analytics = db_fetchAll("SELECT 
            e.name as exam_name, e.subject, e.exam_date,
            AVG(er.percentage) as avg_percentage,
            MAX(er.percentage) as max_percentage,
            MIN(er.percentage) as min_percentage,
            COUNT(CASE WHEN er.status = 'pass' THEN 1 END) as passed,
            COUNT(CASE WHEN er.status = 'fail' THEN 1 END) as failed,
            COUNT(*) as total_students,
            ROUND(COUNT(CASE WHEN er.status = 'pass' THEN 1 END) * 100.0 / COUNT(*), 2) as pass_rate
            FROM exam_results er 
            LEFT JOIN exams e ON er.exam_id = e.id 
            WHERE $whereClause 
            GROUP BY e.id 
            ORDER BY e.exam_date DESC");

        // Grade distribution
        $gradeDist = db_fetchAll("SELECT grade, COUNT(*) as count FROM exam_results WHERE grade IS NOT NULL GROUP BY grade ORDER BY grade");

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

        $results = db_fetchAll("SELECT er.*, e.name as exam_name, e.subject, e.exam_date, e.max_marks, e.pass_marks 
                               FROM exam_results er 
                               LEFT JOIN exams e ON er.exam_id = e.id 
                               WHERE er.student_id = ? 
                               ORDER BY e.exam_date DESC", [$studentId]);

        json_response(['student' => $student, 'results' => $results]);
    }

    // Student's all results
    if (preg_match('/\/results\/student\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches)) {
        $studentId = $matches[1];
        $results = db_fetchAll("SELECT er.*, e.name as exam_name, e.subject, e.exam_date 
                               FROM exam_results er 
                               LEFT JOIN exams e ON er.exam_id = e.id 
                               WHERE er.student_id = ? 
                               ORDER BY e.exam_date DESC", [$studentId]);

        $summary = db_fetch("SELECT 
            COUNT(*) as total_exams,
            AVG(percentage) as avg_percentage,
            COUNT(CASE WHEN status = 'pass' THEN 1 END) as passed,
            COUNT(CASE WHEN status = 'fail' THEN 1 END) as failed
            FROM exam_results WHERE student_id = ?", [$studentId]);

        json_response(['results' => $results, 'summary' => $summary]);
    }

    // Results for specific exam
    if (preg_match('/\/results\/exam\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches)) {
        $examId = $matches[1];
        $results = db_fetchAll("SELECT er.*, s.name as student_name, s.admission_no 
                               FROM exam_results er 
                               LEFT JOIN students s ON er.student_id = s.id 
                               WHERE er.exam_id = ? 
                               ORDER BY s.name", [$examId]);
        json_response(['results' => $results]);
    }

    // Regular exam list (existing functionality)
    // ... existing code ...
    json_response(['exams' => []]);
}

// POST - Bulk save results
if ($method === 'POST') {
    require_role(['admin', 'superadmin', 'teacher']);
    $data = get_post_json();

    // Bulk results
    if (isset($data['results']) && is_array($data['results'])) {
        $saved = 0;
        foreach ($data['results'] as $result) {
            if (empty($result['exam_id']) || empty($result['student_id']))
                continue;

            $marks = (float) $result['marks_obtained'];
            $totalMarks = (float) ($result['total_marks'] ?? 100);
            $grade = calculate_grade($marks, $totalMarks);
            $status = ($marks >= ($result['pass_marks'] ?? 33)) ? 'pass' : 'fail';

            db_query("INSERT INTO exam_results (exam_id, student_id, marks_obtained, total_marks, grade, status, entered_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), total_marks = VALUES(total_marks), 
                      grade = VALUES(grade), status = VALUES(status)",
                [$result['exam_id'], $result['student_id'], $marks, $totalMarks, $grade, $status, get_current_user_id()]
            );
            $saved++;
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
            $data['max_marks'] ?? 100,
            $data['pass_marks'] ?? 33,
            $data['description'] ?? null,
        ];

        $examId = db_insert($sql, $params);
        audit_log('CREATE', 'exams', $examId, null, $data);
        json_response(['message' => 'Exam created', 'id' => $examId], 201);
    }

    // Single result save
    if (isset($data['exam_id']) && isset($data['student_id'])) {
        $marks = (float) $data['marks_obtained'];
        $totalMarks = (float) ($data['total_marks'] ?? 100);
        $grade = calculate_grade($marks, $totalMarks);
        $status = ($marks >= ($data['pass_marks'] ?? 33)) ? 'pass' : 'fail';

        db_query("INSERT INTO exam_results (exam_id, student_id, marks_obtained, total_marks, grade, status, entered_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), grade = VALUES(grade), status = VALUES(status)",
            [$data['exam_id'], $data['student_id'], $marks, $totalMarks, $grade, $status, get_current_user_id()]
        );

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

    $result = db_fetch("SELECT id FROM exam_results WHERE id = ?", [$id]);
    if ($result) {
        $updates = [];
        $params = [];

        foreach (['marks_obtained', 'total_marks', 'remarks'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            // Recalculate grade if marks changed
            if (isset($data['marks_obtained']) && isset($data['total_marks'])) {
                $grade = calculate_grade($data['marks_obtained'], $data['total_marks']);
                $updates[] = "grade = ?";
                $params[] = $grade;
            }

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
