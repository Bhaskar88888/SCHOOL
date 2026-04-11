<?php
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
require_role(['superadmin', 'admin', 'hr', 'accounts']);
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $date = trim((string) ($_GET['date'] ?? date('Y-m-d')));

    if (preg_match('/^\d{4}-\d{2}$/', $date)) {
        $records = db_fetchAll(
            "SELECT u.id AS staff_id, u.name, u.employee_id, u.role, u.department, u.designation,
                    COUNT(CASE WHEN sa.status IN ('present', 'late', 'half_day', 'half-day') THEN 1 END) AS worked_days,
                    COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) AS absent_days,
                    COUNT(*) AS total_days
             FROM users u
             LEFT JOIN staff_attendance_enhanced sa ON sa.staff_id = u.id AND sa.date LIKE ?
             WHERE u.is_active = 1 AND u.role != 'student'
             GROUP BY u.id
             ORDER BY u.name ASC",
            [$date . '%']
        );

        json_response(['data' => $records, 'date' => $date]);
    }

    $records = db_fetchAll(
        "SELECT u.id AS staff_id, u.name, u.employee_id, u.role, u.department, u.designation,
                COALESCE(sa.status, 'not_marked') AS status,
                sa.check_in_time, sa.check_out_time, sa.remarks
         FROM users u
         LEFT JOIN staff_attendance_enhanced sa ON sa.staff_id = u.id AND sa.date = ?
         WHERE u.is_active = 1 AND u.role != 'student'
         ORDER BY u.name ASC",
        [$date]
    );

    json_response(['data' => $records, 'date' => $date]);
}

if ($method === 'POST') {
    $data = get_post_json();
    $date = sanitize($data['date'] ?? date('Y-m-d'));
    $records = is_array($data['records'] ?? null) ? $data['records'] : [];

    if (!$records) {
        json_response(['error' => 'Attendance records are required'], 400);
    }

    $saved = 0;
    foreach ($records as $record) {
        $staffId = (int) ($record['staff_id'] ?? 0);
        $status = str_replace('-', '_', strtolower((string) ($record['status'] ?? 'present')));
        $status = in_array($status, ['present', 'absent', 'late', 'half_day', 'on_leave'], true) ? $status : 'present';

        if ($staffId <= 0) {
            continue;
        }

        db_query(
            "INSERT INTO staff_attendance_enhanced (staff_id, date, status, check_in_time, check_out_time, remarks, marked_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), check_in_time = VALUES(check_in_time),
             check_out_time = VALUES(check_out_time), remarks = VALUES(remarks), marked_by = VALUES(marked_by)",
            [
                $staffId,
                $date,
                $status,
                $record['check_in_time'] ?? null,
                $record['check_out_time'] ?? null,
                sanitize($record['remarks'] ?? ''),
                get_current_user_id()
            ]
        );
        $saved++;
    }

    audit_log('ATTENDANCE_MARKED', 'staff_attendance', null, null, ['date' => $date, 'saved' => $saved]);
    json_response(['success' => true, 'saved' => $saved]);
}

json_response(['error' => 'Method not allowed'], 405);
