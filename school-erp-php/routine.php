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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
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
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Schedule</button>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Day</th><th>Subject</th><th>Teacher</th><th>Time</th><th>Room</th><th>Actions</th></tr></thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
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

<script src="/assets/js/main.js"></script>
<script>
async function loadRoutine() {
    const classId = document.getElementById('classFilter').value;
    if(!classId){ document.getElementById('dataTable').innerHTML='<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">Please select a class</td></tr>'; return; }
    const data = await apiGet(`/api/routine/index.php?class_id=${classId}`);
    
    document.getElementById('dataTable').innerHTML = data.map(r => `
        <tr>
            <td><strong>${r.day}</strong></td>
            <td><span class="badge badge-info">${escHtml(r.subject)}</span></td>
            <td>${escHtml(r.teacher_name||'N/A')}</td>
            <td>${r.start_time?.slice(0,5)} - ${r.end_time?.slice(0,5)}</td>
            <td>${escHtml(r.room||'-')}</td>
            <td><button class="btn btn-danger btn-sm" onclick="delRoutine(${r.id})">🗑️</button></td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No schedule found for this class</td></tr>';
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
