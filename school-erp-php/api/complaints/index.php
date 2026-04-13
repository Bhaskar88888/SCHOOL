<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/../../includes/notify.php';
    $status = sanitize($_GET['status'] ?? '');
    $category = sanitize($_GET['category'] ?? '');
    
    $role = normalize_role_name(get_current_role());
    $userId = get_current_user_id();

    $whereClauses = [];
    $params = [];

    // Role-based visibility
    if (!in_array($role, ['superadmin', 'admin', 'hr'])) {
        $whereClauses[] = "(c.submitted_by = ? OR (c.target_user_id = ? AND c.is_visible_to_target = 1))";
        $params[] = $userId;
        $params[] = $userId;
    }

    if ($status) {
        $whereClauses[] = "c.status = ?";
        $params[] = $status;
    }
    if ($category) {
        $whereClauses[] = "c.category = ?";
        $params[] = $category;
    }

    $whereStr = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

    $complaints = db_fetchAll("SELECT c.*, u.name as submitted_by_name, a.name as assigned_to_name FROM complaints c LEFT JOIN users u ON c.submitted_by = u.id LEFT JOIN users a ON c.assigned_to = a.id $whereStr ORDER BY c.created_at DESC", $params);
    json_response($complaints);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    if (empty($data['title']))
        json_response(['error' => 'Title required'], 400);

    $role = normalize_role_name(get_current_role());
    $type = 'general';
    $targetUserId = null;
    $assignedToRole = 'superadmin';

    // Role-based complaint routing
    if ($role === 'teacher' && !empty($data['student_id'])) {
        $student = db_fetch("SELECT parent_user_id FROM students WHERE id=?", [(int) $data['student_id']]);
        if ($student && $student['parent_user_id']) {
            $type = 'teacher_to_parent';
            $targetUserId = $student['parent_user_id'];
            $assignedToRole = 'parent';
        }
    } elseif ($role === 'parent' && !empty($data['class_id'])) {
        $class = db_fetch("SELECT class_teacher_id FROM classes WHERE id=?", [(int) $data['class_id']]);
        if ($class && $class['class_teacher_id']) {
            $type = 'parent_to_teacher';
            $targetUserId = $class['class_teacher_id'];
            $assignedToRole = 'teacher';
        }
    } elseif ($role === 'student') {
        $type = 'student_to_admin';
    }

    $id = db_insert(
        "INSERT INTO complaints (title, description, category, priority, submitted_by, type, target_user_id, assigned_to_role, raised_by_role) VALUES (?,?,?,?,?,?,?,?,?)",
        [sanitize($data['title']), sanitize($data['description'] ?? ''), sanitize($data['category'] ?? 'general'), sanitize($data['priority'] ?? 'medium'), get_current_user_id(), $type, $targetUserId, $assignedToRole, $role]
    );
    
    // Dispatch Notifications
    require_once __DIR__ . '/../../includes/notify.php';
    $senderName = htmlspecialchars(get_authenticated_user()['name']);
    
    if ($targetUserId) {
        // Notify the specific target (e.g., Parent or Teacher)
        notify_user($targetUserId, 'complaint_new', "New Complaint/Query", "You received a new message from $senderName. Please check your hub.", get_current_user_id(), 'complaints', $id, '/communication.php');
    }
    
    // Always notify admins about urgent/high complaints or if it's general
    if (in_array(sanitize($data['priority'] ?? ''), ['urgent', 'high']) || !$targetUserId) {
        notify_role('admin', 'complaint_alert', "Complaint Submitted: " . sanitize($data['title']), "Priority: " . sanitize($data['priority'] ?? 'medium') . ". Submitted by $senderName.", get_current_user_id());
    }

    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin', 'admin', 'hr']);
    $data = get_post_json();
    $id = (int) $data['id'];
    $status = sanitize($data['status'] ?? 'pending');
    $resolved = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
    $resNote = sanitize($data['resolution_note'] ?? '');
    
    db_query(
        "UPDATE complaints SET status=?, assigned_to=?, resolved_at=?, resolution_note=? WHERE id=?",
        [$status, (int) ($data['assigned_to'] ?? 0), $resolved, $resNote, $id]
    );
    
    // Notify original submitter on status change
    require_once __DIR__ . '/../../includes/notify.php';
    $complaint = db_fetch("SELECT submitted_by, title FROM complaints WHERE id=?", [$id]);
    if ($complaint) {
        $msg = $status === 'resolved' ? "Complaint resolved: " . $complaint['title'] : "Complaint status updated to $status.";
        notify_user($complaint['submitted_by'], 'complaint_update', "Complaint Status Update", $msg, get_current_user_id(), 'complaints', $id, '/communication.php');
    }

    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
