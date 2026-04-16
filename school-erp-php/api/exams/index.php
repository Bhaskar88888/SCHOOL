<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $exam = db_fetch("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE e.id = ?", [(int) $_GET['id']]);
        $results = db_fetchAll("SELECT er.*, s.name as student_name, s.roll_number FROM exam_results er LEFT JOIN students s ON er.student_id = s.id WHERE er.exam_id = ?", [(int) $_GET['id']]);
        
        $role = normalize_role_name(get_current_role());
        $userId = get_current_user_id();
        if ($role === 'student' || $role === 'parent') {
            $allowedStds = [];
            if ($role === 'student' && db_column_exists('students', 'user_id')) {
                $allowedStds = array_column(db_fetchAll("SELECT id FROM students WHERE user_id = ?", [$userId]), 'id');
            } elseif ($role === 'parent' && db_column_exists('students', 'parent_user_id')) {
                $allowedStds = array_column(db_fetchAll("SELECT id FROM students WHERE parent_user_id = ?", [$userId]), 'id');
            }
            $results = array_values(array_filter($results, function($r) use ($allowedStds) {
                return in_array($r['student_id'], $allowedStds);
            }));
        }
        json_response(['exam' => $exam, 'results' => $results]);
    }
    $classId = (int) ($_GET['class_id'] ?? 0);
    if ($classId) {
        $exams = db_fetchAll("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE e.class_id = ? ORDER BY e.exam_date DESC", [$classId]);
    } else {
        $exams = db_fetchAll("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id ORDER BY e.exam_date DESC");
    }
    json_response($exams);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin', 'teacher']);
    $data = get_post_json();
    // Check if saving results
    if (isset($data['exam_id']) && isset($data['results'])) {
        require_once __DIR__ . '/../../includes/helpers.php';
        foreach ($data['results'] as $r) {
            $m = (float) $r['marks'];
            $max = (int) ($r['max'] ?? 100);
            $isAbsent = !empty($r['absent']) || $r['status'] === 'absent';

            $grade = $isAbsent ? 'AB' : calculate_grade($m, $max);
            $pct = (!$isAbsent && $max > 0) ? ($m / $max) * 100 : 0;
            $status = $isAbsent ? 'absent' : ($pct >= 33 ? 'pass' : 'fail');

            $totalMarksCol = db_column_exists('exam_results', 'total_marks') ? ', total_marks' : '';
            $totalMarksVal = db_column_exists('exam_results', 'total_marks') ? ', ?' : '';
            $totalMarksParam = db_column_exists('exam_results', 'total_marks') ? [$max] : [];

            db_query(
                "INSERT INTO exam_results (exam_id, student_id, marks_obtained$totalMarksCol, grade, status, entered_by) VALUES (?,?,?$totalMarksVal,?,?,?)
                  ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained)" . ($totalMarksCol ? ", total_marks=VALUES(total_marks)" : "") . ", grade=VALUES(grade), status=VALUES(status)",
                array_merge(
                    [(int) $data['exam_id'], (int) $r['student_id'], $m],
                    $totalMarksParam,
                    [$grade, $status, get_current_user_id()]
                )
            );
        }
        json_response(['success' => true, 'message' => 'Results saved']);
    }
    // Create exam
    if (empty($data['name']))
        json_response(['error' => 'Exam name required'], 400);
    $id = db_insert(
        "INSERT INTO exams (name, class_id, subject, exam_date, start_time, end_time, max_marks, pass_marks, description) VALUES (?,?,?,?,?,?,?,?,?)",
        [
            sanitize($data['name']),
            (int) ($data['class_id'] ?? 0),
            sanitize($data['subject'] ?? ''),
            $data['exam_date'] ?? date('Y-m-d'),
            $data['start_time'] ?? '09:00',
            $data['end_time'] ?? '12:00',
            (int) ($data['max_marks'] ?? 100),
            (int) ($data['pass_marks'] ?? 33),
            sanitize($data['description'] ?? '')
        ]
    );
    json_response(['success' => true, 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("DELETE FROM exams WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
