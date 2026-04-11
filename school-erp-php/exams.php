<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle  = 'Exams & Results';
$needsClasses = true;
require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        <div class="page-toolbar">
            <div class="toolbar-left">
                <select class="form-control" id="classFilter" onchange="loadExams()" style="width:200px">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Schedule Exam</button>
            </div>
        </div>
        <div class="card">
            <div id="tableLoading" style="text-align:center;padding:40px"><div class="spinner"></div></div>
            <div class="table-wrap" id="tableWrap" style="display:none">
                <table>
                    <thead><tr><th>Exam Name</th><th>Class</th><th>Subject</th><th>Date</th><th>Max Marks</th><th>Total Given</th><th>Actions</th></tr></thead>
                    <tbody id="examBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- Schedule Exam Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">📝 Schedule Exam</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form id="addForm" onsubmit="submitExam(event)">
            <div class="form-group"><label class="form-label">Exam Name *</label><input type="text" class="form-control" name="name" required></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Class</label>
                    <select class="form-control" name="class_id">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Subject</label><input type="text" class="form-control" name="subject"></div>
            </div>
            <div class="form-row-3">
                <div class="form-group"><label class="form-label">Date *</label><input type="date" class="form-control" name="exam_date" required></div>
                <div class="form-group"><label class="form-label">Start Time</label><input type="time" class="form-control" name="start_time" value="09:00"></div>
                <div class="form-group"><label class="form-label">End Time</label><input type="time" class="form-control" name="end_time" value="12:00"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Max Marks</label><input type="number" class="form-control" name="max_marks" value="100"></div>
                <div class="form-group"><label class="form-label">Pass Marks</label><input type="number" class="form-control" name="pass_marks" value="33"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Description (Optional)</label>
                <textarea class="form-control" name="description" rows="2" placeholder="Exam syllabus or instructions"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule</button>
            </div>
        </form>
    </div>
</div>
<!-- Results Modal -->
<div class="modal-overlay" id="resultsModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header"><div class="modal-title" id="resultsTitle">Enter Results</div><button class="modal-close" onclick="closeModal('resultsModal')">✕</button></div>
        <div id="resultsBody"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:16px;gap:10px">
            <button class="btn btn-secondary" onclick="closeModal('resultsModal')">Close</button>
            <button class="btn btn-primary" onclick="saveResults()">💾 Save Results</button>
        </div>
    </div>
</div>
<button class="chatbot-btn" onclick="toggleChatbot()">🤖</button>
<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-head"><span class="chatbot-head-icon">🤖</span><div><div class="chatbot-head-title">ERP Assistant</div></div><button class="chatbot-head-close" onclick="toggleChatbot()">✕</button></div>
    <div class="chatbot-body" id="chatBody"></div>
    <div class="chatbot-footer"><input type="text" id="chatInput" placeholder="Ask about exams..."/><button class="chatbot-send" onclick="sendChatMessage()">➤</button></div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let currentExamId = null;

async function loadExams() {
    const classId = document.getElementById('classFilter').value;
    document.getElementById('tableLoading').style.display='block';document.getElementById('tableWrap').style.display='none';
    const data = await apiGet(`/api/exams/index.php?class_id=${classId}`);
    document.getElementById('examBody').innerHTML = (Array.isArray(data)?data:[]).map(e => `
        <tr>
            <td><strong>${escHtml(e.name)}</strong></td>
            <td>${escHtml(e.class_name||'All')}</td>
            <td>${escHtml(e.subject||'-')}</td>
            <td>${e.exam_date ? new Date(e.exam_date).toLocaleDateString('en-IN') : '-'}</td>
            <td>${e.max_marks}</td>
            <td><span class="badge badge-info">${e.pass_marks} to pass</span></td>
            <td><div style="display:flex;gap:6px">
                <button class="btn btn-primary btn-sm" onclick="openResults(${e.id},'${escHtml(e.name)}',${e.class_id||0},${e.max_marks})">📊 Results</button>
                <button class="btn btn-danger btn-sm" onclick="deleteExam(${e.id})">🗑️</button>
            </div></td>
        </tr>
    `).join('') || '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📝</div><div class="empty-state-text">No exams scheduled</div></div></td></tr>';
    document.getElementById('tableLoading').style.display='none';document.getElementById('tableWrap').style.display='block';
}

