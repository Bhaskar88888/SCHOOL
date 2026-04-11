<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms_service.php';

require_auth();
header('Content-Type: application/json');

function truthy_flag($value)
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function attendance_allowed_student_ids()
{
    $role = normalize_role_name(get_current_role());
    $userId = get_current_user_id();

    if ($role === 'student' && db_column_exists('students', 'user_id')) {
        return array_map('intval', array_column(
            db_fetchAll("SELECT id FROM students WHERE user_id = ? AND is_active = 1", [$userId]),
            'id'
        ));
    }

    if ($role === 'parent') {
        if (db_column_exists('students', 'parent_user_id')) {
            return array_map('intval', array_column(
                db_fetchAll("SELECT id FROM students WHERE parent_user_id = ? AND is_active = 1", [$userId]),
                'id'
            ));
        }

        if (db_column_exists('students', 'parent_phone')) {
            $user = db_fetch("SELECT phone FROM users WHERE id = ?", [$userId]);
            if (!empty($user['phone'])) {
                return array_map('intval', array_column(
                    db_fetchAll("SELECT id FROM students WHERE parent_phone = ? AND is_active = 1", [$user['phone']]),
                    'id'
                ));
            }
        }

        return [];
    }

    return null;
}

function attendance_upsert(array $payload)
{
    $columns = array_keys($payload);
    $placeholders = array_fill(0, count($columns), '?');
    $params = array_values($payload);
    $updates = [];

    foreach ($columns as $column) {
        if (in_array($column, ['student_id', 'date'], true)) {
            continue;
        }
        $updates[] = "$column = VALUES($column)";
    }

    db_query(
        "INSERT INTO attendance (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")
         ON DUPLICATE KEY UPDATE " . implode(', ', $updates),
        $params
    );
}

$method = $_SERVER['REQUEST_METHOD'];
$role = normalize_role_name(get_current_role());
$allowedStudentIds = attendance_allowed_student_ids();

