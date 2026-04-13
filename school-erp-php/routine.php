<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle  = 'Class Routine / Timetable';
$needsClasses = true;
$needsTeachers = true;
require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routine — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .view-toggle { display: flex; background: var(--bg-secondary); border-radius: 8px; padding: 4px; }
        .view-btn { border: none; background: transparent; padding: 6px 12px; font-size: 13px; border-radius: 6px; cursor: pointer; color: var(--text-muted); font-weight: 500; }
        .view-btn.active { background: #fff; color: var(--text-primary); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .grid-day { background: #fff; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
        .grid-day-head { background: var(--bg-secondary); padding: 12px 16px; font-weight: 600; border-bottom: 1px solid var(--border); }
        .grid-periods { display: flex; overflow-x: auto; padding: 16px; gap: 12px; }
        .grid-period { min-width: 180px; border: 1px solid var(--border); border-radius: 6px; padding: 12px; position: relative; }
        .grid-period-time { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; font-weight: 500; }
        .grid-period-subj { font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
        .grid-period-meta { font-size: 12px; color: var(--text-secondary); }
        .grid-period-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 4px; }
        .grid-period-actions button { border: none; background: transparent; cursor: pointer; font-size: 14px; opacity: 0.5; }
        .grid-period-actions button:hover { opacity: 1; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div class="toolbar-left">
                <select class="form-control" id="classFilter" onchange="loadRoutine()" style="width:200px">
                    <option value="">Select a Class...</option>
                    <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-right" style="display:flex;gap:15px;align-items:center;">
                <div class="view-toggle">
                    <button class="view-btn active" onclick="setView('table')" id="btn-table">Table</button>
                    <button class="view-btn" onclick="setView('grid')" id="btn-grid">Grid</button>
                </div>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Schedule</button>
            </div>
        </div>

        <div class="card" id="tableView">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Day</th><th>Subject</th><th>Teacher</th><th>Time</th><th>Room</th><th>Actions</th></tr></thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
        
        <div id="gridView" style="display:none;"></div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">📅 Add to Schedule</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form onsubmit="submitForm(event)">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Class *</label>
                    <select class="form-control" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Day *</label>
                    <select class="form-control" name="day" required>
                        <option value="Monday">Monday</option><option value="Tuesday">Tuesday</option><option value="Wednesday">Wednesday</option><option value="Thursday">Thursday</option><option value="Friday">Friday</option><option value="Saturday">Saturday</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Subject *</label><input type="text" class="form-control" name="subject" required></div>
                <div class="form-group"><label class="form-label">Teacher</label>
                    <select class="form-control" name="teacher_id">
                        <option value="">None</option>
                        <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Start Time</label><input type="time" class="form-control" name="start_time" value="09:00"></div>
                <div class="form-group"><label class="form-label">End Time</label><input type="time" class="form-control" name="end_time" value="09:45"></div>
            </div>
            <div class="form-group"><label class="form-label">Room No.</label><input type="text" class="form-control" name="room"></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">✏️ Edit Schedule</div><button class="modal-close" onclick="closeModal('editModal')">✕</button></div>
        <form onsubmit="submitEdit(event)" id="editForm">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="class_id" id="edit_class_id">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Day *</label>
                    <select class="form-control" name="day" id="edit_day" required>
                        <option value="Monday">Monday</option><option value="Tuesday">Tuesday</option><option value="Wednesday">Wednesday</option><option value="Thursday">Thursday</option><option value="Friday">Friday</option><option value="Saturday">Saturday</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Subject *</label><input type="text" class="form-control" name="subject" id="edit_subject" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Teacher</label>
                    <select class="form-control" name="teacher_id" id="edit_teacher_id">
                        <option value="">None</option>
                        <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Room No.</label><input type="text" class="form-control" name="room" id="edit_room"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Start Time</label><input type="time" class="form-control" name="start_time" id="edit_start_time"></div>
                <div class="form-group"><label class="form-label">End Time</label><input type="time" class="form-control" name="end_time" id="edit_end_time"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let currentData = [];
let currentView = 'table';

function setView(view) {
    currentView = view;
    document.getElementById('btn-table').classList.toggle('active', view==='table');
    document.getElementById('btn-grid').classList.toggle('active', view==='grid');
    document.getElementById('tableView').style.display = view==='table' ? 'block' : 'none';
    document.getElementById('gridView').style.display = view==='grid' ? 'block' : 'none';
    renderData();
}
async function loadRoutine() {
    const classId = document.getElementById('classFilter').value;
    if(!classId){ 
        document.getElementById('dataTable').innerHTML='<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">Please select a class</td></tr>'; 
        document.getElementById('gridView').innerHTML='<div class="empty-state"><div class="empty-state-text">Please select a class</div></div>';
        return; 
    }
    currentData = await apiGet(`/api/routine/index.php?class_id=${classId}`);
    renderData();
}

function renderData() {
    if (currentView === 'table') {
        document.getElementById('dataTable').innerHTML = currentData.map(r => `
            <tr>
                <td><strong>${r.day}</strong></td>
                <td><span class="badge badge-info">${escHtml(r.subject)}</span></td>
                <td>${escHtml(r.teacher_name||'N/A')}</td>
                <td>${r.start_time?.slice(0,5)} - ${r.end_time?.slice(0,5)}</td>
                <td>${escHtml(r.room||'-')}</td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick='editRoutine(${JSON.stringify(r).replace(/'/g, "&apos;")})'>✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="delRoutine(${r.id})">🗑️</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No schedule found for this class</td></tr>';
    } else {
        const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        let html = '';
        let hasData = false;
        for (const day of days) {
            const periods = currentData.filter(r => r.day === day);
            if (periods.length === 0) continue;
            hasData = true;
            html += `<div class="grid-day"><div class="grid-day-head">${day}</div><div class="grid-periods">`;
            html += periods.map(r => `
                <div class="grid-period">
                    <div class="grid-period-actions">
                        <button onclick='editRoutine(${JSON.stringify(r).replace(/'/g, "&apos;")})' title="Edit">✏️</button>
                        <button onclick="delRoutine(${r.id})" title="Delete">🗑️</button>
                    </div>
                    <div class="grid-period-time">🕗 ${r.start_time?.slice(0,5)} - ${r.end_time?.slice(0,5)}</div>
                    <div class="grid-period-subj">${escHtml(r.subject)}</div>
                    <div class="grid-period-meta">👨‍🏫 ${escHtml(r.teacher_name||'TBD')}</div>
                    <div class="grid-period-meta">🚪 ${escHtml(r.room||'TBD')}</div>
                </div>`).join('');
            html += `</div></div>`;
        }
        document.getElementById('gridView').innerHTML = html || '<div class="empty-state"><div class="empty-state-text">No schedule found for this class</div></div>';
    }
}

function editRoutine(r) {
    document.getElementById('edit_id').value = r.id;
    document.getElementById('edit_class_id').value = r.class_id;
    document.getElementById('edit_day').value = r.day;
    document.getElementById('edit_subject').value = r.subject;
    document.getElementById('edit_teacher_id').value = r.teacher_id || '';
    document.getElementById('edit_room').value = r.room || '';
    document.getElementById('edit_start_time').value = r.start_time || '';
    document.getElementById('edit_end_time').value = r.end_time || '';
    openModal('editModal');
}

async function submitEdit(e) {
    e.preventDefault();
    const res = await apiPost('/api/routine/index.php', Object.fromEntries(new FormData(e.target)), 'PUT');
    if(res.success){ showToast('Updated'); closeModal('editModal'); loadRoutine(); }
    else showToast(res.error||'Error','danger');
}

async function submitForm(e) {
    e.preventDefault();
    const res = await apiPost('/api/routine/index.php', Object.fromEntries(new FormData(e.target)));
    if(res.success){ showToast('Schedule Created'); closeModal('addModal'); e.target.reset(); loadRoutine(); }
    else showToast(res.error||'Error','danger');
}

async function delRoutine(id) {
    if(!confirm('Delete schedule?')) return;
    await fetch(`/api/routine/index.php?id=${id}`, {method:'DELETE'});
    showToast('Deleted'); loadRoutine();
}

loadRoutine();
</script>
</body>
</html>