async function openResults(id, name, classId, maxMarks) {
    currentExamId = id;
    document.getElementById('resultsTitle').textContent = `📊 Results: ${name}`;
    const data = await apiGet(`/api/exams/index.php?id=${id}`);
    const students = classId
        ? await apiGet(`/api/students/index.php?page=1&class_id=${classId}&search=`)
        : { data: [] };

    const studs = students.data || [];
    const results = {};
    let sumMarks = 0, passed = 0, highest = 0, lowest = maxMarks, counts = 0;
    (data.results||[]).forEach(r => { 
        results[r.student_id] = r; 
        if (r.marks_obtained != null) {
            let m = parseFloat(r.marks_obtained);
            sumMarks += m;
            if (r.status === 'pass') passed++;
            if (m > highest) highest = m;
            if (m < lowest) lowest = m;
            counts++;
        }
    });
    if (counts === 0) lowest = 0;
    let avg = counts > 0 ? (sumMarks / counts).toFixed(1) : 0;
    let passRate = counts > 0 ? Math.round((passed / counts) * 100) : 0;

    let statsHtml = `<div class="summary-grid" style="margin-bottom:16px;">
        <div class="summary-tile"><div class="summary-kicker">Class Average</div><div class="summary-value">${avg}</div></div>
        <div class="summary-tile"><div class="summary-kicker">Pass Rate</div><div class="summary-value">${passRate}%</div></div>
        <div class="summary-tile"><div class="summary-kicker">Highest Score</div><div class="summary-value">${highest}</div></div>
        <div class="summary-tile"><div class="summary-kicker">Lowest Score</div><div class="summary-value">${lowest}</div></div>
    </div>`;

    document.getElementById('resultsBody').innerHTML = statsHtml + `<div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Roll No</th><th>Marks / ${maxMarks}</th><th>Grade</th><th>Status</th></tr></thead>
        <tbody>${studs.map(s => {
            const r = results[s.id];
            return `<tr>
                <td>${escHtml(s.name)}</td><td>${escHtml(s.roll_number||'-')}</td>
                <td><input type="number" class="form-control" style="width:80px" id="marks_${s.id}" value="${r?.marks_obtained||''}" max="${maxMarks}" min="0"></td>
                <td id="grade_${s.id}">${r?.grade||'-'}</td>
                <td><span class="badge ${r?.status==='pass'?'badge-success':r?.status==='fail'?'badge-danger':'badge-warning'}" id="status_${s.id}">${r?.status||'-'}</span></td>
            </tr>`;
        }).join('')||'<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">No students in this class</td></tr>'}</tbody>
    </table></div>
    <input type="hidden" id="resultsClassId" value="${classId}">
    <input type="hidden" id="resultsMaxMarks" value="${maxMarks}">
    <input type="hidden" id="resultsStudents" value='${JSON.stringify(studs.map(s=>s.id))}'>`;

    openModal('resultsModal');
}

async function saveResults() {
    const ids = JSON.parse(document.getElementById('resultsStudents').value);
    const max = +document.getElementById('resultsMaxMarks').value;
    const results = ids.map(id => ({ student_id: id, marks: +document.getElementById('marks_'+id)?.value||0, max })).filter(r => r.marks >= 0);
    const res = await apiPost('/api/exams/index.php', { exam_id: currentExamId, results });
    if (res.success) { showToast('Results saved successfully!'); closeModal('resultsModal'); }
    else showToast(res.error||'Failed','danger');
}

async function submitExam(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('addForm')));
    const res = await apiPost('/api/exams/index.php', data);
    if (res.success) { showToast('Exam scheduled!'); closeModal('addModal'); document.getElementById('addForm').reset(); loadExams(); }
    else showToast(res.error||'Failed','danger');
}

async function deleteExam(id) {
    if (!confirm('Delete this exam?')) return;
    await fetch(`/api/exams/index.php?id=${id}`, {method:'DELETE'});
    showToast('Exam deleted'); loadExams();
}

loadExams();
</script>
</body>
</html>
