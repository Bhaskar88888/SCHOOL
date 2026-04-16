<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notify.php';
require_auth();
header('Content-Type: application/json');

$role = normalize_role_name(get_current_role());
$userId = get_current_user_id();

if ($role === 'parent') {
    $children = db_fetchAll(
        "SELECT id, class_id FROM students WHERE is_active = 1 AND parent_user_id = ?",
        [$userId]
    );

    if (empty($children) && db_column_exists('students', 'parent_phone')) {
        $user = db_fetch("SELECT phone FROM users WHERE id = ?", [$userId]);
        if (!empty($user['phone'])) {
            $children = db_fetchAll(
                "SELECT id, class_id FROM students WHERE is_active = 1 AND parent_phone = ?",
                [$user['phone']]
            );
        }
    }

    $childIds = array_map(static function ($child) {
        return (int) $child['id'];
    }, $children);
    $classIds = array_values(array_unique(array_filter(array_map(static function ($child) {
        return (int) ($child['class_id'] ?? 0);
    }, $children))));

    $attendancePercent = 0;
    $pendingFee = 0;
    $homeworkCount = 0;
    $complaintCount = db_table_exists('complaints')
        ? db_count("SELECT COUNT(*) FROM complaints WHERE target_user_id = ?", [$userId])
        : 0;

    if (!empty($childIds)) {
        $placeholders = implode(', ', array_fill(0, count($childIds), '?'));

        $attendance = db_fetch(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present
             FROM attendance
             WHERE student_id IN ($placeholders)",
            $childIds
        );
        $totalAttendance = (int) ($attendance['total'] ?? 0);
        $presentAttendance = (int) ($attendance['present'] ?? 0);
        $attendancePercent = $totalAttendance > 0 ? round(($presentAttendance / $totalAttendance) * 100) : 0;

        $pendingFee = (float) (db_fetch(
            "SELECT COALESCE(SUM(balance_amount), 0) AS total
             FROM fees
             WHERE student_id IN ($placeholders)" . (db_column_exists('fees', 'is_deleted') ? " AND COALESCE(is_deleted, 0) = 0" : "") . " AND balance_amount > 0",
            $childIds
        )['total'] ?? 0);

        if (!empty($classIds) && db_table_exists('homework')) {
            $classPlaceholders = implode(', ', array_fill(0, count($classIds), '?'));
            $homeworkCount = db_count(
                "SELECT COUNT(*) FROM homework WHERE class_id IN ($classPlaceholders)",
                $classIds
            );
        }
    }

    json_response([
        'stats' => [
            'children' => count($children),
            'attendance_percentage' => $attendancePercent,
            'pending_fee' => $pendingFee,
            'homework_count' => (int) $homeworkCount,
            'pending_complaints' => (int) $complaintCount,
            'unread_notifications' => function_exists('get_unread_notification_count') ? get_unread_notification_count($userId) : 0,
        ],
        'charts' => [
            'revenueTrend' => [],
            'attendanceTrend' => [],
            'classDistribution' => [],
            'feeCollection' => ['collected' => 0, 'pending' => $pendingFee],
            'examPerformance' => [],
        ],
        'quickActions' => [
            ['label' => 'Communication Hub', 'icon' => 'Messages', 'url' => '/communication.php'],
            ['label' => 'Homework', 'icon' => 'Homework', 'url' => '/homework.php'],
            ['label' => 'Exams', 'icon' => 'Results', 'url' => '/exams.php'],
        ],
    ]);
}

