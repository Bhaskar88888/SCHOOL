<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);
$pageTitle  = 'HR & Staff';
require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR & Staff — School ERP</title>
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
                <input type="text" class="form-control" id="searchInput" placeholder="🔍 Search staff..." style="width:260px" oninput="loadStaff()">
                <select class="form-control" id="roleFilter" onchange="loadStaff()" style="width:160px">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="teacher">Teacher</option>
                    <option value="accountant">Accountant</option>
                    <option value="librarian">Librarian</option>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Staff Member</button>
            </div>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Staff Name</th><th>Role</th><th>Email</th><th>Phone</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody id="staffBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">👔 Add Staff</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form id="addForm" onsubmit="submitStaff(event)">
            <input type="hidden" id="editId" name="id">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" class="form-control" id="staffName" name="name" required></div>
                <div class="form-group"><label class="form-label">Role *</label>
                    <select class="form-control" id="staffRole" name="role" required>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Admin</option>
                        <option value="accountant">Accountant</option>
                        <option value="librarian">Librarian</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Email *</label><input type="email" class="form-control" id="staffEmail" name="email" required></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="tel" class="form-control" id="staffPhone" name="phone"></div>
            </div>
            <div class="form-group"><label class="form-label">Password <span id="pwdHint">(Required for new)</span></label><input type="password" class="form-control" id="staffPassword" name="password"></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Staff</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
let staffList = [];

async function loadStaff() {
    const search = document.getElementById('searchInput').value;
    const role   = document.getElementById('roleFilter').value;
    staffList = await apiGet(`/api/hr/index.php?search=${encodeURIComponent(search)}&role=${role}`);
    
    document.getElementById('staffBody').innerHTML = staffList.map(s => `
        <tr>
            <td><div style="display:flex;align-items:center;gap:10px"><div class="user-avatar" style="width:30px;height:30px;font-size:12px">${s.name[0].toUpperCase()}</div><strong>${escHtml(s.name)}</strong></div></td>
            <td><span class="badge badge-primary">${escHtml(s.role)}</span></td>
            <td>${escHtml(s.email)}</td>
            <td>${escHtml(s.phone||'-')}</td>
            <td>${new Date(s.created_at).toLocaleDateString()}</td>
            <td><div style="display:flex;gap:6px">
                <button class="btn btn-secondary btn-sm" onclick="editStaff(${s.id})">✏️</button>
                ${s.role!=='superadmin' ? `<button class="btn btn-danger btn-sm" onclick="deleteStaff(${s.id})">🗑️</button>` : ''}
            </div></td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No staff found</td></tr>';
}

function editStaff(id) {
    const s = staffList.find(x => x.id === id);
    if (!s) return;
    document.getElementById('editId').value = s.id;
    document.getElementById('staffName').value = s.name;
    document.getElementById('staffEmail').value = s.email;
    document.getElementById('staffRole').value = s.role;
    document.getElementById('staffPhone').value = s.phone || '';
    document.getElementById('staffPassword').value = '';
    document.getElementById('pwdHint').textContent = '(Leave blank to keep current)';
    document.getElementById('staffPassword').required = false;
    document.querySelector('#addModal .modal-title').textContent = '✏️ Edit Staff';
    openModal('addModal');
}

function openAdd() {
    document.getElementById('addForm').reset();
    document.getElementById('editId').value = '';
    document.getElementById('pwdHint').textContent = '(Required)';
    document.getElementById('staffPassword').required = true;
    document.querySelector('#addModal .modal-title').textContent = '👔 Add Staff';
    openModal('addModal');
}

document.querySelector('.toolbar-right .btn-primary').onclick = openAdd;

async function submitStaff(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('addForm')));
    const isEdit = !!data.id;
    const res = isEdit 
        ? await fetch('/api/hr/index.php', { method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) }).then(r=>r.json())
        : await apiPost('/api/hr/index.php', data);
    
    if (res.success) { showToast(isEdit ? 'Staff updated!' : 'Staff created!'); closeModal('addModal'); loadStaff(); }
    else showToast(res.error || 'Failed', 'danger');
}

async function deleteStaff(id) {
    if (!confirm('Remove this staff member?')) return;
    await fetch(`/api/hr/index.php?id=${id}`, {method:'DELETE'});
    showToast('Staff removed'); loadStaff();
}

loadStaff();
</script>
</body>
</html>
