<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notify.php';

require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

ensure_complaints_schema();

if (!db_table_exists('complaints')) {
    json_response(['error' => 'Complaints module is not available. Run the full schema setup first.'], 503);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handle_complaints_list();
}

if ($method === 'POST') {
    handle_complaints_create(get_post_json());
}

if ($method === 'PUT') {
    handle_complaints_update(get_post_json());
}

json_response(['error' => 'Method not allowed'], 405);

function ensure_complaints_schema()
{
    static $ensured = false;

    if ($ensured || !db_table_exists('complaints')) {
        return;
    }

    $statements = [
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'general' AFTER category",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS target_user_id INT DEFAULT NULL AFTER type",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS student_id INT DEFAULT NULL AFTER target_user_id",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS class_id INT DEFAULT NULL AFTER student_id",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS raised_by_role VARCHAR(50) DEFAULT NULL AFTER submitted_by",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS assigned_to_role VARCHAR(50) DEFAULT NULL AFTER assigned_to",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS resolution_note TEXT DEFAULT NULL AFTER resolved_at",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS is_visible_to_target TINYINT(1) DEFAULT 1 AFTER resolution_note",
    ];

    foreach ($statements as $sql) {
        db_query($sql);
    }

    $ensured = true;
}

function complaint_class_teacher_column()
{
    if (db_column_exists('classes', 'teacher_id')) {
        return 'teacher_id';
    }

    if (db_column_exists('classes', 'class_teacher_id')) {
        return 'class_teacher_id';
    }

    return null;
}

function complaint_insert(array $payload)
{
    $payload = db_filter_data_for_table('complaints', $payload);
    $columns = array_keys($payload);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));

    return db_insert(
        "INSERT INTO complaints (" . implode(', ', $columns) . ") VALUES ($placeholders)",
        array_values($payload)
    );
}

function complaint_update($id, array $payload)
{
    $payload = db_filter_data_for_table('complaints', $payload);
    if (empty($payload)) {
        return;
    }

    $setParts = [];
    $params = [];
    foreach ($payload as $column => $value) {
        $setParts[] = "$column = ?";
        $params[] = $value;
    }
    $params[] = $id;

    db_query("UPDATE complaints SET " . implode(', ', $setParts) . " WHERE id = ?", $params);
}

function handle_complaints_list()
{
    $status = sanitize($_GET['status'] ?? '');
    $category = sanitize($_GET['category'] ?? '');
    $inboxOnly = isset($_GET['inbox']) && $_GET['inbox'] === '1';

    $role = normalize_role_name(get_current_role());
    $userId = get_current_user_id();

    $whereClauses = ['1 = 1'];
    $params = [];

    if ($inboxOnly) {
        $whereClauses[] = 'c.target_user_id = ?';
        $params[] = $userId;
    } elseif (!in_array($role, ['superadmin', 'admin', 'hr'], true)) {
        $targetClause = db_column_exists('complaints', 'is_visible_to_target')
            ? '(c.target_user_id = ? AND COALESCE(c.is_visible_to_target, 1) = 1)'
            : 'c.target_user_id = ?';

        $whereClauses[] = "(c.submitted_by = ? OR $targetClause)";
        $params[] = $userId;
        $params[] = $userId;
    }

    if ($status !== '') {
        $whereClauses[] = 'c.status = ?';
        $params[] = $status;
    }

    if ($category !== '') {
        $whereClauses[] = 'c.category = ?';
        $params[] = $category;
    }

    $complaints = db_fetchAll(
        "SELECT c.*,
                submitter.name AS submitted_by_name,
                assignee.name AS assigned_to_name,
                target.name AS target_user_name,
                student.name AS student_name,
                class_ref.name AS class_name
         FROM complaints c
         LEFT JOIN users submitter ON c.submitted_by = submitter.id
         LEFT JOIN users assignee ON c.assigned_to = assignee.id
         LEFT JOIN users target ON c.target_user_id = target.id
         LEFT JOIN students student ON c.student_id = student.id
         LEFT JOIN classes class_ref ON c.class_id = class_ref.id
         WHERE " . implode(' AND ', $whereClauses) . "
         ORDER BY c.created_at DESC",
        $params
    );

    json_response($complaints);
}

