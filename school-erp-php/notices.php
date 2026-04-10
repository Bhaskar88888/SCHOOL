<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle  = 'Notices & Announcements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .notice-card { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; position:relative; overflow:hidden; }
        .notice-card::before { content:''; position:absolute; top:0;left:0;bottom:0;width:4px;background:var(--accent); }
        .notice-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
        .notice-title { font-size:16px; font-weight:600; color:var(--text-primary); }
        .notice-meta { font-size:11px; color:var(--text-muted); margin-top:4px; display:flex; gap:12px; }
        .notice-content { font-size:14px; line-height:1.6; color:var(--text-secondary); white-space:pre-wrap; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div style="font-size:18px;font-weight:700">📌 Notice Board</div>
            <?php if(in_array(get_current_user()['role'], ['superadmin','admin','teacher'])): ?>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ Publish Notice</button>
            <?php endif; ?>
        </div>

        <div id="noticeContainer"></div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">📌 Publish Notice</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form onsubmit="submitNotice(event)">
            <div class="form-group"><label class="form-label">Notice Title *</label><input type="text" class="form-control" name="title" required></div>
            <div class="form-group"><label class="form-label">Visible To</label>
                <select class="form-control" name="target_roles">
                    <option value="all">Everyone</option><option value="teachers">Teachers Only</option><option value="students">Students Only</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Content / Message *</label><textarea class="form-control" name="content" rows="6" required></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Publish</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
const userRole = '<?= get_current_user()['role'] ?>';
const isAdmin = ['superadmin','admin'].includes(userRole);

async function loadNotices() {
    const data = await apiGet('/api/notices/index.php');
    document.getElementById('noticeContainer').innerHTML = data.map(n => `
        <div class="notice-card">
            <div class="notice-header">
                <div>
                    <div class="notice-title">${escHtml(n.title)}</div>
                    <div class="notice-meta"><span>📅 ${new Date(n.created_at).toLocaleDateString()}</span><span>🗣️ Posted by: ${escHtml(n.created_by_name||'Admin')}</span><span>👥 Target: ${escHtml(n.target_roles)}</span></div>
                </div>
                ${isAdmin ? `<button class="btn btn-danger btn-sm" onclick="delNotice(${n.id})">🗑️</button>` : ''}
            </div>
            <div class="notice-content">${escHtml(n.content)}</div>
        </div>
    `).join('') || '<div class="empty-state"><div class="empty-state-icon">📌</div><div class="empty-state-text">No active notices</div></div>';
}

async function submitNotice(e) {
    e.preventDefault();
    const res = await apiPost('/api/notices/index.php', Object.fromEntries(new FormData(e.target)));
    if(res.success){ showToast('Published!'); closeModal('addModal'); e.target.reset(); loadNotices(); }
    else showToast(res.error||'Error','danger');
}

async function delNotice(id) {
    if(!confirm('Delete notice?')) return;
    await fetch(`/api/notices/index.php?id=${id}`,{method:'DELETE'});
    showToast('Deleted'); loadNotices();
}
loadNotices();
</script>
</body>
</html>
