<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms_service.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $classId = (int) ($_GET['class_id'] ?? 0);
    $date = sanitize($_GET['date'] ?? date('Y-m-d'));

    if (!$classId)
        json_response(['error' => 'class_id required'], 400);

    // Get all students in class
    $students = db_fetchAll("SELECT s.*, COALESCE(a.status, 'not_marked') as attendance_status, a.id as att_id
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id AND a.date = ?
        WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.name", [$date, $classId]);

    $summary = db_fetchAll("SELECT status, COUNT(*) as count FROM attendance WHERE date = ? AND student_id IN (SELECT id FROM students WHERE class_id = ?) GROUP BY status", [$date, $classId]);

    // Monthly report
    if (isset($_GET['monthly']) && $_GET['monthly']) {
        $month = $_GET['month'] ?? date('Y-m');
        $sql = "SELECT s.name, s.admission_no,
                       COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                       COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                       COUNT(*) as total_days,
                       ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*), 2) as percentage
                FROM students s
                LEFT JOIN attendance a ON a.student_id = s.id AND a.date LIKE ?
                WHERE s.class_id = ? AND s.is_active = 1
                GROUP BY s.id
                HAVING total_days > 0
                ORDER BY percentage DESC";
        $monthlyReport = db_fetchAll($sql, ["$month%", $classId]);
        json_response(['students' => $students, 'date' => $date, 'summary' => $summary, 'monthlyReport' => $monthlyReport]);
    }

    // Defaulters list
    if (isset($_GET['defaulters']) && $_GET['defaulters']) {
        $sql = "SELECT s.name, s.admission_no, s.parent_phone,
                       COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                       COUNT(*) as total_days,
                       ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*), 2) as percentage
                FROM students s
                LEFT JOIN attendance a ON a.student_id = s.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                WHERE s.class_id = ? AND s.is_active = 1
                GROUP BY s.id
                HAVING percentage < 75
                ORDER BY percentage ASC";
        $defaulters = db_fetchAll($sql, [$classId]);
        json_response(['students' => $students, 'defaulters' => $defaulters]);
    }

    json_response(['students' => $students, 'date' => $date, 'summary' => $summary]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    $date = sanitize($data['date'] ?? date('Y-m-d'));
    $records = $data['records'] ?? [];
    $sendSMS = $data['send_sms'] ?? true;

    if (empty($records))
        json_response(['error' => 'Records required'], 400);

    $saved = 0;
    $absentStudents = [];

    foreach ($records as $rec) {
        $studentId = (int) $rec['student_id'];
        $status = in_array($rec['status'], ['present', 'absent', 'late', 'excused']) ? $rec['status'] : 'present';
        $subject = $rec['subject'] ?? null;
        $note = $rec['note'] ?? null;

        // Insert or update
        db_query("INSERT INTO attendance (student_id, date, status, subject, note, marked_by, sms_sent) VALUES (?,?,?,?,?,?,0)
                  ON DUPLICATE KEY UPDATE status = VALUES(status), subject = VALUES(subject), note = VALUES(note), marked_by = VALUES(marked_by), sms_sent = 0",
            [$studentId, $date, $status, $subject, $note, get_current_user_id()]
        );

        // Track absent students for SMS
        if ($status === 'absent' && $sendSMS) {
            $absentStudents[] = $studentId;
        }

        $saved++;
    }

    // Send SMS to parents of absent students
    $smsCount = 0;
    if ($sendSMS && !empty($absentStudents)) {
        $smsService = SMSService::getInstance();

        foreach ($absentStudents as $studentId) {
            $student = db_fetch("SELECT name, parent_phone FROM students WHERE id = ? AND parent_phone IS NOT NULL", [$studentId]);
            if ($student) {
                $result = $smsService->notifyAbsence($student['name'], $student['parent_phone'], $date);
                if ($result['success']) {
                    $smsCount++;
                    // Mark SMS as sent
                    db_query("UPDATE attendance SET sms_sent = 1 WHERE student_id = ? AND date = ?", [$studentId, $date]);
                }
            }
        }
    }

    audit_log('ATTENDANCE_MARKED', 'attendance', null, null, ['date' => $date, 'count' => $saved, 'sms_sent' => $smsCount]);

    json_response(['success' => true, 'saved' => $saved, 'sms_sent' => $smsCount]);
}

// Student attendance history
if (preg_match('/\/student\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches) || isset($_GET['student_id'])) {
    $studentId = $matches[1] ?? $_GET['student_id'];

    // Stats
    if (isset($_GET['stats'])) {
        $stats = db_fetch("SELECT 
            COUNT(*) as total_days,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
            ROUND(COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*), 2) as percentage
            FROM attendance WHERE student_id = ?", [$studentId]);
        json_response($stats);
    }

    // History
    $sql = "SELECT a.*, c.name as class_name 
            FROM attendance a 
            LEFT JOIN classes c ON a.class_id = c.id 
            WHERE a.student_id = ? 
            ORDER BY a.date DESC 
            LIMIT 200";
    $history = db_fetchAll($sql, [$studentId]);
    json_response(['history' => $history]);
}

// PUT - Update attendance record
if ($_SERVER['REQUEST_METHOD'] === 'PUT' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update')) {
    $data = get_post_json();
    $id = $data['id'] ?? null;

    if (!$id) {
        json_response(['error' => 'Attendance ID required'], 400);
    }

    $updates = [];
    $params = [];

    foreach (['status', 'subject', 'note'] as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (!empty($updates)) {
        $params[] = $id;
        db_query("UPDATE attendance SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        audit_log('UPDATE', 'attendance', $id, null, $data);
        json_response(['message' => 'Attendance record updated']);
    }

    json_response(['error' => 'No data to update'], 400);
}

json_response(['error' => 'Method not allowed'], 405);
