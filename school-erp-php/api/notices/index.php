<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = get_current_user_id();
    $role = normalize_role_name(get_current_role());
    $whereExtra = '';
    $extraParams = [];
    if ($role === 'teacher') {
        $whereExtra = " AND (target_roles LIKE '%teacher%' OR target_roles LIKE '%all%' OR target_roles = '' OR target_roles IS NULL)";
    } elseif ($role === 'student') {
        $whereExtra = " AND (target_roles LIKE '%student%' OR target_roles LIKE '%all%' OR target_roles = '' OR target_roles IS NULL)";
    } elseif ($role === 'parent') {
        $whereExtra = " AND (target_roles LIKE '%parent%' OR target_roles LIKE '%all%' OR target_roles = '' OR target_roles IS NULL)";
    }
    if (db_column_exists('notices', 'expiry_date')) {
        $whereExtra .= " AND (n.expiry_date IS NULL OR n.expiry_date >= CURDATE())";
    }
    $notices = db_fetchAll("SELECT n.*, u.name as created_by_name FROM notices n LEFT JOIN users u ON n.created_by = u.id WHERE n.is_active = 1$whereExtra ORDER BY FIELD(n.priority, 'urgent', 'high', 'normal'), n.created_at DESC", $extraParams);
    json_response($notices);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin', 'teacher']);
    $data = get_post_json();
    if (empty($data['title']) || empty($data['content']))
        json_response(['error' => 'Title and content required'], 400);
    // Sanitize title, allow basic formatting in content (strip scripts but keep <b><i><p><ul><li><br>)
    $allowedTags = '<b><i><u><em><strong><p><ul><ol><li><br><h3><h4><blockquote>';
    $safeContent = strip_tags($data['content'], $allowedTags);
    $id = db_insert(
        "INSERT INTO notices (title, content, target_roles, priority, expiry_date, is_active, created_by) VALUES (?,?,?,?,?,1,?)",
        [sanitize($data['title']), $safeContent, sanitize($data['target_roles'] ?? 'all'), sanitize($data['priority'] ?? 'normal'), !empty($data['expiry_date']) ? sanitize($data['expiry_date']) : null, get_current_user_id()]
    );
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("UPDATE notices SET is_active = 0 WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
