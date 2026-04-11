<?php
/**
 * Top Header Component – Minimal Gallery UI
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$_authUser = get_authenticated_user();
?>
<header class="topbar" id="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <svg viewBox="0 0 24 24">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <span class="page-heading"><?= htmlspecialchars($pageTitle) ?></span>
    </div>

    <div class="topbar-right">
        <div class="search-box">
            <span class="search-icon">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <input type="text" placeholder="Search anything…" id="globalSearch" autocomplete="off"/>
        </div>

        <button class="icon-btn" id="notifBtn" onclick="toggleNotifications()" aria-label="Notifications">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-badge" id="notifBadge" style="display:none">0</span>
        </button>

        <div class="user-dropdown" onclick="toggleUserMenu()" id="userDropdownBtn">
            <div class="topbar-avatar"><?= strtoupper(substr($_authUser['name'], 0, 1)) ?></div>
            <span class="topbar-username"><?= htmlspecialchars(explode(' ', $_authUser['name'])[0]) ?></span>
            <span class="dropdown-chevron">
                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
            <div class="dropdown-menu" id="userMenu">
                <a href="<?= BASE_URL ?>/profile.php">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Profile
                </a>
                <hr class="dropdown-divider">
                <a href="<?= BASE_URL ?>/api/auth/logout.php" style="color:var(--red)">
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Notification panel -->
<div class="notif-panel" id="notifPanel">
    <div class="notif-header">
        <span>Notifications</span>
        <button onclick="toggleNotifications()">✕</button>
    </div>
    <div class="notif-list" id="notifList">
        <div style="padding:24px;text-align:center;color:var(--ink-4);font-size:13px">Loading…</div>
    </div>
</div>
<div class="notif-overlay" id="notifOverlay" onclick="toggleNotifications()"></div>
