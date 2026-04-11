<?php
require_once __DIR__ . '/includes/auth.php';

require_auth();
$pageTitle = 'Leave Applications';
$currentRole = normalize_role_name(get_current_role());
$canReview = role_matches($currentRole, ['superadmin', 'admin', 'hr']);
$canApply = $currentRole !== 'superadmin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <?php if (!$canReview): ?>
        <div class="summary-grid" style="margin-bottom:20px">
            <div class="summary-tile">
                <div class="summary-kicker">Casual Leave</div>
                <div class="summary-value" id="casualBalance">0</div>
            </div>
            <div class="summary-tile">
                <div class="summary-kicker">Earned Leave</div>
                <div class="summary-value" id="earnedBalance">0</div>
            </div>
            <div class="summary-tile">
                <div class="summary-kicker">Sick Leave</div>
                <div class="summary-value" id="sickBalance">0</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-toolbar">
            <div class="toolbar-left">
                <select class="form-control" id="statusFilter" onchange="loadLeaves()" style="width:180px">
                    <option value="">All Applications</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <?php if ($canApply): ?>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Apply for Leave</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Leave Type</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="dataTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($canApply): ?>
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Apply for Leave</div>
            <button class="modal-close" onclick="closeModal('addModal')">X</button>
        </div>
        <form id="leaveForm" onsubmit="submitForm(event)">
            <div class="form-group">
                <label class="form-label">Leave Type</label>
                <select class="form-control" name="leave_type">
                    <option value="casual">Casual</option>
                    <option value="earned">Earned</option>
                    <option value="sick">Sick</option>
                    <option value="emergency">Emergency</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">From Date *</label>
                    <input type="date" class="form-control" name="from_date" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">To Date *</label>
                    <input type="date" class="form-control" name="to_date" required min="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Reason *</label>
                <textarea class="form-control" name="reason" rows="3" required></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canReview): ?>
<div class="modal-overlay" id="actionModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title">Take Action</div>
            <button class="modal-close" onclick="closeModal('actionModal')">X</button>
        </div>
        <form id="actionForm" onsubmit="submitAction(event)">
            <input type="hidden" id="actionId" name="id">
            <div class="form-group">
                <label class="form-label">Review Note</label>
                <textarea class="form-control" name="note" rows="3" placeholder="Optional note for the applicant"></textarea>
            </div>
            <div style="display:flex;gap:10px">
                <button type="button" class="btn btn-success" style="flex:1" onclick="reviewAction('approved')">Approve</button>
                <button type="button" class="btn btn-danger" style="flex:1" onclick="reviewAction('rejected')">Reject</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const canReview = <?= $canReview ? 'true' : 'false' ?>;
const canApply = <?= $canApply ? 'true' : 'false' ?>;
let pendingStatus = 'approved';

const leaveTypeLabels = {
    casual: 'Casual',
    earned: 'Earned',
    sick: 'Sick',
    emergency: 'Emergency',
    unpaid: 'Unpaid'
};

function formatLeaveType(type) {
    const key = String(type || '').toLowerCase();
    return leaveTypeLabels[key] || key || '-';
}

async function loadBalance() {
    if (canReview) return;
    const response = await apiGet('/api/leave/enhanced.php?action=balance');
    const balance = response.balance || {};
    document.getElementById('casualBalance').textContent = balance.casual ?? 0;
    document.getElementById('earnedBalance').textContent = balance.earned ?? 0;
    document.getElementById('sickBalance').textContent = balance.sick ?? 0;
}

async function loadLeaves() {
    const status = document.getElementById('statusFilter').value;
    const url = `/api/leave/index.php?status=${encodeURIComponent(status)}${canReview ? '' : '&my=1'}`;
    const leaves = await apiGet(url);

    document.getElementById('dataTable').innerHTML = Array.isArray(leaves) && leaves.length ? leaves.map((leave) => {
        const statusClass = leave.status === 'approved'
            ? 'badge-success'
            : (leave.status === 'rejected' ? 'badge-danger' : 'badge-warning');
        const actionHtml = canReview && leave.status === 'pending'
            ? `<button class="btn btn-primary btn-sm" onclick="openAction(${leave.id})">Take Action</button>`
            : `<span style="font-size:11px;color:var(--ink-4)">${leave.approved_by_name ? `by ${escHtml(leave.approved_by_name)}` : '-'}</span>`;

        return `
            <tr>
                <td><strong>${escHtml(leave.applicant_name || 'Self')}</strong></td>
                <td><span class="badge badge-info">${escHtml(formatLeaveType(leave.leave_type))}</span></td>
                <td>${leave.from_date ? new Date(leave.from_date).toLocaleDateString('en-IN') : '-'} to ${leave.to_date ? new Date(leave.to_date).toLocaleDateString('en-IN') : '-'}</td>
                <td><div style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escHtml(leave.reason || '')}">${escHtml(leave.reason || '-')}</div></td>
                <td><span class="badge ${statusClass}" style="text-transform:capitalize">${escHtml(leave.status || 'pending')}</span></td>
                <td>${actionHtml}</td>
            </tr>
        `;
    }).join('') : '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">Leave</div><div class="empty-state-text">No leave applications found.</div></div></td></tr>';
}

function openAction(id) {
    document.getElementById('actionId').value = id;
    document.querySelector('#actionForm textarea[name="note"]').value = '';
    openModal('actionModal');
}

async function submitForm(event) {
    event.preventDefault();
    const response = await apiPost('/api/leave/index.php', Object.fromEntries(new FormData(event.target)));
    if (response.success) {
        showToast('Leave application submitted.');
        closeModal('addModal');
        event.target.reset();
        loadLeaves();
        loadBalance();
        return;
    }
    showToast(response.error || 'Unable to submit leave application.', 'danger');
}

function reviewAction(status) {
    pendingStatus = status;
    document.getElementById('actionForm').requestSubmit();
}

async function submitAction(event) {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target));
    payload.status = pendingStatus;
    const response = await fetch('/api/leave/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then((res) => res.json());

    if (response.success) {
        showToast(`Leave ${pendingStatus}.`);
        closeModal('actionModal');
        loadLeaves();
        return;
    }

    showToast(response.error || 'Unable to update leave status.', 'danger');
}

document.addEventListener('DOMContentLoaded', () => {
    loadLeaves();
    loadBalance();
});
</script>
</body>
</html>
