<?php
/**
 * Sidebar Navigation Component – Minimal Gallery UI
 * SVGs have explicit width/height/fill/stroke to prevent rendering issues
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = get_authenticated_user();

// SVG icon map — all have fill="none" stroke="currentColor" with explicit sizes
function nav_svg($paths, $extra = '') {
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" ' . $extra . '>' . $paths . '</svg>';
}

require_once __DIR__ . '/notify.php';
$unreadCountSidebar = get_unread_notification_count(get_current_user_id());

$icons = [
    'dashboard'        => nav_svg('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'),
    'students'         => nav_svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
    'attendance'       => nav_svg('<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'),
    'fee'              => nav_svg('<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'),
    'exams'            => nav_svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'),
    'hr'               => nav_svg('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'),
    'payroll'          => nav_svg('<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>'),
    'library'          => nav_svg('<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'),
    'hostel'           => nav_svg('<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'),
    'transport'        => nav_svg('<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>'),
    'canteen'          => nav_svg('<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>'),
    'homework'         => nav_svg('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
    'notices'          => nav_svg('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'),
    'routine'          => nav_svg('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'),
    'leave'            => nav_svg('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'),
    'communication'    => nav_svg('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'),
    'chatbot'          => nav_svg('<rect x="2" y="2" width="20" height="20" rx="5"/><line x1="7" y1="10" x2="17" y2="10"/><line x1="7" y1="14" x2="13" y2="14"/>'),
    'remarks'          => nav_svg('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'),
    'classes'          => nav_svg('<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'),
    'users'            => nav_svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
    'salary-setup'     => nav_svg('<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>'),
    'staff-attendance' => nav_svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>'),
    'archive'          => nav_svg('<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/>'),
    'export'           => nav_svg('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'),
    'audit'            => nav_svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'),
];

$navItems = [
    ['key' => 'dashboard',        'label' => 'Dashboard',        'roles' => ['superadmin','admin','teacher','student','parent','accounts','librarian']],
    ['key' => 'students',         'label' => 'Students',         'roles' => ['superadmin','admin','teacher']],
    ['key' => 'attendance',       'label' => 'Attendance',       'roles' => ['superadmin','admin','teacher']],
    ['key' => 'fee',              'label' => 'Fee',              'roles' => ['superadmin','admin','accounts']],
    ['key' => 'exams',            'label' => 'Exams',            'roles' => ['superadmin','admin','teacher']],
    ['key' => 'hr',               'label' => 'HR / Staff',       'roles' => ['superadmin','admin']],
    ['key' => 'payroll',          'label' => 'Payroll',          'roles' => ['superadmin','admin','accounts']],
    ['key' => 'library',          'label' => 'Library',          'roles' => ['superadmin','admin','librarian','teacher']],
    ['key' => 'hostel',           'label' => 'Hostel',           'roles' => ['superadmin','admin']],
    ['key' => 'transport',        'label' => 'Transport',        'roles' => ['superadmin','admin']],
    ['key' => 'canteen',          'label' => 'Canteen',          'roles' => ['superadmin','admin']],
    ['key' => 'homework',         'label' => 'Homework',         'roles' => ['superadmin','admin','teacher','student']],
    ['key' => 'notices',          'label' => 'Notices',          'roles' => ['superadmin','admin','teacher','student','parent']],
    ['key' => 'routine',          'label' => 'Routine',          'roles' => ['superadmin','admin','teacher','student']],
    ['key' => 'leave',            'label' => 'Leave',            'roles' => ['superadmin','admin','teacher']],
    ['key' => 'communication',    'label' => 'Comms Hub',        'roles' => ['superadmin','admin','teacher','student','parent']],
    ['key' => 'chatbot',          'label' => 'AI Chatbot',       'roles' => ['superadmin','admin','teacher','student','parent','accounts','librarian']],
    ['key' => 'remarks',          'label' => 'Remarks',          'roles' => ['superadmin','admin','teacher']],
    ['key' => 'classes',          'label' => 'Classes',          'roles' => ['superadmin','admin']],
    ['key' => 'users',            'label' => 'Users',            'roles' => ['superadmin','admin','hr']],
    ['key' => 'salary-setup',     'label' => 'Salary Setup',     'roles' => ['superadmin','admin','hr']],
    ['key' => 'staff-attendance', 'label' => 'Staff Attend.',    'roles' => ['superadmin','admin','hr']],
    ['key' => 'archive',          'label' => 'Archive',          'roles' => ['superadmin','admin']],
    ['key' => 'export',           'label' => 'Export Data',      'roles' => ['superadmin','admin','accounts','hr','teacher']],
    ['key' => 'audit',            'label' => 'Audit Log',        'roles' => ['superadmin']],
];
?>
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">School ERP</div>
            <div class="brand-version">v3.0</div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav" id="sidebarNav">
        <?php foreach ($navItems as $item):
            $hasAccess = role_matches($user['role'], $item['roles']);
            if (!$hasAccess) continue;
            $isActive  = ($currentPage === $item['key']) ? 'active' : '';
            $svg       = $icons[$item['key']] ?? $icons['dashboard'];
            $href      = BASE_URL . '/' . $item['key'] . '.php';
            $badge     = ($item['key'] === 'communication' && $unreadCountSidebar > 0) ? '<span class="badge badge-danger" style="margin-left:auto">' . $unreadCountSidebar . '</span>' : '';
        ?>
        <a href="<?= $href ?>" class="nav-item <?= $isActive ?>">
            <span class="nav-icon"><?= $svg ?></span>
            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
            <?= $badge ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/profile.php" class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="user-role"><?= role_label($user['role']) ?></div>
            </div>
        </a>
        <a href="<?= BASE_URL ?>/api/auth/logout.php" class="logout-link" title="Logout">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Logout
        </a>
    </div>
</aside>
