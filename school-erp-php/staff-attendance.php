<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin', 'hr']);
$pageTitle = 'Staff Attendance';
require_once __DIR__ . '/includes/data.php';
$needsClasses = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-toolbar">
            <div style="font-size:18px;font-weight:700">👔 Staff Attendance</div>
            <div class="toolbar-right" style="display:flex;gap:10px;align-items:center">
                <input type="date" class="form-control" id="attendanceDate" value="<?= date('Y-m-d') ?>" onchange="loadAttendance()" style="width:180px">
                <button class="btn btn-primary" onclick="saveAttendance()">💾 Save All</button>
            </div>
        </div>

        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
            <div class="stat-card" style="--stat-color:#3fb950">
                <div class="stat-icon" style="--stat-color:#3fb950">✅</div>
                <div class="stat-info">
                    <div class="stat-value" id="countPresent">-</div>
                    <div class="stat-label">Present</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#f85149">
                <div class="stat-icon" style="--stat-color:#f85149">❌</div>
                <div class="stat-info">
                    <div class="stat-value" id="countAbsent">-</div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#d29922">
                <div class="stat-icon" style="--stat-color:#d29922">⏰</div>
                <div class="stat-info">
                    <div class="stat-value" id="countLate">-</div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#58a6ff">
                <div class="stat-icon" style="--stat-color:#58a6ff">🏖️</div>
                <div class="stat-info">
                    <div class="stat-value" id="countLeave">-</div>
                    <div class="stat-label">On Leave</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Mark Staff Attendance — <span id="displayDate"><?= date('d M Y') ?></span></div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-secondary btn-sm" onclick="markAll('present')">✅ All Present</button>
                    <button class="btn btn-secondary btn-sm" onclick="markAll('absent')">❌ All Absent</button>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="staffBody">
                        <tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">👔</div><div class="empty-state-text">Loading staff...</div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
let staffList = [];
let attendanceMap = {};

async function loadAttendance() {
    const date = document.getElementById('attendanceDate').value;
    document.getElementById('displayDate').textContent = new Date(date).toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});

    try {
        const [staffData, attData] = await Promise.all([
            apiGet('/api/staff-attendance/index.php?action=staff'),
            apiGet(`/api/staff-attendance/index.php?date=${date}`)
        ]);
        staffList = Array.isArray(staffData) ? staffData : (staffData.data || []);
        attendanceMap = {};
        if (Array.isArray(attData)) attData.forEach(a => { attendanceMap[a.user_id] = a; });

        renderTable();
    } catch(e) {
        document.getElementById('staffBody').innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">⚠️</div><div class="empty-state-text">Error loading data</div></div></td></tr>';
    }
}

function renderTable() {
    if (!staffList.length) {
        document.getElementById('staffBody').innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">👔</div><div class="empty-state-text">No staff found</div></div></td></tr>';
        return;
    }

    document.getElementById('staffBody').innerHTML = staffList.map(s => {
        const existing = attendanceMap[s.id] || {};
        const status = existing.status || 'present';
        const statuses = ['present','absent','late','leave','half_day'];
        return `
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="user-avatar" style="width:32px;height:32px;font-size:12px">${escHtml(s.name.charAt(0).toUpperCase())}</div>
                    <div>
                        <div style="font-weight:600">${escHtml(s.name)}</div>
                        <div style="font-size:11px;color:var(--text-muted)">${escHtml(s.employee_id || s.email)}</div>
                    </div>
                </div>
            </td>
            <td><span class="badge badge-info">${escHtml(s.role)}</span></td>
            <td>${escHtml(s.department || '-')}</td>
            <td>
                <select class="form-control att-status" data-id="${s.id}" style="width:140px" onchange="updateCount()">
                    ${statuses.map(st => `<option value="${st}" ${status === st ? 'selected' : ''}>${st.charAt(0).toUpperCase()+st.slice(1).replace('_',' ')}</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="text" class="form-control att-remark" data-id="${s.id}" value="${escHtml(existing.remarks || '')}" placeholder="Optional remark" style="width:200px">
            </td>
        </tr>`;
    }).join('');
    updateCount();
}

function markAll(status) {
    document.querySelectorAll('.att-status').forEach(sel => sel.value = status);
    updateCount();
}

function updateCount() {
    const counts = {present:0, absent:0, late:0, leave:0};
    document.querySelectorAll('.att-status').forEach(s => {
        const v = s.value;
        if (v === 'present') counts.present++;
        else if (v === 'absent') counts.absent++;
        else if (v === 'late') counts.late++;
        else if (v === 'leave' || v === 'half_day') counts.leave++;
    });
    document.getElementById('countPresent').textContent = counts.present;
    document.getElementById('countAbsent').textContent = counts.absent;
    document.getElementById('countLate').textContent = counts.late;
    document.getElementById('countLeave').textContent = counts.leave;
}

async function saveAttendance() {
    const date = document.getElementById('attendanceDate').value;
    const records = staffList.map(s => ({
        user_id: s.id,
        date: date,
        status: document.querySelector(`.att-status[data-id="${s.id}"]`)?.value || 'present',
        remarks: document.querySelector(`.att-remark[data-id="${s.id}"]`)?.value || ''
    }));
    const res = await apiPost('/api/staff-attendance/index.php', {records, date});
    if (res.success) showToast('Attendance saved!');
    else showToast(res.error || 'Error saving', 'danger');
}

loadAttendance();
</script>
</body>
</html>
