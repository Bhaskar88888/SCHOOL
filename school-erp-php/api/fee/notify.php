<?php
/**
 * Fee Notifications API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notify.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_auth();
require_role(['superadmin', 'admin', 'hr']); // Only admins can send manual fee notices

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

CSRFProtection::verifyToken();
$data = get_post_json();

if (empty($data['student_id'])) {
    json_response(['error' => 'Student ID is required'], 400);
}

$studentId = (int)$data['student_id'];
$feeId = !empty($data['fee_id']) ? (int)$data['fee_id'] : null;
$message = sanitize($data['message'] ?? 'This is a gentle reminder that your fee payment is due. Please clear pending dues at the earliest.');
$channel = sanitize($data['channel'] ?? 'in_app');

// Send notification to the student's parent
notify_parent_of_student(
    $studentId,
    'fee_due',
    'Fee Payment Reminder',
    $message,
    get_current_user_id(),
    'fees',
    $feeId,
    ''
);

// Log to fee_notifications explicitly
db_insert(
    "INSERT INTO fee_notifications (student_id, fee_id, message, sent_by, channel) VALUES (?, ?, ?, ?, ?)",
    [$studentId, $feeId, $message, get_current_user_id(), $channel]
);

json_response(['success' => true, 'message' => 'Notification sent successfully']);