$statsData = db_fetch(
    "SELECT 
        (SELECT COUNT(*) FROM students WHERE is_active = 1) AS totalStudents,
        (SELECT COUNT(*) FROM users WHERE role NOT IN ('student', 'parent') AND is_active = 1) AS totalStaff,
        (SELECT COALESCE(SUM(amount_paid),0) FROM fees WHERE MONTH(paid_date)=MONTH(NOW()) AND YEAR(paid_date)=YEAR(NOW())) AS feeThisMonth,
        (SELECT COALESCE(SUM(balance_amount),0) FROM fees WHERE balance_amount > 0) AS pendingFee,
        (SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present') AS todayPresent,
        (SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'absent') AS todayAbsent,
        (SELECT COUNT(*) FROM library_books) AS totalBooks,
        (SELECT COUNT(*) FROM complaints WHERE status = 'pending') AS pendingComplaints,
        (SELECT COUNT(*) FROM notices WHERE is_active = 1) AS activeNotices,
        (SELECT COUNT(*) FROM leave_applications WHERE status = 'pending') AS pendingLeaves"
);

$totalStudents     = $statsData['totalStudents'] ?? 0;
$totalStaff        = $statsData['totalStaff'] ?? 0;
$feeThisMonth      = $statsData['feeThisMonth'] ?? 0;
$pendingFee        = $statsData['pendingFee'] ?? 0;
$todayPresent      = $statsData['todayPresent'] ?? 0;
$todayAbsent       = $statsData['todayAbsent'] ?? 0;
$totalBooks        = $statsData['totalBooks'] ?? 0;
$pendingComplaints = $statsData['pendingComplaints'] ?? 0;
$activeNotices     = $statsData['activeNotices'] ?? 0;
$pendingLeaves     = $statsData['pendingLeaves'] ?? 0;

// 6-month revenue trend
$revenueTrend = db_fetchAll(
    "SELECT DATE_FORMAT(paid_date, '%Y-%m') as month, COALESCE(SUM(amount_paid),0) as total 
     FROM fees 
     WHERE paid_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
     GROUP BY DATE_FORMAT(paid_date, '%Y-%m') 
     ORDER BY month"
);

// Attendance trend (last 30 days)
$attendanceTrend = db_fetchAll(
    "SELECT date, 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent
     FROM attendance 
     WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
     GROUP BY date 
     ORDER BY date"
);

// Class-wise student distribution
$classDistribution = db_fetchAll(
    "SELECT c.name, COUNT(s.id) as student_count 
     FROM classes c 
     LEFT JOIN students s ON s.class_id = c.id AND s.is_active = 1 
     GROUP BY c.id, c.name 
     ORDER BY c.name"
);

// Fee collection vs pending
$feeCollection = db_fetchAll(
    "SELECT 
        COALESCE(SUM(CASE WHEN balance_amount = 0 THEN amount_paid ELSE 0 END),0) as collected,
        COALESCE(SUM(balance_amount),0) as pending
     FROM fees"
);

// Exam performance (current month)
$examPerformance = db_fetchAll(
    "SELECT e.name as exam_name, e.subject, 
            AVG(er.marks_obtained) as avg_marks,
            MAX(er.marks_obtained) as max_marks,
            MIN(er.marks_obtained) as min_marks,
            COUNT(CASE WHEN er.status = 'pass' THEN 1 END) as passed,
            COUNT(CASE WHEN er.status = 'fail' THEN 1 END) as failed
     FROM exam_results er 
     LEFT JOIN exams e ON er.exam_id = e.id 
     WHERE e.exam_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY e.id 
     ORDER BY e.exam_date DESC 
     LIMIT 10"
);

// Quick actions based on role
$quickActions = [];
if (in_array($role, ['admin', 'superadmin'])) {
    $quickActions = [
        ['label' => 'Add Student', 'icon' => '👨‍🎓', 'url' => '/students.php?action=add'],
        ['label' => 'Mark Attendance', 'icon' => '✅', 'url' => '/attendance.php'],
        ['label' => 'Collect Fees', 'icon' => '💰', 'url' => '/fee.php'],
        ['label' => 'Schedule Exam', 'icon' => '📝', 'url' => '/exams.php'],
        ['label' => 'Post Notice', 'icon' => '📢', 'url' => '/notices.php'],
    ];
} elseif ($role === 'teacher') {
    $quickActions = [
        ['label' => 'Mark Attendance', 'icon' => '✅', 'url' => '/attendance.php'],
        ['label' => 'Assign Homework', 'icon' => '📚', 'url' => '/homework.php'],
        ['label' => 'Enter Marks', 'icon' => '📝', 'url' => '/exams.php'],
        ['label' => 'Add Remarks', 'icon' => '💬', 'url' => '/remarks.php'],
    ];
} elseif ($role === 'accounts') {
    $quickActions = [
        ['label' => 'Collect Fees', 'icon' => '💰', 'url' => '/fee.php'],
        ['label' => 'View Defaulters', 'icon' => '⚠️', 'url' => '/fee.php?view=defaulters'],
        ['label' => 'Generate Payroll', 'icon' => '💼', 'url' => '/payroll.php'],
        ['label' => 'Export Reports', 'icon' => '📊', 'url' => '/export.php'],
    ];
}

json_response([
    'stats' => [
        'students' => (int) $totalStudents,
        'staff' => (int) $totalStaff,
        'fee_this_month' => (float) $feeThisMonth,
        'pending_fee' => (float) $pendingFee,
        'today_present' => (int) $todayPresent,
        'today_absent' => (int) $todayAbsent,
        'total_books' => (int) $totalBooks,
        'pending_complaints' => (int) $pendingComplaints,
        'active_notices' => (int) $activeNotices,
        'pending_leaves' => (int) $pendingLeaves,
    ],
    'charts' => [
        'revenueTrend' => $revenueTrend,
        'attendanceTrend' => $attendanceTrend,
        'classDistribution' => $classDistribution,
        'feeCollection' => $feeCollection[0] ?? ['collected' => 0, 'pending' => 0],
        'examPerformance' => $examPerformance,
    ],
    'quickActions' => $quickActions,
]);
