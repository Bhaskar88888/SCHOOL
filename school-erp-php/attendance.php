<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Attendance';
$classes   = db_fetchAll("SELECT id, name FROM classes ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .att-card { display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:8px; }
        .att-name { font-weight:500; }
        .att-btns { display:flex;gap:8px; }
        .att-btn { padding:6px 16px;border:1px solid var(--border);border-radius:20px;cursor:pointer;font-size:13px;background:var(--bg-input);color:var(--text-secondary);transition:all 0.15s; }
        .att-btn.present.sel { background:rgba(63,185,80,0.2);color:var(--success);border-color:var(--success); }
        .att-btn.absent.sel  { background:rgba(248,81,73,0.2);color:var(--danger);border-color:var(--danger); }
        .att-btn.late.sel    { background:rgba(210,153,34,0.2);color:var(--warning);border-color:var(--warning); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-toolbar">
            <div class="toolbar-left">
                <select class="form-control" id="classSelect" style="width:200px">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" class="form-control" id="dateSelect" value="<?= date('Y-m-d') ?>" style="width:160px">
                <button class="btn btn-secondary" onclick="loadAttendance()">Load Students</button>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="markAll('present')" id="markAllBtn" style="display:none">✅ Mark All Present</button>
                <button class="btn btn-success" onclick="saveAttendance()" id="saveBtn" style="display:none">💾 Save Attendance</button>
            </div>
        </div>

        <!-- Summary Bar -->
        <div style="display:flex;gap:12px;margin-bottom:20px" id="summaryBar" style="display:none">
            <div class="card" style="flex:1;padding:12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:var(--success)" id="presentCount">0</div>
                <div style="font-size:12px;color:var(--text-muted)">Present</div>
            </div>
            <div class="card" style="flex:1;padding:12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:var(--danger)" id="absentCount">0</div>
                <div style="font-size:12px;color:var(--text-muted)">Absent</div>
            </div>
            <div class="card" style="flex:1;padding:12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:var(--warning)" id="lateCount">0</div>
                <div style="font-size:12px;color:var(--text-muted)">Late</div>
            </div>
            <div class="card" style="flex:1;padding:12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:var(--text-muted)" id="totalCount">0</div>
                <div style="font-size:12px;color:var(--text-muted)">Total</div>
            </div>
        </div>

        <div class="card">
            <div id="emptyState" class="empty-state"><div class="empty-state-icon">✅</div><div class="empty-state-text">Select a class and date to mark attendance</div></div>
            <div id="studentList" style="display:none"></div>
        </div>
    </div>
</div>

<button class="chatbot-btn" onclick="toggleChatbot()" title="AI Assistant">🤖</button>
<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-head"><span class="chatbot-head-icon">🤖</span><div><div class="chatbot-head-title">ERP Assistant</div></div><button class="chatbot-head-close" onclick="toggleChatbot()">✕</button></div>
    <div class="chatbot-body" id="chatBody"></div>
    <div class="chatbot-footer"><input type="text" id="chatInput" placeholder="Ask about attendance..."/><button class="chatbot-send" onclick="sendChatMessage()">➤</button></div>
</div>

<script src="/assets/js/main.js"></script>
<script>
let attendanceData = {};

async function loadAttendance() {
    const classId = document.getElementById('classSelect').value;
    const date    = document.getElementById('dateSelect').value;
    if (!classId) { showToast('Please select a class', 'warning'); return; }

    const data = await apiGet(`/api/attendance/index.php?class_id=${classId}&date=${date}`);
    attendanceData = {};

    const list = document.getElementById('studentList');
    list.innerHTML = data.students.map(s => {
        attendanceData[s.id] = s.attendance_status === 'not_marked' ? 'present' : s.attendance_status;
        return `
            <div class="att-card" id="attCard${s.id}">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="user-avatar" style="width:34px;height:34px;font-size:13px">${s.name.charAt(0)}</div>
                    <div><div class="att-name">${escHtml(s.name)}</div><div style="font-size:11px;color:var(--text-muted)">Roll: ${escHtml(s.roll_number||'-')}</div></div>
                </div>
                <div class="att-btns">
                    <button class="att-btn present ${attendanceData[s.id]==='present'?'sel':''}" onclick="setStatus(${s.id},'present',this)">✅ Present</button>
                    <button class="att-btn absent ${attendanceData[s.id]==='absent'?'sel':''}" onclick="setStatus(${s.id},'absent',this)">❌ Absent</button>
                    <button class="att-btn late ${attendanceData[s.id]==='late'?'sel':''}" onclick="setStatus(${s.id},'late',this)">⏰ Late</button>
                </div>
            </div>
        `;
    }).join('');

    document.getElementById('emptyState').style.display = 'none';
    list.style.display = 'block';
    document.getElementById('markAllBtn').style.display = 'inline-flex';
    document.getElementById('saveBtn').style.display = 'inline-flex';
    document.getElementById('summaryBar').style.display = 'flex';
    updateSummary();
}

function setStatus(id, status, btn) {
    attendanceData[id] = status;
    const card = document.getElementById('attCard'+id);
    card.querySelectorAll('.att-btn').forEach(b => b.classList.remove('sel'));
    btn.classList.add('sel');
    updateSummary();
}

function markAll(status) {
    Object.keys(attendanceData).forEach(id => {
        attendanceData[id] = status;
        const card = document.getElementById('attCard'+id);
        if (card) {
            card.querySelectorAll('.att-btn').forEach(b => b.classList.remove('sel'));
            card.querySelector(`.att-btn.${status}`)?.classList.add('sel');
        }
    });
    updateSummary();
}

function updateSummary() {
    const vals = Object.values(attendanceData);
    document.getElementById('presentCount').textContent = vals.filter(v=>v==='present').length;
    document.getElementById('absentCount').textContent  = vals.filter(v=>v==='absent').length;
    document.getElementById('lateCount').textContent    = vals.filter(v=>v==='late').length;
    document.getElementById('totalCount').textContent   = vals.length;
}

async function saveAttendance() {
    const classId = document.getElementById('classSelect').value;
    const date    = document.getElementById('dateSelect').value;
    const records = Object.entries(attendanceData).map(([id, status]) => ({ student_id: +id, status }));
    const res = await apiPost('/api/attendance/index.php', { date, class_id: +classId, records });
    if (res.success) showToast(`Attendance saved for ${res.saved} students!`);
    else showToast(res.error || 'Failed to save', 'danger');
}
</script>
</body>
</html>
