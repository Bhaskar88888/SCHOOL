<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin', 'hr']);

$pageTitle = 'Departments Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .dept-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .dept-card { background: var(--surface-container-lowest); border: 1px solid rgba(172,179,180,.15); border-radius: 12px; padding: 20px; }
        .dept-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .dept-name { font-weight: 800; font-size: 18px; margin-bottom: 4px; }
        .dept-code { font-size: 11px; font-weight: 700; color: var(--accent); background: rgba(99,102,241,.1); padding: 2px 8px; border-radius: 4px; }
        .dept-info { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; border-bottom: 1px solid rgba(172,179,180,.1); padding-bottom: 8px; }
        .dept-actions { display: flex; gap: 8px; margin-top: 16px; }
        .btn-icon { padding: 6px; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; border: none; background: transparent; border-radius: 6px; }
        .btn-icon:hover { background: rgba(172,179,180,.1); }
        .btn-icon.edit { color: var(--accent); }
        .btn-icon.delete { color: #ef4444; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-content" style="padding: 24px 28px">
            <div class="page-header">
                <div>
                    <h1 style="margin:0; font-size: 24px; font-weight: 800;">Departments</h1>
                    <div style="color: var(--ink-3); font-size: 14px; margin-top: 4px;">Manage school departments and assigned staff</div>
                </div>
                <button class="btn btn-primary" onclick="openModal('add')">
                    + Add Department
                </button>
            </div>

            <div id="loading" style="text-align: center; padding: 40px; color: var(--ink-3);">Loading departments...</div>
            <div id="emptyState" class="card" style="display: none; text-align: center; padding: 40px;">
                <div style="font-size: 40px; margin-bottom: 16px;">🏢</div>
                <h3 style="margin-bottom: 8px;">No Departments Found</h3>
                <p style="color: var(--ink-3); margin-bottom: 20px;">Get started by creating your first department.</p>
                <button class="btn btn-primary" onclick="openModal('add')">Create Department</button>
            </div>
            
            <div class="dept-grid" id="deptGrid"></div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="deptModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add Department</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="deptForm" onsubmit="submitForm(event)">
                <input type="hidden" id="deptId" name="id">
                
                <div class="form-group">
                    <label class="form-label">Department Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="deptName" name="name" required placeholder="e.g., Mathematics, Administration">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Department Code</label>
                    <input type="text" class="form-control" id="deptCode" name="code" placeholder="e.g., MATH, ADMIN">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Department Head</label>
                    <select class="form-control" id="deptHead" name="head_user_id">
                        <option value="">-- Select Head --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="deptDesc" name="description" rows="3" placeholder="Brief description of the department's role"></textarea>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let currentDepartments = [];
let availableStaff = [];

async function loadData() {
    document.getElementById('loading').style.display = 'block';
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('deptGrid').innerHTML = '';
    
    const res = await apiGet('/api/departments/index.php');
    document.getElementById('loading').style.display = 'none';
    
    if (res && res.data) {
        currentDepartments = res.data;
        availableStaff = res.staff || [];
        
        populateStaffDropdown();
        renderGrid();
    } else {
        showToast('Failed to load departments', 'danger');
    }
}

function populateStaffDropdown() {
    const select = document.getElementById('deptHead');
    // keep first option
    select.innerHTML = '<option value="">-- Select Head --</option>';
    availableStaff.forEach(staff => {
        const opt = document.createElement('option');
        opt.value = staff.id;
        opt.textContent = `${staff.name} (${staff.role})`;
        select.appendChild(opt);
    });
}

function renderGrid() {
    const grid = document.getElementById('deptGrid');
    
    if (currentDepartments.length === 0) {
        document.getElementById('emptyState').style.display = 'block';
        return;
    }
    
    document.getElementById('emptyState').style.display = 'none';
    
    currentDepartments.forEach(dept => {
        const card = document.createElement('div');
        card.className = 'dept-card';
        
        const headName = dept.head_name ? dept.head_name : '<span style="color:#94a3b8">Unassigned</span>';
        const codeBadge = dept.code ? `<span class="dept-code">${dept.code}</span>` : '';
        
        card.innerHTML = `
            <div class="dept-card-header">
                <div>
                    <div class="dept-name">${dept.name}</div>
                    ${codeBadge}
                </div>
            </div>
            
            <div class="dept-info">
                <span style="color:var(--ink-3)">Head</span>
                <span style="font-weight:600">${headName}</span>
            </div>
            <div class="dept-info" style="border-bottom:none">
                <span style="color:var(--ink-3)">Staff Members</span>
                <span style="font-weight:700; color:var(--accent)">${dept.staff_count}</span>
            </div>
            
            ${dept.description ? `<div style="font-size:12px; color:var(--ink-3); margin-top:8px; line-height:1.4">${dept.description}</div>` : ''}
            
            <div class="dept-actions">
                <button class="btn btn-secondary btn-sm" style="flex:1" onclick='openModal("edit", ${JSON.stringify(dept).replace(/'/g, "&#39;")})'>Edit</button>
                <button class="btn-icon delete" onclick="deleteDept(${dept.id}, ${dept.staff_count})" title="Archive">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </div>
        `;
        grid.appendChild(card);
    });
}

function openModal(mode, dept = null) {
    const modal = document.getElementById('deptModal');
    const form = document.getElementById('deptForm');
    
    form.reset();
    document.getElementById('deptId').value = '';
    
    if (mode === 'add') {
        document.getElementById('modalTitle').textContent = 'Add Department';
    } else if (mode === 'edit' && dept) {
        document.getElementById('modalTitle').textContent = 'Edit Department';
        document.getElementById('deptId').value = dept.id;
        document.getElementById('deptName').value = dept.name;
        document.getElementById('deptCode').value = dept.code || '';
        document.getElementById('deptHead').value = dept.head_user_id || '';
        document.getElementById('deptDesc').value = dept.description || '';
    }
    
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('deptModal').style.display = 'none';
}

async function submitForm(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const isEdit = !!data.id;
    
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    try {
        const res = isEdit 
            ? await apiPut('/api/departments/index.php', data)
            : await apiPost('/api/departments/index.php', data);
            
        if (res && res.success) {
            showToast(res.message || 'Saved successfully');
            closeModal();
            loadData();
        } else {
            showToast(res.error || 'Operation failed', 'danger');
        }
    } catch (err) {
        showToast('A network error occurred', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Department';
    }
}

async function deleteDept(id, staffCount) {
    if (staffCount > 0) {
        showToast(`Cannot delete: ${staffCount} staff members are assigned to this department.`, 'danger');
        return;
    }
    
    if (!confirm('Are you sure you want to archive this department?')) return;
    
    const res = await apiDelete('/api/departments/index.php', { id });
    if (res && res.success) {
        showToast('Department archived');
        loadData();
    } else {
        showToast(res.error || 'Failed to archive', 'danger');
    }
}

// Initial load
loadData();
</script>
</body>
</html>
