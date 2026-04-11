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
            <div style="font-size:18px;font-weight:700">💬 Teacher's Remarks</div>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Remark</button>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Student</th><th>Teacher</th><th>Remark</th><th>Type</th><th>Date</th></tr></thead>
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
async function loadRemarks() {
    const data = await apiGet('/api/remarks/index.php');
    document.getElementById('dataTable').innerHTML = data.map(r => `
        <tr>
            <td><strong>${escHtml(r.student_name)}</strong></td>
            <td>${escHtml(r.teacher_name)}</td>
            <td>${escHtml(r.remark)}</td>
            <td><span class="badge ${r.type==='positive'?'badge-success':(r.type==='negative'?'badge-danger':'badge-info')}">${r.type}</span></td>
            <td><div style="font-size:11px;color:var(--text-muted)">${new Date(r.created_at).toLocaleDateString()}</div></td>
        </tr>
    `).join('') || '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">No remarks found</td></tr>';
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
