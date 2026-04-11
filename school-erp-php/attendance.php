<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

require_auth();
$pageTitle = 'Student Attendance';
$currentRole = normalize_role_name(get_current_role());
$isManager = in_array($currentRole, ['superadmin', 'admin', 'teacher']);

$classes = db_fetchAll("SELECT id, name, section FROM classes WHERE is_active = 1 ORDER BY name ASC, section ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .present { background-color: #e6f4ea; color: #1e8e3e; }
        .absent { background-color: #fce8e6; color: #d93025; }
        .late { background-color: #fef7e0; color: #e37400; }
        .excused { background-color: #e8eaed; color: #5f6368; }
        .student-chip {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 50%; font-weight: 600;
            background: var(--ink-border); font-size: 13px;
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <?php if ($isManager): ?>
        <div class="page-tabs">
            <button class="page-tab active" id="tab-mark" onclick="switchTab('mark')">Mark Attendance</button>
            <button class="page-tab" id="tab-view" onclick="switchTab('view')">View Records</button>
            <button class="page-tab" id="tab-defaulters" onclick="switchTab('defaulters')">Defaulters</button>
        </div>

        <div id="panel-mark" class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Mark Attendance</div>
                    <div class="card-sub">Record daily student attendance by class.</div>
                </div>
                <div style="display:flex;gap:10px">
                    <select class="form-control" id="markClass" style="width:150px" onchange="loadMarkList()">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'] . ' ' . $c['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" class="form-control" id="markDate" value="<?= date('Y-m-d') ?>" style="width:160px" onchange="loadMarkList()">
                </div>
            </div>
            
            <div id="markActionBar" style="display:none; padding:10px 20px; border-bottom:1px solid var(--ink-border); justify-content:space-between; align-items:center;">
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-secondary btn-sm" onclick="markAll('present')">Mark All Present</button>
                    <button class="btn btn-secondary btn-sm" onclick="markAll('absent')">Mark All Absent</button>
                </div>
                <button class="btn btn-primary" onclick="submitAttendance()">Save Attendance</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Roll/Adm No</th>
                            <th>Status</th>
                            <th>Note (Optional)</th>
                        </tr>
                    </thead>
                    <tbody id="markTableBody">
                        <tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-text">Select a class to load students.</div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="panel-view" class="card" style="display:none">
            <div class="card-header">
                <div>
                    <div class="card-title">View Attendance Records</div>
                    <div class="card-sub">Check previously submitted attendance for a class.</div>
                </div>
                <div style="display:flex;gap:10px">
                    <select class="form-control" id="viewClass" style="width:150px" onchange="loadViewList()">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'] . ' ' . $c['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" class="form-control" id="viewDate" value="<?= date('Y-m-d') ?>" style="width:160px" onchange="loadViewList()">
                    <button class="btn btn-secondary" onclick="exportCSV()">Export CSV</button>
                </div>
            </div>
            <div class="table-wrap">
                <table id="viewTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission No</th>
                            <th>Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody id="viewTableBody">
                        <tr><td colspan="4"><div class="empty-state"><div class="empty-state-text">Select a class to view records.</div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="panel-defaulters" class="card" style="display:none">
            <div class="card-header">
                <div>
                    <div class="card-title">Defaulters List</div>
                    <div class="card-sub">Students with attendance below 75% in the last 30 days.</div>
                </div>
                <div style="display:flex;gap:10px; align-items:center;">
                    <select class="form-control" id="defaulterClass" style="width:150px" onchange="loadDefaulters()">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'] . ' ' . $c['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:14px;color:var(--ink-4)">Threshold: 75%</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission No</th>
                            <th>Parent Phone</th>
                            <th>Present / Total Days</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody id="defaultersTableBody">
                        <tr><td colspan="5"><div class="empty-state"><div class="empty-state-text">Select a class to generate report.</div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- My Attendance View for Students and Parents -->
        <div id="panel-my" class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">My Attendance</div>
                    <div class="card-sub">View overall attendance stats and daily history.</div>
                </div>
            </div>
            
            <div class="summary-grid" style="padding: 20px;">
                <div class="summary-tile">
                    <div class="summary-kicker">Attendance %</div>
                    <div class="summary-value" id="myPercent">0%</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-kicker">Total Days</div>
                    <div class="summary-value" id="myTotal">0</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-kicker">Present / Absent</div>
                    <div class="summary-value" id="myPresentAbsent">0 / 0</div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="myTableBody">
                        <tr><td colspan="4">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const isManager = <?= $isManager ? 'true' : 'false' ?>;
let activeTab = isManager ? 'mark' : 'my';
let attendanceMap = {};
let studentList = [];

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.page-tab').forEach(b => b.classList.remove('active'));
    document.getElementById(`tab-${tab}`).classList.add('active');
    document.querySelectorAll('[id^="panel-"]').forEach(p => p.style.display = 'none');
    document.getElementById(`panel-${tab}`).style.display = 'block';
    
    if (tab === 'mark') loadMarkList();
    if (tab === 'view') loadViewList();
    if (tab === 'defaulters') loadDefaulters();
}

function renderStatusSelect(studentId) {
    const val = attendanceMap[studentId]?.status || 'present';
    return `
        <select class="form-control" style="width:130px; font-weight:500;" id="status_${studentId}" onchange="updateAttStatus(${studentId}, this.value)">
            <option value="present" ${val==='present'?'selected':''}>Present</option>
            <option value="absent" ${val==='absent'?'selected':''}>Absent</option>
            <option value="late" ${val==='late'?'selected':''}>Late</option>
            <option value="excused" ${val==='excused'?'selected':''}>Excused</option>
        </select>
    `;
}

function updateAttStatus(id, status) {
    if (!attendanceMap[id]) attendanceMap[id] = {};
    attendanceMap[id].status = status;
}

function updateAttNote(id, note) {
    if (!attendanceMap[id]) attendanceMap[id] = {};
    attendanceMap[id].note = note;
}

function markAll(status) {
    studentList.forEach(s => {
        if (!attendanceMap[s.id]) attendanceMap[s.id] = {};
        attendanceMap[s.id].status = status;
        const sel = document.getElementById(`status_${s.id}`);
        if(sel) { sel.value = status; sel.className = `form-control ${status}`; }
    });
}

async function loadMarkList() {
    const classId = document.getElementById('markClass').value;
    const date = document.getElementById('markDate').value;
    const tbody = document.getElementById('markTableBody');
    const bar = document.getElementById('markActionBar');
    
    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-text">Select a class to load students.</div></div></td></tr>';
        bar.style.display = 'none';
        return;
    }

    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;">Loading...</td></tr>';
    
    try {
        const res = await apiGet(`/api/attendance/index.php?class_id=${classId}&date=${date}`);
        studentList = res.students || [];
        attendanceMap = {};
        
        studentList.forEach(s => {
            attendanceMap[s.id] = {
                status: s.attendance_status === 'not_marked' ? 'present' : s.attendance_status,
                note: ''
            };
        });

        if (studentList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-state-text">No active students in this class.</div></div></td></tr>';
            bar.style.display = 'none';
            return;
        }

        bar.style.display = 'flex';
        tbody.innerHTML = studentList.map(s => `
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="student-chip">${escHtml((s.name||'U').charAt(0).toUpperCase())}</div>
                        <div>
                            <div style="font-weight:600">${escHtml(s.name)}</div>
                        </div>
                    </div>
                </td>
                <td><span style="color:var(--ink-4);font-size:13px">${escHtml(s.admission_no || s.roll_number || '-')}</span></td>
                <td>${renderStatusSelect(s.id)}</td>
                <td><input type="text" class="form-control" placeholder="Optional remark" onchange="updateAttNote(${s.id}, this.value)"></td>
            </tr>
        `).join('');

        // Apply colors to selects
        studentList.forEach(s => {
            const sel = document.getElementById(`status_${s.id}`);
            sel.addEventListener('change', function() { this.className = `form-control ${this.value}`; });
            sel.className = `form-control ${sel.value}`;
        });
        
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" style="color:red;padding:20px;">Error loading students</td></tr>`;
    }
}

async function submitAttendance() {
    const classId = document.getElementById('markClass').value;
    const date = document.getElementById('markDate').value;
    if (!classId) return showToast('Select a class first', 'danger');

    const records = Object.keys(attendanceMap).map(id => ({
        student_id: id,
        status: attendanceMap[id].status,
        note: attendanceMap[id].note
    }));

    if (records.length === 0) return showToast('No data to save', 'warning');

    const res = await apiPost('/api/attendance/index.php', {
        class_id: classId,
        date: date,
        records: records,
        send_sms: true
    });

    if (res.success) {
        showToast(`Attendance saved for ${res.saved} students`);
    } else {
        showToast(res.error || 'Failed to save attendance', 'danger');
    }
}

async function loadViewList() {
    if (!isManager) return;
    const classId = document.getElementById('viewClass').value;
    const date = document.getElementById('viewDate').value;
    const tbody = document.getElementById('viewTableBody');
    
    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-state-text">Select a class to view records.</div></div></td></tr>';
        return;
    }

    try {
        const res = await apiGet(`/api/attendance/index.php?class_id=${classId}&date=${date}`);
        const records = res.records || [];
        
        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-state-text">No attendance submitted for this date.</div></div></td></tr>';
            return;
        }

        tbody.innerHTML = records.map(r => `
            <tr>
                <td><strong>${escHtml(r.name)}</strong></td>
                <td>${escHtml(r.admission_no || '-')}</td>
                <td><span class="badge ${r.attendance_status}">${escHtml(r.attendance_status)}</span></td>
                <td>${escHtml(r.note || '-')}</td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" style="color:red;padding:20px;">Error loading records</td></tr>';
    }
}

function exportCSV() {
    const date = document.getElementById('viewDate').value;
    const rows = [...document.querySelectorAll('#viewTable tbody tr')].map(tr =>
        [...tr.querySelectorAll('td')].map(td => td.textContent).join(',')
    );
    if(rows.length === 0 || rows[0].includes("No attendance submitted")) return showToast('No data to export', 'warning');
    const csv = ['Student,Admission No,Status,Note', ...rows].join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `Attendance_${date}.csv`;
    a.click();
}

async function loadDefaulters() {
    if (!isManager) return;
    const classId = document.getElementById('defaulterClass').value;
    const tbody = document.getElementById('defaultersTableBody');
    
    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-text">Select a class to generate report.</div></div></td></tr>';
        return;
    }

    try {
        const res = await apiGet(`/api/attendance/index.php?defaulters=1&class_id=${classId}&threshold=75`);
        const list = res.defaulters || [];
        
        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-text">No defaulters found for this class! 🎉</div></div></td></tr>';
            return;
        }

        tbody.innerHTML = list.map(s => `
            <tr>
                <td><strong>${escHtml(s.name)}</strong></td>
                <td>${escHtml(s.admission_no || '-')}</td>
                <td>${escHtml(s.parent_phone || '-')}</td>
                <td>${s.present_days} / ${s.total_days}</td>
                <td><span class="badge badge-danger">${s.percentage}%</span></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" style="color:red;padding:20px;">Error loading report</td></tr>';
    }
}

async function loadMyAttendance() {
    if (isManager) return;
    try {
        // Find which student_id is tied to this user. We call a known student API endpoint or check stats.
        // Actually api/attendance/index.php?student_id=0 falls back internally? No, need to pass student_id.
        // If parent holds multiple, this gets tricky, but we try with URL param or let API handle it.
        // For simplicity, we just fetch stats & history using the API's implicit current student checking!
        const resStats = await apiGet('/api/attendance/index.php?student_id=0&stats=1'); // Backend enforces constraints
        
        if (resStats.error) {
            document.getElementById('myTableBody').innerHTML = `<tr><td colspan="4" style="color:red;">${escHtml(resStats.error)}</td></tr>`;
            return;
        }
        
        const stats = resStats.stats || {};
        document.getElementById('myPercent').textContent = `${stats.percentage || 0}%`;
        document.getElementById('myTotal').textContent = stats.total_days || 0;
        document.getElementById('myPresentAbsent').textContent = `${stats.present_days || 0} / ${stats.absent_days || 0}`;

        const resHist = await apiGet('/api/attendance/index.php?student_id=0');
        const history = resHist.history || [];
        
        const tbody = document.getElementById('myTableBody');
        if (history.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-state-text">No attendance records found.</div></div></td></tr>';
            return;
        }

        tbody.innerHTML = history.map(h => `
            <tr>
                <td>${new Date(h.date).toLocaleDateString()}</td>
                <td>${escHtml(h.class_name || '-')}</td>
                <td><span class="badge ${h.status}">${escHtml(h.status)}</span></td>
                <td>${escHtml(h.remarks || h.note || '-')}</td>
            </tr>
        `).join('');

    } catch (e) {
        console.error(e);
        document.getElementById('myTableBody').innerHTML = '<tr><td colspan="4" style="color:red;">Error fetching attendance.</td></tr>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (isManager) {
        loadMarkList();
    } else {
        loadMyAttendance();
    }
});
</script>
</body>
</html>
