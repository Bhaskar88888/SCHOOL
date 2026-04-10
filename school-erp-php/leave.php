<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle  = 'Leave Applications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave — School ERP</title>
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
                <select class="form-control" id="statusFilter" onchange="loadLeaves()" style="width:160px">
                    <option value="">All Applications</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Apply for Leave</button>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Applicant</th><th>Leave Type</th><th>Duration (From - To)</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Apply Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">📅 Apply for Leave</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form onsubmit="submitForm(event)">
            <div class="form-group"><label class="form-label">Leave Type</label>
                <select class="form-control" name="leave_type"><option>Sick Leave</option><option>Casual Leave</option><option>Emergency</option><option>Other</option></select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">From Date *</label><input type="date" class="form-control" name="from_date" required min="<?= date('Y-m-d') ?>"></div>
                <div class="form-group"><label class="form-label">To Date *</label><input type="date" class="form-control" name="to_date" required min="<?= date('Y-m-d') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Reason *</label><textarea class="form-control" name="reason" rows="3" required></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<!-- Action Modal (Admin) -->
<div class="modal-overlay" id="actionModal">
    <div class="modal" style="width:400px">
        <div class="modal-header"><div class="modal-title">Take Action</div><button class="modal-close" onclick="closeModal('actionModal')">✕</button></div>
        <div style="text-align:center;padding:20px 0">
            <input type="hidden" id="actionId">
            <button class="btn btn-success" style="width:100%;margin-bottom:10px;padding:12px;font-size:15px" onclick="takeAction('approved')">✅ Approve Leave</button>
            <button class="btn btn-danger" style="width:100%;padding:12px;font-size:15px" onclick="takeAction('rejected')">❌ Reject Leave</button>
        </div>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
async function loadLeaves() {
    const s = document.getElementById('statusFilter').value;
    const leaves = await apiGet(`/api/leave/index.php?status=${s}`);
    
    document.getElementById('dataTable').innerHTML = leaves.map(l => {
        const adminHtml = l.status==='pending' ? `<button class="btn btn-primary btn-sm" onclick="openAction(${l.id})">Take Action</button>` : `<span style="font-size:11px;color:var(--text-muted)">by ${escHtml(l.approved_by_name||'Admin')}</span>`;
        return `
        <tr>
            <td><strong>${escHtml(l.applicant_name)}</strong></td>
            <td><span class="badge badge-info">${escHtml(l.leave_type)}</span></td>
            <td>${new Date(l.from_date).toLocaleDateString()} to ${new Date(l.to_date).toLocaleDateString()}</td>
            <td><p style="margin:0;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(l.reason)}">${escHtml(l.reason)}</p></td>
            <td><span class="badge ${l.status==='approved'?'badge-success':l.status==='rejected'?'badge-danger':'badge-warning'}" style="text-transform:capitalize">${l.status}</span></td>
            <td>${adminHtml}</td>
        </tr>
    `}).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No applications found</td></tr>';
}

function openAction(id) { document.getElementById('actionId').value = id; openModal('actionModal'); }

async function submitForm(e) {
    e.preventDefault();
    const res = await apiPost('/api/leave/index.php', Object.fromEntries(new FormData(e.target)));
    if(res.success){ showToast('Applied successfully'); closeModal('addModal'); e.target.reset(); loadLeaves(); }
    else showToast(res.error||'Error','danger');
}

async function takeAction(status) {
    const id = document.getElementById('actionId').value;
    const res = await fetch('/api/leave/index.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,status})}).then(r=>r.json());
    if(res.success){ showToast('Status updated'); closeModal('actionModal'); loadLeaves(); }
    else showToast(res.error||'Error','danger');
}

loadLeaves();
</script>
</body>
</html>
