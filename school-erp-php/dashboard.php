<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();

$pageTitle  = 'Dashboard';
$role       = normalize_role_name(get_current_role());
$userId     = get_current_user_id();

// Map role → panel file
$panelMap = [
    'superadmin' => 'superadmin_panel.php',
    'admin'      => 'admin_panel.php',
    'teacher'    => 'teacher_panel.php',
    'student'    => 'student_panel.php',
    'parent'     => 'parent_panel.php',
    'accounts'   => 'accounts_panel.php',
    'accountant' => 'accounts_panel.php',
    'librarian'  => 'librarian_panel.php',
    'hr'         => 'hr_panel.php',
    'canteen'    => 'canteen_panel.php',
];

$panelFile = isset($panelMap[$role])
    ? __DIR__ . '/panels/' . $panelMap[$role]
    : __DIR__ . '/panels/admin_panel.php';

if (!file_exists($panelFile)) {
    $panelFile = __DIR__ . '/panels/admin_panel.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        <div class="page-content" style="padding:24px 28px">
            <?php include $panelFile; ?>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
// Notification bell polling (every 30s)
async function pollNotifBadge() {
    try {
        const d = await apiGet('/api/notifications/index.php?count=1');
        const badge = document.getElementById('notifBadge');
        const total = (d.unread || 0) + (d.unread_messages || 0);
        if (badge) {
            badge.textContent = total;
            badge.style.display = total > 0 ? 'flex' : 'none';
        }
    } catch(e) {}
}
pollNotifBadge();
setInterval(pollNotifBadge, 30000);
</script>
</body>
</html>
