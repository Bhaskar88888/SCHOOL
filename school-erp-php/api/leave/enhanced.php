<?php
/**
 * Leave Enhanced API - Balance, My, Approve
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms_service.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$userId = get_current_user_id();
$role = get_current_role();

// GET - Leave balance
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'balance') {
    $user = db_fetch("SELECT casual_leave_balance, earned_leave_balance, sick_leave_balance FROM users WHERE id = ?", [$userId]);
    
    $balance = [
        'casual' => $user['casual_leave_balance'] ?? 12,
        'earned' => $user['earned_leave_balance'] ?? 15,
        'sick' => $user['sick_leave_balance'] ?? 10,
        'total' => ($user['casual_leave_balance'] ?? 12) + ($user['earned_leave_balance'] ?? 15) + ($user['sick_leave_balance'] ?? 10),
    ];
    
    json_response(['balance' => $balance]);
}

// GET - My leave requests
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'my') {
    $leaves = db_fetchAll("SELECT * FROM leave_applications WHERE applicant_id = ? ORDER BY created_at DESC", [$userId]);
    json_response(['leaves' => $leaves]);
}

// PUT - Approve/Reject leave
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'approve') {
    require_role(['admin', 'superadmin', 'hr']);
    $data = get_post_json();
    
    if (empty($data['id']) || empty($data['status'])) {
        json_response(['error' => 'id and status required'], 400);
    }
    
    if (!in_array($data['status'], ['approved', 'rejected'])) {
        json_response(['error' => 'Status must be approved or rejected'], 400);
    }
    
    $leave = db_fetch("SELECT * FROM leave_applications WHERE id = ?", [$data['id']]);
    if (!$leave) {
        json_response(['error' => 'Leave request not found'], 404);
    }
    
    db_query("UPDATE leave_applications SET status = ?, approved_by = ?, review_note = ? WHERE id = ?",
        [$data['status'], $userId, $data['note'] ?? null, $data['id']]);
    
    // Deduct leave balance if approved
    if ($data['status'] === 'approved') {
        $days = $leave['days_count'] ?? 1;
        $leaveType = $leave['leave_type'] ?? 'sick';
        $balanceColumn = $leaveType === 'casual' ? 'casual_leave_balance' : ($leaveType === 'earned' ? 'earned_leave_balance' : 'sick_leave_balance');
        
        db_query("UPDATE users SET $balanceColumn = GREATEST($balanceColumn - ?, 0) WHERE id = ?", [$days, $leave['applicant_id']]);
        
        // Send SMS notification
        $staff = db_fetch("SELECT name, phone FROM users WHERE id = ?", [$leave['applicant_id']]);
        if ($staff && $staff['phone']) {
            $smsService = SMSService::getInstance();
            $smsService->leaveStatus($staff['name'], $staff['phone'], 'approved');
        }
    }
    
    audit_log('LEAVE_APPROVED', 'leave', $data['id'], $leave, $data);
    json_response(['message' => "Leave {$data['status']}"]);
}

// Include regular leave API
require_once __DIR__ . '/index.php';
