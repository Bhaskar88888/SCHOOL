<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Students';

$classes = db_fetchAll("SELECT id, name FROM classes ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — School ERP</title>
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
                <input type="text" class="form-control" id="searchInput" placeholder="🔍 Search students..." oninput="filterTable('searchInput','studentTable')" style="width:260px">
                <select class="form-control" id="classFilter" onchange="loadStudents()" style="width:180px">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-secondary" onclick="openModal('importModal')" style="margin-right:8px">📥 Import CSV</button>
                <button class="btn btn-secondary" onclick="exportData()" style="margin-right:8px">📂 Export CSV</button>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Student</button>
            </div>
        </div>

        <div class="card">
            <div id="tableLoading" style="text-align:center;padding:40px"><div class="spinner"></div></div>
            <div class="table-wrap" id="tableWrap" style="display:none">
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Roll No</th>
                            <th>Gender</th>
                            <th>Parent</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentBody"></tbody>
                </table>
            </div>
            <div id="pagination" class="pagination"></div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👨‍🎓 Add New Student</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form id="addForm" onsubmit="submitAdd(event)">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Roll Number *</label>
                    <input type="text" class="form-control" name="roll_number" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Class *</label>
                    <select class="form-control" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender *</label>
                    <select class="form-control" name="gender" required>
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date of Birth *</label>
                    <input type="date" class="form-control" name="dob" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" name="phone">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Parent/Guardian Name *</label>
                    <input type="text" class="form-control" name="parent_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" rows="2"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Student</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form id="editForm" onsubmit="submitEdit(event)">
            <input type="hidden" name="id" id="editId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" id="editName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Roll Number *</label>
                    <input type="text" class="form-control" name="roll_number" id="editRoll" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Class *</label>
                    <select class="form-control" name="class_id" id="editClass" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender *</label>
                    <select class="form-control" name="gender" id="editGender" required>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Parent Name *</label>
                    <input type="text" class="form-control" name="parent_name" id="editParent" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" name="phone" id="editPhone">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Student Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">📥 Import Students from CSV</div>
            <button class="modal-close" onclick="closeModal('importModal')">✕</button>
        </div>
        <form id="importForm" onsubmit="submitImport(event)" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Choose CSV File *</label>
                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                <div style="font-size:11px; color:var(--text-muted); margin-top:8px">
                    CSV Format: Name, RollNumber, ClassName, Gender, ParentName, Phone, Email, Address
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Chatbot -->
<button class="chatbot-btn" onclick="toggleChatbot()" title="AI Assistant">🤖</button>
<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-head">
        <span class="chatbot-head-icon">🤖</span>
        <div>
            <div class="chatbot-head-title">ERP Assistant</div>
            <div class="chatbot-head-sub">AI-powered school helper</div>
        </div>
        <button class="chatbot-head-close" onclick="toggleChatbot()">✕</button>
    </div>
    <div class="chatbot-body" id="chatBody"></div>
    <div class="chatbot-footer">
        <input type="text" id="chatInput" placeholder="Ask about students..." />
        <button class="chatbot-send" onclick="sendChatMessage()">➤</button>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
let currentPage = 1;

async function loadStudents(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const classId = document.getElementById('classFilter').value;
    document.getElementById('tableLoading').style.display = 'block';
    document.getElementById('tableWrap').style.display = 'none';

    const data = await apiGet(`/api/students/index.php?page=${page}&search=${encodeURIComponent(search)}&class_id=${classId}`);

    const body = document.getElementById('studentBody');
    if (!data.data?.length) {
        body.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">👨‍🎓</div><div class="empty-state-text">No students found</div></div></td></tr>';
    } else {
        body.innerHTML = data.data.map(s => `
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:12px">${s.name.charAt(0).toUpperCase()}</div>
                        <div>
                            <div style="font-weight:500">${escHtml(s.name)}</div>
                            <div style="font-size:11px;color:var(--text-muted)">${escHtml(s.email||'')}</div>
                        </div>
                    </div>
                </td>
                <td>${escHtml(s.class_name||'-')}</td>
                <td>${escHtml(s.roll_number||'-')}</td>
                <td><span class="badge badge-info">${escHtml(s.gender||'-')}</span></td>
                <td>${escHtml(s.parent_name||'-')}</td>
                <td>${escHtml(s.phone||'-')}</td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-secondary btn-sm" onclick="editStudent(${s.id})">✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteStudent(${s.id}, '${escHtml(s.name)}')">🗑️</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Pagination
    let pag = '';
    if (data.pages > 1) {
        for (let i = 1; i <= data.pages; i++) {
            pag += `<a href="#" onclick="loadStudents(${i})" class="${i===currentPage?'current':''}">${i}</a>`;
        }
    }
    document.getElementById('pagination').innerHTML = pag;

    document.getElementById('tableLoading').style.display = 'none';
    document.getElementById('tableWrap').style.display = 'block';
}

async function submitAdd(e) {
    e.preventDefault();
    const form = document.getElementById('addForm');
    const data = Object.fromEntries(new FormData(form));
    const res = await apiPost('/api/students/index.php', data);
    if (res.success) {
        showToast('Student added successfully!');
        closeModal('addModal');
        form.reset();
        loadStudents();
    } else {
        showToast(res.error || 'Failed to add student', 'danger');
    }
}

async function editStudent(id) {
    const s = await apiGet(`/api/students/index.php?id=${id}`);
    document.getElementById('editId').value    = s.id;
    document.getElementById('editName').value  = s.name;
    document.getElementById('editRoll').value  = s.roll_number;
    document.getElementById('editClass').value = s.class_id;
    document.getElementById('editGender').value= s.gender;
    document.getElementById('editParent').value= s.parent_name;
    document.getElementById('editPhone').value = s.phone;
    openModal('editModal');
}

async function submitEdit(e) {
    e.preventDefault();
    const form = document.getElementById('editForm');
    const data = Object.fromEntries(new FormData(form));
    const res = await fetch('/api/students/index.php', {
        method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json());
    if (res.success) {
        showToast('Student updated!');
        closeModal('editModal');
        loadStudents(currentPage);
    } else {
        showToast(res.error || 'Update failed', 'danger');
    }
}

async function deleteStudent(id, name) {
    if (!confirm(`Remove student "${name}"? This action cannot be undone.`)) return;
    const res = await fetch(`/api/students/index.php?id=${id}`, {method:'DELETE'}).then(r=>r.json());
    if (res.success) {
        showToast(`${name} removed.`);
        loadStudents(currentPage);
    } else {
        showToast(res.error || 'Delete failed', 'danger');
    }
}

function exportData() {
    const classId = document.getElementById('classFilter').value;
    window.location.href = `/api/students/export.php?class_id=${classId}`;
}

async function submitImport(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('/api/students/import.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json());
    
    if (res.success) {
        showToast(`Import completed! ${res.imported} imported, ${res.errors} errors.`);
        closeModal('importModal');
        loadStudents();
    } else {
        showToast(res.error || 'Import failed', 'danger');
    }
}

loadStudents();
</script>
</body>
</html>
