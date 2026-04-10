<?php
/**
 * Sidebar Navigation Component
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = get_current_user();

$navItems = [
    ['icon' => '🏠', 'label' => 'Dashboard',   'page' => 'dashboard',   'roles' => ['superadmin','admin','teacher','student','parent','accountant','librarian']],
    ['icon' => '👨‍🎓', 'label' => 'Students',    'page' => 'students',    'roles' => ['superadmin','admin','teacher']],
    ['icon' => '✅', 'label' => 'Attendance',   'page' => 'attendance',  'roles' => ['superadmin','admin','teacher']],
    ['icon' => '💰', 'label' => 'Fee',          'page' => 'fee',         'roles' => ['superadmin','admin','accountant']],
    ['icon' => '📝', 'label' => 'Exams',        'page' => 'exams',       'roles' => ['superadmin','admin','teacher']],
    ['icon' => '👔', 'label' => 'HR / Staff',   'page' => 'hr',          'roles' => ['superadmin','admin']],
    ['icon' => '💵', 'label' => 'Payroll',      'page' => 'payroll',     'roles' => ['superadmin','admin','accountant']],
    ['icon' => '📚', 'label' => 'Library',      'page' => 'library',     'roles' => ['superadmin','admin','librarian','teacher']],
    ['icon' => '🏠', 'label' => 'Hostel',       'page' => 'hostel',      'roles' => ['superadmin','admin']],
    ['icon' => '🚌', 'label' => 'Transport',    'page' => 'transport',   'roles' => ['superadmin','admin']],
    ['icon' => '🍽️', 'label' => 'Canteen',      'page' => 'canteen',     'roles' => ['superadmin','admin']],
    ['icon' => '📋', 'label' => 'Homework',     'page' => 'homework',    'roles' => ['superadmin','admin','teacher','student']],
    ['icon' => '📌', 'label' => 'Notices',      'page' => 'notices',     'roles' => ['superadmin','admin','teacher','student','parent']],
    ['icon' => '🗓️', 'label' => 'Routine',      'page' => 'routine',     'roles' => ['superadmin','admin','teacher','student']],
    ['icon' => '📅', 'label' => 'Leave',        'page' => 'leave',       'roles' => ['superadmin','admin','teacher']],
    ['icon' => '📣', 'label' => 'Complaints',   'page' => 'complaints',  'roles' => ['superadmin','admin','teacher','student','parent']],
    ['icon' => '💬', 'label' => 'Chatbot',      'page' => 'chatbot',     'roles' => ['superadmin','admin','teacher','student','parent','accountant','librarian']],
    ['icon' => '💬', 'label' => 'Remarks',      'page' => 'remarks',     'roles' => ['superadmin','admin','teacher']],
    ['icon' => '🏫', 'label' => 'Classes',      'page' => 'classes',     'roles' => ['superadmin','admin']],
    ['icon' => '🔔', 'label' => 'Notifications','page' => 'notifications','roles' => ['superadmin','admin','teacher','student','parent']],
    ['icon' => '👥', 'label' => 'Users',        'page' => 'users',       'roles' => ['superadmin','admin']],
    ['icon' => '📦', 'label' => 'Archive',      'page' => 'archive',     'roles' => ['superadmin','admin']],
    ['icon' => '📊', 'label' => 'Audit Log',    'page' => 'audit',       'roles' => ['superadmin']],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🎓</div>
        <div class="brand-text">
            <div class="brand-name">School ERP</div>
            <div class="brand-version">v2.0</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item):
            $hasAccess = in_array($user['role'], $item['roles']);
            if (!$hasAccess) continue;
            $isActive = ($currentPage === $item['page']) ? 'active' : '';
        ?>
        <a href="/<?= $item['page'] ?>.php" class="nav-item <?= $isActive ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <span class="nav-label"><?= $item['label'] ?></span>
            <?php if ($isActive): ?>
            <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="/profile.php" class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="user-role"><?= role_label($user['role']) ?></div>
            </div>
        </a>
        <a href="/api/auth/logout.php" class="logout-btn" title="Logout">⏻</a>
    </div>
</aside>
