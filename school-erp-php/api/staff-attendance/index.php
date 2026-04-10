<?php
/**
 * Staff Attendance API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List staff attendance
if ($method === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (preg_match('/^\d{4}-\d{2}$/', $date)) {
        // Monthly view
        $sql = "SELECT u.id as staff_id, u.name, u.employee_id, u.role,
                       COUNT(CASE WHEN sa.status = 'present' THEN 1 END) as present_days,
                       COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as absent_days,
                       COUNT(*) as total_days
                FROM users u
                LEFT JOIN staff_attendance_enhanced sa ON sa.staff_id = u.id AND sa.date LIKE ?
                WHERE u.is_active = 1 AND u.role != 'student'
                GROUP BY u.id
                ORDER BY u.name";
        $attendance = db_fetchAll($sql, ["$date%"]);
    } else {
        // Daily view
        $sql = "SELECT u.id as staff_id, u.name, u.employee_id, u.role,
                       COALESCE(sa.status, 'not_marked') as status,
                       sa.check_in_time, sa.check_out_time, sa.remarks
                FROM users u
                LEFT JOIN staff_attendance_enhanced sa ON sa.staff_id = u.id AND sa.date = ?
                WHERE u.is_active = 1 AND u.role != 'student'
                ORDER BY u.name";
        $attendance = db_fetchAll($sql, [$date]);
    }
    
    json_response(['attendance' => $attendance, 'date' => $date]);
}

// POST - Mark staff attendance
if ($method === 'POST') {
    $data = get_post_json();
    $date = $data['date'] ?? date('Y-m-d');
    $records = $data['records'] ?? [];
    
    if (empty($records)) {
        json_response(['error' => 'Records required'], 400);
    }
    
    $saved = 0;
    foreach ($records as $rec) {
        $staffId = (int)$rec['staff_id'];
        $status = in_array($rec['status'], ['present', 'absent', 'late', 'half_day', 'on_leave']) ? $rec['status'] : 'present';
        $remarks = $rec['remarks'] ?? null;
        $checkIn = $rec['check_in_time'] ?? ($status === 'present' ? date('H:i:s') : null);
        $checkOut = $rec['check_out_time'] ?? null;
        
        db_query("INSERT INTO staff_attendance_enhanced (staff_id, date, status, check_in_time, check_out_time, remarks, marked_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE status = VALUES(status), check_in_time = VALUES(check_in_time), 
                  check_out_time = VALUES(check_out_time), remarks = VALUES(remarks)",
            [$staffId, $date, $status, $checkIn, $checkOut, $remarks, get_current_user_id()]);
        $saved++;
    }
    
    json_response(['success' => true, 'saved' => $saved]);
}

json_response(['error' => 'Method not allowed'], 405);
