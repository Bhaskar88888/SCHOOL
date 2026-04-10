<?php
/**
 * Sidebar Navigation Component
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = get_authenticated_user();

$navItems = [
    ['icon' => 'DB', 'label' => 'Dashboard', 'page' => 'dashboard', 'roles' => ['superadmin', 'admin', 'teacher', 'student', 'parent', 'accounts', 'librarian']],
    ['icon' => 'ST', 'label' => 'Students', 'page' => 'students', 'roles' => ['superadmin', 'admin', 'teacher']],
    ['icon' => 'AT', 'label' => 'Attendance', 'page' => 'attendance', 'roles' => ['superadmin', 'admin', 'teacher']],
    ['icon' => 'FE', 'label' => 'Fee', 'page' => 'fee', 'roles' => ['superadmin', 'admin', 'accounts']],
    ['icon' => 'EX', 'label' => 'Exams', 'page' => 'exams', 'roles' => ['superadmin', 'admin', 'teacher']],
    ['icon' => 'HR', 'label' => 'HR / Staff', 'page' => 'hr', 'roles' => ['superadmin', 'admin']],
    ['icon' => 'PY', 'label' => 'Payroll', 'page' => 'payroll', 'roles' => ['superadmin', 'admin', 'accounts']],
    ['icon' => 'LB', 'label' => 'Library', 'page' => 'library', 'roles' => ['superadmin', 'admin', 'librarian', 'teacher']],
    ['icon' => 'HS', 'label' => 'Hostel', 'page' => 'hostel', 'roles' => ['superadmin', 'admin']],
    ['icon' => 'TR', 'label' => 'Transport', 'page' => 'transport', 'roles' => ['superadmin', 'admin']],
    ['icon' => 'CN', 'label' => 'Canteen', 'page' => 'canteen', 'roles' => ['superadmin', 'admin']],
    ['icon' => 'HW', 'label' => 'Homework', 'page' => 'homework', 'roles' => ['superadmin', 'admin', 'teacher', 'student']],
    ['icon' => 'NT', 'label' => 'Notices', 'page' => 'notices', 'roles' => ['superadmin', 'admin', 'teacher', 'student', 'parent']],
    ['icon' => 'RT', 'label' => 'Routine', 'page' => 'routine', 'roles' => ['superadmin', 'admin', 'teacher', 'student']],
    ['icon' => 'LV', 'label' => 'Leave', 'page' => 'leave', 'roles' => ['superadmin', 'admin', 'teacher']],
    ['icon' => 'CP', 'label' => 'Complaints', 'page' => 'complaints', 'roles' => ['superadmin', 'admin', 'teacher', 'student', 'parent']],
    ['icon' => 'AI', 'label' => 'Chatbot', 'page' => 'chatbot', 'roles' => ['superadmin', 'admin', 'teacher', 'student', 'parent', 'accounts', 'librarian']],
    ['icon' => 'RM', 'label' => 'Remarks', 'page' => 'remarks', 'roles' => ['superadmin', 'admin', 'teacher']],
    ['icon' => 'CL', 'label' => 'Classes', 'page' => 'classes', 'roles' => ['superadmin', 'admin']],
    ['icon' => 'NF', 'label' => 'Notifications', 'page' => 'notifications', 'roles' => ['superadmin', 'admin', 'teacher', 'student', 'parent']],
    ['icon' => 'US', 'label' => 'Users', 'page' => 'users', 'roles' => ['superadmin', 'admin', 'hr']],
    ['icon' => 'SS', 'label' => 'Salary Setup', 'page' => 'salary-setup', 'roles' => ['superadmin', 'admin', 'hr']],
    ['icon' => 'SA', 'label' => 'Staff Attend.', 'page' => 'staff-attendance', 'roles' => ['superadmin', 'admin', 'hr']],
    ['icon' => 'AR', 'label' => 'Archive', 'page' => 'archive', 'roles' => ['superadmin', 'admin']],
    ['icon' => 'EX', 'label' => 'Export Data', 'page' => 'export', 'roles' => ['superadmin', 'admin', 'accounts', 'hr', 'teacher']],
    ['icon' => 'AL', 'label' => 'Audit Log', 'page' => 'audit', 'roles' => ['superadmin']],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">SE</div>
        <div class="brand-text">
            <div class="brand-name">School ERP</div>
            <div class="brand-version">v3.0</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item):
            $hasAccess = role_matches($user['role'], $item['roles']);
            if (!$hasAccess) {
                continue;
            }
            $isActive = ($currentPage === $item['page']) ? 'active' : '';
        ?>
        <a href="/<?= $item['page'] ?>.php" class="nav-item <?= $isActive ?>">
            <span class="nav-icon"><?= htmlspecialchars($item['icon']) ?></span>
            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
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
        <a href="/api/auth/logout.php" class="logout-btn" title="Logout">LT</a>
    </div>
</aside>
