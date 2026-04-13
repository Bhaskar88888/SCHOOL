<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Student Remarks';
$needsStudents = true;
require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remarks — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div class="toolbar-left" style="display:flex;gap:15px;align-items:center;">
                <div style="font-size:18px;font-weight:700">💬 Teacher's Remarks</div>
                <select class="form-control" id="typeFilter" onchange="loadRemarks()" style="width:150px">
                    <option value="">All Types</option>
                    <option value="positive">Positive</option>
                    <option value="negative">Negative</option>
                    <option value="general">General</option>
                </select>
            </div>
            <?php if(in_array(get_authenticated_user()['role'], ['superadmin','admin','teacher'])): ?>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Remark</button>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Student</th><th>Teacher</th><th>Remark</th><th>Type</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">💬 Add Remark</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form onsubmit="submitForm(event)">
            <div class="form-group"><label class="form-label">Select Student *</label>
                <select class="form-control" name="student_id" required>
                    <option value="">Choose...</option>
                    <?php foreach($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['class_name']?:'-') ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Type</label>
                <select class="form-control" name="type"><option value="general">General</option><option value="positive" selected>Positive</option><option value="negative">Negative</option></select>
            </div>
            <div class="form-group"><label class="form-label">Remark / Feedback *</label><textarea class="form-control" name="remark" rows="4" required></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Remark</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const userRole = '<?= get_authenticated_user()['role'] ?>';
const isAdmin = ['superadmin','admin'].includes(userRole);

async function loadRemarks() {
    const type = document.getElementById('typeFilter').value;
    const url = type ? `/api/remarks/index.php?type=${encodeURIComponent(type)}` : '/api/remarks/index.php';
    const data = await apiGet(url);
    
    document.getElementById('dataTable').innerHTML = data.map(r => `
        <tr>
            <td><strong>${escHtml(r.student_name)}</strong></td>
            <td>${escHtml(r.teacher_name)}</td>
            <td>${escHtml(r.remark)}</td>
            <td><span class="badge ${r.type==='positive'?'badge-success':(r.type==='negative'?'badge-danger':'badge-info')}">${r.type}</span></td>
            <td><div style="font-size:11px;color:var(--text-muted)">${new Date(r.created_at).toLocaleDateString()}</div></td>
            <td>${isAdmin ? `<button class="btn btn-danger btn-sm" onclick="delRemark(${r.id})">🗑️</button>` : ''}</td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No remarks found</td></tr>';
}

async function delRemark(id) {
    if(!confirm('Delete remark?')) return;
    await fetch(`/api/remarks/index.php?id=${id}`, {method:'DELETE'});
    showToast('Deleted'); loadRemarks();
}

async function submitForm(e) {
    e.preventDefault();
    const res = await apiPost('/api/remarks/index.php', Object.fromEntries(new FormData(e.target)));
    if(res.success){ showToast('Remark added'); closeModal('addModal'); e.target.reset(); loadRemarks(); }
    else showToast(res.error||'Error','danger');
}
loadRemarks();
</script>
</body>
</html>
