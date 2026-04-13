<?php
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

$method = $_SERVER['REQUEST_METHOD'];
$currentRole = normalize_role_name(get_current_role());
$currentUserId = get_current_user_id();
$canReview = role_matches($currentRole, ['superadmin', 'admin', 'hr']);

if ($method === 'GET') {
    // GET leave balance
    if (isset($_GET['balance'])) {
        $user = db_fetch("SELECT casual_leave_balance, earned_leave_balance, sick_leave_balance FROM users WHERE id=?", [$currentUserId]);
        json_response([
            'casual' => (int) ($user['casual_leave_balance'] ?? 0),
            'earned' => (int) ($user['earned_leave_balance'] ?? 0),
            'sick' => (int) ($user['sick_leave_balance'] ?? 0),
        ]);
    }

    $status = trim((string) ($_GET['status'] ?? ''));
    $myOnly = isset($_GET['my']) || !$canReview;

    $where = ['1 = 1'];
    $params = [];

    if ($status !== '') {
        $where[] = 'l.status = ?';
        $params[] = sanitize($status);
    }

    if ($myOnly) {
        $where[] = 'l.applicant_id = ?';
        $params[] = $currentUserId;
    }

    $leaves = db_fetchAll(
        "SELECT l.*, u.name AS applicant_name, a.name AS approved_by_name
         FROM leave_applications l
         LEFT JOIN users u ON l.applicant_id = u.id
         LEFT JOIN users a ON l.approved_by = a.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY l.created_at DESC",
        $params
    );
    json_response($leaves);
}

if ($method === 'POST') {
    $data = get_post_json();
    $leaveType = sanitize($data['leave_type'] ?? 'casual');
    $fromDate = sanitize($data['from_date'] ?? '');
    $toDate = sanitize($data['to_date'] ?? '');
    $reason = sanitize($data['reason'] ?? '');

    if ($fromDate === '' || $toDate === '') {
        json_response(['error' => 'From date and to date are required'], 400);
    }

    $daysCount = 1;
    try {
        $start = new DateTime($fromDate);
        $end = new DateTime($toDate);
        $daysCount = max(1, (int) $start->diff($end)->days + 1);
    } catch (Exception $error) {
        $daysCount = 1;
    }

    $columns = ['applicant_id', 'leave_type', 'from_date', 'to_date', 'reason'];
    $placeholders = ['?', '?', '?', '?', '?'];
    $params = [$currentUserId, $leaveType, $fromDate, $toDate, $reason];

    if (db_column_exists('leave_applications', 'days_count')) {
        $columns[] = 'days_count';
        $placeholders[] = '?';
        $params[] = $daysCount;
    }
    if (db_column_exists('leave_applications', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if (db_column_exists('leave_applications', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $id = db_insert(
        "INSERT INTO leave_applications (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")",
        $params
    );

    audit_log('CREATE', 'leave', $id, null, ['leave_type' => $leaveType, 'from_date' => $fromDate, 'to_date' => $toDate]);
    json_response(['success' => true, 'id' => $id]);
}

if ($method === 'PUT') {
    require_role(['superadmin', 'admin', 'hr']);
    $data = get_post_json();
    $id = (int) ($data['id'] ?? 0);
    $status = sanitize($data['status'] ?? 'pending');

    if ($id <= 0) {
        json_response(['error' => 'Leave ID required'], 400);
    }

    $set = ['status = ?', 'approved_by = ?'];
    $params = [$status, $currentUserId];

    if (db_column_exists('leave_applications', 'review_note')) {
        $set[] = 'review_note = ?';
        $params[] = sanitize($data['note'] ?? '');
    }

    if (db_column_exists('leave_applications', 'updated_at')) {
        $set[] = 'updated_at = NOW()';
    }

    $params[] = $id;
    db_query("UPDATE leave_applications SET " . implode(', ', $set) . " WHERE id = ?", $params);

    // Deduct balance only on first approval
    if ($status === 'approved' && db_column_exists('users', 'casual_leave_balance')) {
        $leave = db_fetch("SELECT * FROM leave_applications WHERE id=?", [$id]);
        $prevStatus = $leave['status'] ?? '';
        if ($prevStatus !== 'approved') {
            $start = new DateTime($leave['from_date']);
            $end = new DateTime($leave['to_date']);
            $days = (int) $start->diff($end)->days + 1;
            $leaveType = strtolower($leave['leave_type'] ?? '');
            $column = match ($leaveType) {
                'casual' => 'casual_leave_balance',
                'earned' => 'earned_leave_balance',
                'sick' => 'sick_leave_balance',
                default => null
            };
            if ($column) {
                db_query("UPDATE users SET $column = GREATEST(0, $column - ?) WHERE id=?", [$days, $leave['applicant_id']]);
            }
        }
    }

    // Disptach Notification to Applicant
    require_once __DIR__ . '/../../includes/notify.php';
    $leaveInfo = db_fetch("SELECT applicant_id FROM leave_applications WHERE id=?", [$id]);
    if ($leaveInfo && $leaveInfo['applicant_id']) {
        notify_user($leaveInfo['applicant_id'], 'leave_update', 'Leave Application ' . ucfirst($status), "Your leave request has been marked as $status.", get_current_user_id(), 'leave', $id, '/hr.php');
    }

    audit_log('UPDATE', 'leave', $id, null, ['status' => $status]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
