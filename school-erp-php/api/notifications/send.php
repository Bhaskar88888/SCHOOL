<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/push_notification.php';

require_auth();
require_role(['superadmin', 'admin', 'teacher']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

CSRFProtection::verifyToken();

$data = get_post_json();
$title = trim((string) ($data['title'] ?? ''));
$body = trim((string) ($data['body'] ?? ''));
$userId = (int) ($data['user_id'] ?? 0);
$role = normalize_role_name($data['role'] ?? '');
$extra = is_array($data['data'] ?? null) ? $data['data'] : [];
$currentRole = normalize_role_name(get_current_role());

if ($title === '' || $body === '') {
    json_response(['error' => 'title and body are required'], 400);
}

if ($userId <= 0 && $role === '') {
    json_response(['error' => 'user_id or role is required'], 400);
}

if ($currentRole === 'teacher' && $role !== '' && !in_array($role, ['student', 'parent'], true)) {
    json_response(['error' => 'Teachers can only send role notifications to students or parents'], 403);
}

$delivery = null;

if ($userId > 0) {
    $targetUser = db_fetch("SELECT id, role FROM users WHERE id = ?", [$userId]);
    if (!$targetUser) {
        json_response(['error' => 'Target user not found'], 404);
    }

    if ($currentRole === 'teacher') {
        $targetRole = normalize_role_name($targetUser['role'] ?? '');
        if (!in_array($targetRole, ['student', 'parent'], true)) {
            json_response(['error' => 'Teachers can only send direct notifications to students or parents'], 403);
        }
    }

    $sent = PushNotification::send($userId, $title, $body, $extra);
    $delivery = [
        'success' => $sent,
        'targeted_users' => 1,
        'delivered_users' => $sent ? 1 : 0,
        'failed_users' => $sent ? 0 : 1,
    ];
} else {
    $delivery = PushNotification::sendToRole($role, $title, $body, $extra);
}

audit_log('SEND_PUSH_NOTIFICATION', 'notifications', $userId > 0 ? $userId : $role, null, [
    'title' => $title,
    'sent_by' => get_current_user_id(),
    'target_role' => $role ?: null,
]);

if (($delivery['targeted_users'] ?? 0) === 0) {
    json_response([
        'success' => false,
        'message' => 'No active recipients matched the notification target',
        'delivery' => $delivery,
    ], 404);
}

if (empty($delivery['success'])) {
    json_response([
        'success' => false,
        'message' => 'Notification could not be delivered to any recipient',
        'delivery' => $delivery,
    ], 422);
}

$message = ($delivery['failed_users'] ?? 0) > 0
    ? sprintf(
        'Notification delivered to %d of %d recipients',
        (int) $delivery['delivered_users'],
        (int) $delivery['targeted_users']
    )
    : 'Notification delivered successfully';

json_response([
    'success' => true,
    'message' => $message,
    'delivery' => $delivery,
]);
