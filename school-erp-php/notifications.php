<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .notif-card { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius); padding:16px; margin-bottom:12px; display:flex; gap:16px; align-items:flex-start; transition: all 0.2s; }
        .notif-card.unread { border-left: 4px solid var(--accent); background: rgba(79,142,247,0.05); }
        .notif-icon { width:40px; height:40px; border-radius:50%; background:rgba(79,142,247,0.1); color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .notif-content { flex: 1; }
        .notif-title { font-weight:600; font-size:15px; margin-bottom:4px; }
        .notif-body { font-size:14px; color:var(--text-secondary); line-height:1.5; }
        .notif-time { font-size:11px; color:var(--text-muted); margin-top:8px; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar"><div style="font-size:18px;font-weight:700">🔔 Notifications History</div></div>

        <div id="notifContainer" style="max-width:800px"></div>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
async function loadNotifications() {
    const data = await apiGet('/api/notifications/list.php');
    document.getElementById('notifContainer').innerHTML = data.map(n => `
        <div class="notif-card ${!parseInt(n.is_read)?'unread':''}" onclick="markRead(${n.id})">
            <div class="notif-icon">🔔</div>
            <div class="notif-content">
                <div class="notif-title">${escHtml(n.title)}</div>
                <div class="notif-body">${escHtml(n.message)}</div>
                <div class="notif-time">${new Date(n.created_at).toLocaleString()}</div>
            </div>
        </div>
    `).join('') || '<div class="empty-state"><div class="empty-state-icon">🔔</div><div class="empty-state-text">No notifications yet</div></div>';
}

async function markRead(id) {
    await apiPost('/api/notifications/list.php', { action:'mark_read', id });
    loadNotifications();
}
loadNotifications();
</script>
</body>
</html>
