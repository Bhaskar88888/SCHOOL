<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

$totalStudents = db_count("SELECT COUNT(*) FROM students WHERE is_active = 1");
$totalStaff = db_count("SELECT COUNT(*) FROM users WHERE role != 'student' AND is_active = 1");
$feeThisMonth = db_count("SELECT COALESCE(SUM(amount_paid),0) FROM fees WHERE MONTH(paid_date)=MONTH(NOW()) AND YEAR(paid_date)=YEAR(NOW())");
$pendingFee = db_count("SELECT COALESCE(SUM(balance_amount),0) FROM fees WHERE balance_amount > 0");
$todayPresent = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'");
$todayAbsent = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'absent'");
$totalBooks = db_count("SELECT COUNT(*) FROM library_books");
$pendingComplaints = db_count("SELECT COUNT(*) FROM complaints WHERE status = 'pending'");
$activeNotices = db_count("SELECT COUNT(*) FROM notices WHERE is_active = 1");
$pendingLeaves = db_count("SELECT COUNT(*) FROM leave_applications WHERE status = 'pending'");

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
$role = get_current_role();
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