if ($method === 'GET' && (isset($_GET['student_id']) || preg_match('/\/student\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches) || preg_match('/\/student\/(\d+)\/stats$/', $_SERVER['REQUEST_URI'], $statMatches))) {
    $studentId = (int) ($_GET['student_id'] ?? ($matches[1] ?? ($statMatches[1] ?? 0)));
    if ($studentId <= 0) {
        json_response(['error' => 'student_id required'], 400);
    }

    if (is_array($allowedStudentIds) && !in_array($studentId, $allowedStudentIds, true)) {
        json_response(['error' => 'Forbidden'], 403);
    }

    if (isset($_GET['stats']) || !empty($statMatches)) {
        $stats = db_fetch(
            "SELECT COUNT(*) AS total_days,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) AS present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) AS absent_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) AS late_days,
                    COUNT(CASE WHEN status = 'excused' THEN 1 END) AS excused_days
             FROM attendance
             WHERE student_id = ?",
            [$studentId]
        );

        $total = (int) ($stats['total_days'] ?? 0);
        $present = (int) ($stats['present_days'] ?? 0);
        $stats['percentage'] = $total > 0 ? round(($present / $total) * 100, 2) : 0;
        json_response(['stats' => $stats]);
    }

    $history = db_fetchAll(
        "SELECT a.*, c.name AS class_name
         FROM attendance a
         LEFT JOIN classes c ON a.class_id = c.id
         WHERE a.student_id = ?
         ORDER BY a.date DESC
         LIMIT 200",
        [$studentId]
    );
    json_response(['history' => $history]);
}

// GET - Reports summary
if ($method === 'GET' && isset($_GET['reports'])) {
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $dailyCount = db_count("SELECT COUNT(*) FROM attendance WHERE date=?", [$today]);
    $monthlyCount = db_count("SELECT COUNT(*) FROM attendance WHERE date BETWEEN ? AND ?", [$monthStart, $monthEnd]);
    $absentToday = db_count("SELECT COUNT(*) FROM attendance WHERE date=? AND status='absent'", [$today]);
    json_response(['dailyCount' => (int) $dailyCount, 'monthlyCount' => (int) $monthlyCount, 'absentToday' => (int) $absentToday]);
}

// GET - Daily report by class
if ($method === 'GET' && isset($_GET['report_type']) && $_GET['report_type'] === 'daily') {
    require_role(['superadmin', 'admin', 'teacher', 'accounts', 'hr']);
    $date = sanitize($_GET['date'] ?? date('Y-m-d'));
    $records = db_fetchAll(
        "SELECT a.*, s.name as student_name, c.name as class_name, c.id as class_id
         FROM attendance a
         LEFT JOIN students s ON a.student_id=s.id
         LEFT JOIN classes c ON a.class_id=c.id
         WHERE a.date=? ORDER BY c.name, s.name",
        [$date]
    );
    $byClass = [];
    foreach ($records as $r) {
        $cn = $r['class_name'] ?? 'Unknown';
        if (!isset($byClass[$cn])) {
            $byClass[$cn] = ['class_name' => $cn, 'class_id' => $r['class_id'], 'present' => 0, 'absent' => 0, 'late' => 0, 'records' => []];
        }
        $byClass[$cn]['records'][] = $r;
        if ($r['status'] === 'present')
            $byClass[$cn]['present']++;
        elseif ($r['status'] === 'absent')
            $byClass[$cn]['absent']++;
        elseif ($r['status'] === 'late')
            $byClass[$cn]['late']++;
    }
    $total = count($records);
    $present = db_count("SELECT COUNT(*) FROM attendance WHERE date=? AND status='present'", [$date]);
    $absent = db_count("SELECT COUNT(*) FROM attendance WHERE date=? AND status='absent'", [$date]);
    $late = db_count("SELECT COUNT(*) FROM attendance WHERE date=? AND status='late'", [$date]);
    json_response(['date' => $date, 'byClass' => array_values($byClass), 'summary' => ['total' => $total, 'present' => (int) $present, 'absent' => (int) $absent, 'late' => (int) $late]]);
}

if ($method === 'GET' && isset($_GET['defaulters'])) {
    $classId = (int) ($_GET['class_id'] ?? 0);
    $threshold = (float) ($_GET['threshold'] ?? 75);
    if ($classId <= 0) {
        json_response(['error' => 'class_id required'], 400);
    }

    $defaulters = db_fetchAll(
        "SELECT s.id, s.name, s.admission_no, s.parent_phone,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) AS present_days,
                COUNT(*) AS total_days,
                ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS percentage
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         WHERE s.class_id = ? AND s.is_active = 1
         GROUP BY s.id
         HAVING percentage < ?
         ORDER BY percentage ASC, s.name ASC",
        [$classId, $threshold]
    );
    json_response(['defaulters' => $defaulters]);
}

if ($method === 'GET' && isset($_GET['monthly'])) {
    $classId = (int) ($_GET['class_id'] ?? 0);
    $month = sanitize($_GET['month'] ?? date('Y-m'));
    if ($classId <= 0) {
        json_response(['error' => 'class_id required'], 400);
    }

    $report = db_fetchAll(
        "SELECT s.id, s.name, s.admission_no,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) AS present_days,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) AS absent_days,
                COUNT(CASE WHEN a.status = 'late' THEN 1 END) AS late_days,
                COUNT(CASE WHEN a.status = 'excused' THEN 1 END) AS excused_days,
                COUNT(*) AS total_days,
                ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS percentage
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id AND a.date LIKE ?
         WHERE s.class_id = ? AND s.is_active = 1
         GROUP BY s.id
         ORDER BY s.name ASC",
        [$month . '%', $classId]
    );
    json_response(['report' => $report, 'month' => $month]);
}

if ($method === 'GET') {
    $classId = (int) ($_GET['class_id'] ?? 0);
    $date = sanitize($_GET['date'] ?? date('Y-m-d'));
    if ($classId <= 0) {
        json_response(['error' => 'class_id required'], 400);
    }

    $params = [$date, $classId];
    $scope = '';
    if (is_array($allowedStudentIds)) {
        if (!$allowedStudentIds) {
            json_response(['students' => [], 'records' => [], 'summary' => []]);
        }
        $placeholders = implode(', ', array_fill(0, count($allowedStudentIds), '?'));
        $scope = " AND s.id IN ($placeholders)";
        $params = array_merge($params, $allowedStudentIds);
    }

    $students = db_fetchAll(
        "SELECT s.*, COALESCE(a.status, 'not_marked') AS attendance_status, a.id AS attendance_id
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id AND a.date = ?
         WHERE s.class_id = ? AND s.is_active = 1$scope
         ORDER BY s.name ASC",
        $params
    );

    $summary = db_fetchAll(
        "SELECT status, COUNT(*) AS count
         FROM attendance
         WHERE date = ? AND student_id IN (SELECT id FROM students WHERE class_id = ? AND is_active = 1)
         GROUP BY status",
        [$date, $classId]
    );

    $records = array_values(array_filter($students, function ($student) {
        return ($student['attendance_status'] ?? 'not_marked') !== 'not_marked';
    }));

    json_response(['students' => $students, 'records' => $records, 'date' => $date, 'summary' => $summary]);
}

if ($method === 'POST') {
    require_role(['superadmin', 'admin', 'teacher']);
    $data = get_post_json();

    // Single-student mark (Node.js /mark compat)
    if (!isset($data['records'])) {
        $data['records'] = [
            [
                'student_id' => $data['student_id'] ?? $data['studentId'] ?? 0,
                'status' => $data['status'] ?? 'present',
                'subject' => $data['subject'] ?? '',
                'note' => $data['note'] ?? '',
            ]
        ];
    }

    $date = sanitize($data['date'] ?? date('Y-m-d'));
    $classId = (int) ($data['class_id'] ?? 0);
    $records = is_array($data['records'] ?? null) ? $data['records'] : [];
    $sendSms = !isset($data['send_sms']) || truthy_flag($data['send_sms']);

    if (!$records) {
        json_response(['error' => 'Records required'], 400);
    }

    $saved = 0;
    $absentStudents = [];

    foreach ($records as $record) {
        $studentId = (int) ($record['student_id'] ?? 0);
        $status = strtolower((string) ($record['status'] ?? 'present'));
        $status = in_array($status, ['present', 'absent', 'late', 'excused', 'half-day'], true) ? $status : 'present';

        if ($studentId <= 0) {
            continue;
        }

        $payload = db_filter_data_for_table('attendance', [
            'student_id' => $studentId,
            'class_id' => $classId,
            'date' => $date,
            'status' => $status,
            'subject' => sanitize($record['subject'] ?? ''),
            'note' => sanitize($record['note'] ?? ''),
            'remarks' => sanitize($record['note'] ?? ''),
            'marked_by' => get_current_user_id(),
            'teacher_id' => get_current_user_id(),
            'sms_sent' => 0,
        ]);

        attendance_upsert($payload);
        if ($status === 'absent' && $sendSms) {
            $absentStudents[] = $studentId;
        }
        $saved++;
    }

    $smsCount = 0;
    if ($sendSms && $absentStudents) {
        $smsService = SMSService::getInstance();
        foreach ($absentStudents as $studentId) {
            $student = db_fetch("SELECT name, parent_phone FROM students WHERE id = ? AND parent_phone IS NOT NULL", [$studentId]);
            if ($student) {
                $result = $smsService->notifyAbsence($student['name'], $student['parent_phone'], $date);
                if (!empty($result['success'])) {
                    $smsCount++;
                }
            }
        }
    }

    audit_log('ATTENDANCE_MARKED', 'attendance', null, null, ['date' => $date, 'saved' => $saved]);
    json_response(['success' => true, 'saved' => $saved, 'sms_sent' => $smsCount]);
}

if ($method === 'PUT') {
    $data = get_post_json();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Attendance ID required'], 400);
    }

    $updates = [];
    $params = [];
    foreach (['status', 'subject', 'note', 'remarks'] as $field) {
        if (!array_key_exists($field, $data)) {
            continue;
        }
        $updates[] = "$field = ?";
        $params[] = sanitize((string) $data[$field]);
    }

    if (!$updates) {
        json_response(['error' => 'No data to update'], 400);
    }
    $params[] = $id;
    db_query("UPDATE attendance SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    audit_log('UPDATE', 'attendance', $id, null, $data);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
