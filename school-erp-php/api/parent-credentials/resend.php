<?php
/**
 * Resend Parent Portal Credentials
 * POST /api/parent-credentials/resend.php
 *
 * Body (JSON): { "student_id": 42 }
 *   OR         { "user_id": 17 }
 *
 * Roles: superadmin, admin
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/parent_credentials.php';

require_auth();
require_role(['superadmin', 'admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/../../includes/csrf.php';
CSRFProtection::verifyToken();

$data = get_post_json();

$userId    = (int)($data['user_id']    ?? 0);
$studentId = (int)($data['student_id'] ?? 0);

// Resolve user_id from student if not provided directly
if ($userId <= 0 && $studentId > 0) {
    $student = db_fetch("SELECT parent_user_id FROM students WHERE id = ?", [$studentId]);
    if (!$student || empty($student['parent_user_id'])) {
        json_response(['error' => 'No parent account linked to this student. Register a student with a parent email/phone first.'], 404);
    }
    $userId = (int)$student['parent_user_id'];
}

if ($userId <= 0) {
    json_response(['error' => 'user_id or student_id required'], 400);
}

$success = ParentCredentials::resendCredentials($userId);

if ($success) {
    audit_log('RESEND_CREDENTIALS', 'parent_credentials', $userId, null, ['resent_by' => get_current_user_id()]);
    json_response(['success' => true, 'message' => 'Credentials regenerated and sent successfully.']);
} else {
    json_response(['error' => 'Failed to resend credentials. Check that the user exists and is a parent account.'], 400);
}
