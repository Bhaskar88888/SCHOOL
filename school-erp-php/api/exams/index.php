<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $exam = db_fetch("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE e.id = ?", [(int)$_GET['id']]);
        $results = db_fetchAll("SELECT er.*, s.name as student_name, s.roll_number FROM exam_results er LEFT JOIN students s ON er.student_id = s.id WHERE er.exam_id = ?", [(int)$_GET['id']]);
        json_response(['exam' => $exam, 'results' => $results]);
    }
    $classId = (int)($_GET['class_id'] ?? 0);
    $where = $classId ? "WHERE class_id = $classId" : "";
    $exams = db_fetchAll("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id $where ORDER BY e.exam_date DESC");
    json_response($exams);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin','teacher']);
    $data = get_post_json();
    // Check if saving results
    if (isset($data['exam_id']) && isset($data['results'])) {
        foreach ($data['results'] as $r) {
            $grade = '';
            $m = (float)$r['marks'];
            $max = (int)($r['max'] ?? 100);
            $pct = $max > 0 ? ($m / $max) * 100 : 0;
            if ($pct >= 90) $grade = 'A+';
            elseif ($pct >= 80) $grade = 'A';
            elseif ($pct >= 70) $grade = 'B+';
            elseif ($pct >= 60) $grade = 'B';
            elseif ($pct >= 50) $grade = 'C';
            elseif ($pct >= 33) $grade = 'D';
            else $grade = 'F';
            $status = $pct >= 33 ? 'pass' : 'fail';
            db_query("INSERT INTO exam_results (exam_id, student_id, marks_obtained, grade, status, entered_by) VALUES (?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), grade=VALUES(grade), status=VALUES(status)",
                [(int)$data['exam_id'], (int)$r['student_id'], $m, $grade, $status, get_current_user_id()]);
        }
        json_response(['success' => true, 'message' => 'Results saved']);
    }
    // Create exam
    if (empty($data['name'])) json_response(['error' => 'Exam name required'], 400);
    $id = db_insert("INSERT INTO exams (name, class_id, subject, exam_date, start_time, end_time, max_marks, pass_marks, description) VALUES (?,?,?,?,?,?,?,?,?)",
        [sanitize($data['name']), (int)($data['class_id'] ?? 0), sanitize($data['subject'] ?? ''),
         $data['exam_date'] ?? date('Y-m-d'), $data['start_time'] ?? '09:00', $data['end_time'] ?? '12:00',
         (int)($data['max_marks'] ?? 100), (int)($data['pass_marks'] ?? 33), sanitize($data['description'] ?? '')]);
    json_response(['success' => true, 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin','admin']);
    db_query("DELETE FROM exams WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
