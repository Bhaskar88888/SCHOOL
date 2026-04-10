<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);
$pageTitle  = 'Classes Management';
$needsTeachers = true;
require_once __DIR__ . '/includes/data.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div style="font-size:18px;font-weight:700">🏫 Manage Classes</div>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ Create Class</button>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Class Name</th><th>Section</th><th>Class Teacher</th><th>Capacity</th><th>Students Enrolled</th><th>Actions</th></tr></thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">🏫 Create Class</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form onsubmit="submitForm(event)">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Class Name *</label><input type="text" class="form-control" name="name" required placeholder="e.g. Class 10"></div>
                <div class="form-group"><label class="form-label">Section</label><input type="text" class="form-control" name="section" placeholder="e.g. A, B, Science"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Class Teacher</label>
                    <select class="form-control" name="teacher_id">
                        <option value="">None Assigned</option>
                        <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Capacity *</label><input type="number" class="form-control" name="capacity" value="40" required></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Class</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
async function loadClasses() {
    const data = await apiGet('/api/classes/index.php');
    document.getElementById('dataTable').innerHTML = data.map(c => `
        <tr>
            <td><strong>${escHtml(c.name)}</strong></td>
            <td><span class="badge badge-secondary">${escHtml(c.section||'-')}</span></td>
            <td>${escHtml(c.teacher_name||'Unassigned')}</td>
            <td>${c.capacity}</td>
            <td><span class="badge ${c.student_count>=c.capacity?'badge-danger':'badge-success'}">${c.student_count}</span></td>
            <td><button class="btn btn-danger btn-sm" onclick="delClass(${c.id})">🗑️</button></td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No classes found</td></tr>';
}

async function submitForm(e) {
    e.preventDefault();
    const res = await apiPost('/api/classes/index.php', Object.fromEntries(new FormData(e.target)));
    if(res.success){ showToast('Class Created'); closeModal('addModal'); e.target.reset(); loadClasses(); }
    else showToast(res.error||'Error','danger');
}

async function delClass(id) {
    if(!confirm('Delete class? Note: You cannot delete a class if it has students.')) return;
    await fetch(`/api/classes/index.php?id=${id}`, {method:'DELETE'});
    showToast('Class Deleted'); loadClasses();
}

loadClasses();
</script>
</body>
</html>
