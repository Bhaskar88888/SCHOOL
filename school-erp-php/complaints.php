<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle  = 'Complaints & Grievances';
$needsStaff = true;
require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div class="toolbar-left" style="display:flex;gap:10px;">
                <select class="form-control" id="statusFilter" onchange="loadComplaints()" style="width:160px">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select class="form-control" id="categoryFilter" onchange="loadComplaints()" style="width:160px">
                    <option value="">All Categories</option>
                    <option value="General">General</option>
                    <option value="Academics">Academics</option>
                    <option value="Facilities">Facilities</option>
                    <option value="Staff">Staff</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Register Complaint</button>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Complaint ID</th><th>Title & Category</th><th>Submitted By</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">📣 Register Complaint</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form id="addForm" onsubmit="submitForm(event)">
            <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-control" name="title" required></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Category</label>
                    <select class="form-control" name="category"><option>General</option><option>Academics</option><option>Facilities</option><option>Staff</option><option>Other</option></select>
                </div>
                <div class="form-group"><label class="form-label">Priority</label>
                    <select class="form-control" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Modal (Admin only) -->
<div class="modal-overlay" id="manageModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">⚙️ Manage Complaint</div><button class="modal-close" onclick="closeModal('manageModal')">✕</button></div>
        <form id="manageForm" onsubmit="submitManage(event)">
            <input type="hidden" name="id" id="manageId">
            <div class="form-group"><label class="form-label">Assign To</label>
                <select class="form-control" name="assigned_to" id="manageAssign">
                    <option value="">Unassigned</option>
                    <?php foreach($staff as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Status</label>
                <select class="form-control" name="status" id="manageStatus">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('manageModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Complaint</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let compList = [];

async function loadComplaints() {
    const s = document.getElementById('statusFilter').value;
    const c = document.getElementById('categoryFilter').value;
    compList = await apiGet(`/api/complaints/index.php?status=${encodeURIComponent(s)}&category=${encodeURIComponent(c)}`);
    
    document.getElementById('dataTable').innerHTML = compList.map(c => `
        <tr>
            <td><strong>#CMP-${c.id.toString().padStart(4,'0')}</strong><div style="font-size:11px;color:var(--text-muted)">${new Date(c.created_at).toLocaleDateString()}</div></td>
            <td><strong>${escHtml(c.title)}</strong><div style="font-size:11px;color:var(--text-muted)">Cat: ${escHtml(c.category)}</div></td>
            <td>${escHtml(c.submitted_by_name||'Unknown')}</td>
            <td><span class="badge ${c.priority==='urgent'?'badge-danger':c.priority==='high'?'badge-warning':'badge-info'}" style="text-transform:capitalize">${c.priority}</span></td>
            <td><span class="badge ${c.status==='resolved'?'badge-success':c.status==='rejected'?'badge-danger':'badge-warning'}" style="text-transform:capitalize">${c.status.replace('_',' ')}</span></td>
            <td><button class="btn btn-secondary btn-sm" onclick="manage(${c.id}, '${c.status}', ${c.assigned_to||''})">Manage</button></td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No complaints found</td></tr>';
}

function manage(id, st, assigned) {
    document.getElementById('manageId').value = id;
    document.getElementById('manageStatus').value = st;
    document.getElementById('manageAssign').value = assigned;
    openModal('manageModal');
}

async function submitForm(e) {
    e.preventDefault();
    const res = await apiPost('/api/complaints/index.php', Object.fromEntries(new FormData(e.target)));
    if(res.success){ showToast('Complaint registered'); closeModal('addModal'); e.target.reset(); loadComplaints(); }
    else showToast(res.error||'Error','danger');
}

async function submitManage(e) {
    e.preventDefault();
    const res = await fetch('/api/complaints/index.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(e.target)))}).then(r=>r.json());
    if(res.success){ showToast('Updated'); closeModal('manageModal'); loadComplaints(); }
    else showToast(res.error||'Error','danger');
}

loadComplaints();
</script>
</body>
</html>