function handle_complaints_create(array $data)
{
    if (empty($data['title'])) {
        json_response(['error' => 'Title required'], 400);
    }

    $role = normalize_role_name(get_current_role());
    $type = 'general';
    $targetUserId = null;
    $assignedToRole = 'admin';
    $studentId = !empty($data['student_id']) ? (int) $data['student_id'] : null;
    $classId = !empty($data['class_id']) ? (int) $data['class_id'] : null;

    if ($role === 'teacher' && $studentId) {
        $student = db_fetch("SELECT parent_user_id FROM students WHERE id = ?", [$studentId]);
        if ($student && !empty($student['parent_user_id'])) {
            $type = 'teacher_to_parent';
            $targetUserId = (int) $student['parent_user_id'];
            $assignedToRole = 'parent';
        }
    } elseif ($role === 'parent' && $classId) {
        $teacherColumn = complaint_class_teacher_column();
        if ($teacherColumn) {
            $class = db_fetch("SELECT $teacherColumn AS teacher_user_id FROM classes WHERE id = ?", [$classId]);
            if ($class && !empty($class['teacher_user_id'])) {
                $type = 'parent_to_teacher';
                $targetUserId = (int) $class['teacher_user_id'];
                $assignedToRole = 'teacher';
            }
        }
    } elseif ($role === 'student') {
        $type = 'student_to_admin';
    }

    $id = complaint_insert([
        'title' => sanitize($data['title']),
        'description' => sanitize($data['description'] ?? ''),
        'category' => sanitize($data['category'] ?? 'general'),
        'priority' => sanitize($data['priority'] ?? 'medium'),
        'submitted_by' => get_current_user_id(),
        'type' => $type,
        'target_user_id' => $targetUserId,
        'student_id' => $studentId,
        'class_id' => $classId,
        'assigned_to_role' => $assignedToRole,
        'raised_by_role' => $role,
        'status' => 'pending',
        'is_visible_to_target' => $targetUserId ? 1 : 0,
    ]);

    $senderName = get_authenticated_user()['name'] ?? 'A user';

    if ($targetUserId) {
        notify_user(
            $targetUserId,
            'complaint_new',
            'New Complaint/Query',
            "You received a new message from $senderName. Please check your hub.",
            get_current_user_id(),
            'complaints',
            $id,
            '/communication.php'
        );
    }

    if (in_array(sanitize($data['priority'] ?? ''), ['urgent', 'high'], true) || !$targetUserId) {
        notify_role(
            'admin',
            'complaint_alert',
            'Complaint Submitted: ' . sanitize($data['title']),
            'Priority: ' . sanitize($data['priority'] ?? 'medium') . ". Submitted by $senderName.",
            get_current_user_id()
        );
        notify_role(
            'superadmin',
            'complaint_alert',
            'Complaint Submitted: ' . sanitize($data['title']),
            'Priority: ' . sanitize($data['priority'] ?? 'medium') . ". Submitted by $senderName.",
            get_current_user_id()
        );
    }

    json_response([
        'success' => true,
        'id' => $id,
        'routed_to' => $targetUserId ? $assignedToRole : 'admin',
        'fallback_to_admin' => !$targetUserId,
    ]);
}

function handle_complaints_update(array $data)
{
    require_role(['superadmin', 'admin', 'hr']);

    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Complaint ID required'], 400);
    }

    $complaint = db_fetch("SELECT * FROM complaints WHERE id = ?", [$id]);
    if (!$complaint) {
        json_response(['error' => 'Complaint not found'], 404);
    }

    $status = sanitize($data['status'] ?? 'pending');
    $assignedTo = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
    $resolutionNote = sanitize($data['resolution_note'] ?? '');

    $updateData = [
        'status' => $status,
        'assigned_to' => $assignedTo,
        'resolved_at' => $status === 'resolved' ? date('Y-m-d H:i:s') : null,
        'resolution_note' => $resolutionNote,
    ];

    if ($assignedTo) {
        $assignedUser = db_fetch("SELECT role, name FROM users WHERE id = ?", [$assignedTo]);
        if ($assignedUser) {
            $updateData['assigned_to_role'] = normalize_role_name($assignedUser['role']);
        }
    }

    complaint_update($id, $updateData);

    if ($assignedTo) {
        notify_user(
            $assignedTo,
            'complaint_assigned',
            'Complaint Assigned to You',
            'You have been assigned complaint: ' . ($complaint['title'] ?? 'Untitled complaint'),
            get_current_user_id(),
            'complaints',
            $id,
            '/communication.php'
        );
    }

    if (!empty($complaint['submitted_by'])) {
        $message = $status === 'resolved'
            ? 'Complaint resolved: ' . ($complaint['title'] ?? 'Untitled complaint')
            : "Complaint status updated to $status.";

        notify_user(
            (int) $complaint['submitted_by'],
            'complaint_update',
            'Complaint Status Update',
            $message,
            get_current_user_id(),
            'complaints',
            $id,
            '/communication.php'
        );
    }

    json_response(['success' => true]);
}
