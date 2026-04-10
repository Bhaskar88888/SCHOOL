<?php
/**
 * Top Header Component
 */
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">☰</button>
        <div class="page-title">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
    </div>
    <div class="topbar-right">
        <div class="search-box">
            <input type="text" placeholder="Search..." id="globalSearch" />
            <span class="search-icon">🔍</span>
        </div>
        <button class="icon-btn" id="notifBtn" onclick="toggleNotifications()">
            🔔
            <span class="notif-badge" id="notifBadge">3</span>
        </button>
        <div class="user-dropdown" onclick="toggleUserMenu()">
            <div class="topbar-avatar">
                <?= strtoupper(substr(get_current_user()['name'], 0, 1)) ?>
            </div>
            <span class="topbar-username"><?= htmlspecialchars(get_current_user()['name']) ?></span>
            <span>▾</span>
            <div class="dropdown-menu" id="userMenu">
                <a href="/profile.php">👤 Profile</a>
                <a href="/api/auth/logout.php">⏻ Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- Notifications Dropdown -->
<div class="notif-panel" id="notifPanel">
    <div class="notif-header">
        <span>Notifications</span>
        <button onclick="toggleNotifications()">✕</button>
    </div>
    <div class="notif-list" id="notifList">
        <div class="notif-loading">Loading...</div>
    </div>
</div>
<div class="notif-overlay" id="notifOverlay" onclick="toggleNotifications()"></div>
