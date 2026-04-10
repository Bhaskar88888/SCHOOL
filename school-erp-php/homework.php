<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle  = 'Homework & Assignments';
$needsClasses = true;
require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homework — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .hw-card { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; }
        .hw-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
        .hw-title { font-size:16px; font-weight:600; color:var(--text-primary); }
        .hw-meta { font-size:12px; color:var(--text-muted); margin-top:4px; display:flex; gap:12px; }
        .hw-content { font-size:14px; line-height:1.6; color:var(--text-secondary); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div class="toolbar-left">
                <select class="form-control" id="classFilter" onchange="loadHW()" style="width:200px">
                    <option value="">All Classes</option>
                    <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Assign Homework</button>
            </div>
        </div>

        <div id="hwContainer"></div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">📋 Assign Homework</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form onsubmit="submitHW(event)">
            <div class="form-group"><label class="form-label">Homework Title *</label><input type="text" class="form-control" name="title" required></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Class *</label>
                    <select class="form-control" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Subject</label><input type="text" class="form-control" name="subject"></div>
            </div>
            <div class="form-group"><label class="form-label">Due Date *</label><input type="date" class="form-control" name="due_date" required min="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label class="form-label">Description / Details *</label><textarea class="form-control" name="description" rows="5" required></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Homework</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
const userRole = '<?= get_authenticated_user()['role'] ?>';
const isTeacherOrAdmin = ['superadmin','admin','teacher'].includes(userRole);

async function loadHW() {
    const classId = document.getElementById('classFilter').value;
    const data = await apiGet(`/api/homework/index.php?class_id=${classId}`);
    const today = new Date().toISOString().split('T')[0];
    
    document.getElementById('hwContainer').innerHTML = data.map(h => {
        const due = new Date(h.due_date);
        const isOverdue = h.due_date < today;
        return `
        <div class="hw-card">
            <div class="hw-header">
                <div>
                    <div class="hw-title">${escHtml(h.title)} <span class="badge ${isOverdue?'badge-danger':'badge-success'}">${isOverdue?'Past Due':'Active'}</span></div>
                    <div class="hw-meta">
                        <span>Class: ${escHtml(h.class_name)}</span>
                        <span>Subject: <strong style="color:var(--info)">${escHtml(h.subject)}</strong></span>
                        <span style="${isOverdue?'color:var(--danger)':''}">Due: ${due.toLocaleDateString()}</span>
                        <span>By: ${escHtml(h.assigned_by_name)}</span>
                    </div>
                </div>
                ${isTeacherOrAdmin ? `<button class="btn btn-danger btn-sm" onclick="delHW(${h.id})">🗑️</button>` : ''}
            </div>
            <hr style="border-color:var(--border);margin-bottom:12px">
            <div class="hw-content">${escHtml(h.description)}</div>
        </div>
    `}).join('') || '<div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-text">No homework assignments found</div></div>';
}

async function submitHW(e) {
    e.preventDefault();
    const res = await apiPost('/api/homework/index.php', Object.fromEntries(new FormData(e.target)));
    if(res.success){ showToast('Assigned!'); closeModal('addModal'); e.target.reset(); loadHW(); }
    else showToast(res.error||'Error','danger');
}

async function delHW(id) {
    if(!confirm('Delete assignment?')) return;
    await fetch(`/api/homework/index.php?id=${id}`,{method:'DELETE'});
    showToast('Deleted'); loadHW();
}
loadHW();
</script>
</body>
</html>
