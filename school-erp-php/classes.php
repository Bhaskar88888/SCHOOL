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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
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
                    <thead><tr><th>Class Name</th><th>Section</th><th>Class Teacher</th><th>Capacity</th><th>Students</th><th>Subjects</th><th>Actions</th></tr></thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Class Modal -->
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

<!-- Manage Subjects Modal -->
<div class="modal-overlay" id="subjectsModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <div class="modal-title">📚 Manage Subjects — <span id="subjectsClassLabel"></span></div>
            <button class="modal-close" type="button" onclick="closeModal('subjectsModal')">✕</button>
        </div>
        <div id="subjectsList" style="min-height:80px;margin-bottom:16px"></div>
        <form id="subjectAddForm" onsubmit="addSubject(event)" style="border-top:1px solid var(--border);padding-top:16px">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Subject Name *</label>
                    <input type="text" class="form-control" id="newSubjectName" placeholder="e.g. Mathematics" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Assign Teacher</label>
                    <select class="form-control" id="newSubjectTeacher">
                        <option value="">None</option>
                        <?php foreach($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('subjectsModal')">Close</button>
                <button type="submit" class="btn btn-primary">+ Add Subject</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let currentSubjectClassId = null;

async function loadClasses() {
    const data = await apiGet('/api/classes/index.php');
    document.getElementById('dataTable').innerHTML = data.map(c => `
        <tr>
            <td><strong>${escHtml(c.name)}</strong></td>
            <td><span class="badge badge-secondary">${escHtml(c.section||'-')}</span></td>
            <td>${escHtml(c.teacher_name||'Unassigned')}</td>
            <td>${c.capacity}</td>
            <td><span class="badge ${c.student_count>=c.capacity?'badge-danger':'badge-success'}">${c.student_count}</span></td>
            <td><button class="btn btn-secondary btn-sm" onclick="openSubjects(${c.id}, '${escHtml(c.name)}')">📚 Subjects</button></td>
            <td><button class="btn btn-danger btn-sm" onclick="delClass(${c.id})">🗑️</button></td>
        </tr>
    `).join('') || '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted)">No classes found</td></tr>';
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

async function openSubjects(classId, className) {
    currentSubjectClassId = classId;
    document.getElementById('subjectsClassLabel').textContent = className;
    openModal('subjectsModal');
    await refreshSubjects();
}

async function refreshSubjects() {
    const data = await apiGet(`/api/classes/index.php?id=${currentSubjectClassId}`);
    const subjects = data.subjects || [];
    document.getElementById('subjectsList').innerHTML = subjects.length
        ? subjects.map(s => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:var(--surface-container-lowest);border-radius:8px;margin-bottom:8px">
                <div>
                    <strong>${escHtml(s.subject)}</strong>
                    ${s.teacher_name ? `<span style="font-size:12px;color:var(--text-muted);margin-left:8px">👤 ${escHtml(s.teacher_name)}</span>` : ''}
                    <span style="font-size:11px;color:var(--text-muted);margin-left:8px">${s.periods_per_week || 5} periods/wk</span>
                </div>
                <button class="btn btn-danger btn-sm" onclick="removeSubject('${escHtml(s.subject)}')">✕</button>
            </div>`).join('')
        : '<div style="text-align:center;color:var(--text-muted);padding:20px">No subjects assigned yet.</div>';
}

async function addSubject(e) {
    e.preventDefault();
    const subject   = document.getElementById('newSubjectName').value.trim();
    const teacherId = document.getElementById('newSubjectTeacher').value;
    const res = await apiPost('/api/classes/index.php', {
        action: 'add_subject',
        class_id: currentSubjectClassId,
        subject,
        teacher_id: teacherId || 0,
    });
    if (res.success) {
        document.getElementById('newSubjectName').value = '';
        document.getElementById('newSubjectTeacher').value = '';
        await refreshSubjects();
    } else {
        showToast(res.error || 'Failed to add subject', 'danger');
    }
}

async function removeSubject(subject) {
    if (!confirm(`Remove subject "${subject}"?`)) return;
    const res = await apiPost('/api/classes/index.php', {
        action: 'remove_subject',
        class_id: currentSubjectClassId,
        subject,
    });
    if (res.success) await refreshSubjects();
    else showToast(res.error || 'Failed', 'danger');
}

loadClasses();
</script>
</body>
</html>
