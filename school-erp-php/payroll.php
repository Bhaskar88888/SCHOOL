<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin', 'accountant']);
$pageTitle  = 'Payroll Management';
$needsStaff = true;
require_once __DIR__ . '/includes/data.php';

$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$currentMonth = date('F');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll — School ERP</title>
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
                <select class="form-control" id="monthFilter" onchange="loadPayroll()" style="width:140px">
                    <?php foreach($months as $m): ?><option value="<?= $m ?>" <?= $m==$currentMonth?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
                </select>
                <input type="number" class="form-control" id="yearFilter" value="<?= $currentYear ?>" onchange="loadPayroll()" style="width:100px">
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Process Salary</button>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card" style="--stat-color:#4f8ef7">
                <div class="stat-icon" style="--stat-color:#4f8ef7">💸</div>
                <div class="stat-info"><div class="stat-value" id="statPaid">₹0</div><div class="stat-label">Total Paid Salary</div></div>
            </div>
            <div class="stat-card" style="--stat-color:#f85149">
                <div class="stat-icon" style="--stat-color:#f85149">⏳</div>
                <div class="stat-info"><div class="stat-value" id="statPending">₹0</div><div class="stat-label">Total Pending</div></div>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Staff Name</th><th>Role</th><th>Basic Salary</th><th>Allowances</th><th>Deductions</th><th>Net Salary</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="payrollBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">💸 Process Salary</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form id="addForm" onsubmit="submitPayroll(event)">
            <div class="form-group"><label class="form-label">Select Staff *</label>
                <select class="form-control" name="staff_id" id="selStaff" required>
                    <option value="">Choose...</option>
                    <?php foreach($staff as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Month *</label>
                    <select class="form-control" name="month" id="selMonth" required>
                        <?php foreach($months as $m): ?><option value="<?= $m ?>" <?= $m==$currentMonth?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Year *</label><input type="number" class="form-control" name="year" id="selYear" value="<?= $currentYear ?>" required></div>
            </div>
            <div class="form-group"><label class="form-label">Basic Salary (₹) *</label><input type="number" class="form-control" name="basic_salary" id="inpBasic" value="0" required oninput="calcNet()"></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Allowances (₹)</label><input type="number" class="form-control" name="allowances" id="inpAllow" value="0" oninput="calcNet()"></div>
                <div class="form-group"><label class="form-label">Deductions (₹)</label><input type="number" class="form-control" name="deductions" id="inpDeduct" value="0" oninput="calcNet()"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Status *</label>
                    <select class="form-control" name="status" id="selStatus">
                        <option value="pending">Pending</option><option value="paid">Paid</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Paid Date</label><input type="date" class="form-control" name="paid_date" id="selDate"></div>
            </div>
            <div style="background:var(--bg-secondary);padding:14px;border-radius:var(--radius-sm);margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                <strong>Net Salary:</strong> <span style="font-size:20px;font-weight:700;color:var(--success)" id="lblNet">₹0</span>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payroll</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
let payrollData = [];

function calcNet() {
    const b = parseFloat(document.getElementById('inpBasic').value||0);
    const a = parseFloat(document.getElementById('inpAllow').value||0);
    const d = parseFloat(document.getElementById('inpDeduct').value||0);
    document.getElementById('lblNet').textContent = '₹' + (b + a - d).toLocaleString('en-IN');
}

async function loadPayroll() {
    const m = document.getElementById('monthFilter').value;
    const y = document.getElementById('yearFilter').value;
    payrollData = await apiGet(`/api/payroll/index.php?month=${m}&year=${y}`);
    
    let tPaid = 0, tPending = 0;
    
    document.getElementById('payrollBody').innerHTML = payrollData.map(p => {
        const net = parseFloat(p.net_salary);
        if (p.status === 'paid') tPaid += net; else tPending += net;
        return `
        <tr>
            <td><strong>${escHtml(p.staff_name)}</strong></td>
            <td><span style="font-size:12px;color:var(--text-muted)">${escHtml(p.role)}</span></td>
            <td>₹${parseFloat(p.basic_salary).toLocaleString('en-IN')}</td>
            <td style="color:var(--success)">+ ₹${parseFloat(p.allowances).toLocaleString('en-IN')}</td>
            <td style="color:var(--danger)">- ₹${parseFloat(p.deductions).toLocaleString('en-IN')}</td>
            <td><strong>₹${net.toLocaleString('en-IN')}</strong></td>
            <td><span class="badge ${p.status==='paid'?'badge-success':'badge-warning'}">${p.status}</span></td>
            <td><button class="btn btn-secondary btn-sm" onclick="editPayroll(${p.staff_id}, ${p.basic_salary}, ${p.allowances}, ${p.deductions}, '${p.status}', '${p.paid_date||''}')">✏️</button></td>
        </tr>
    `}).join('') || '<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--text-muted)">No payroll records for this month</td></tr>';
    
    document.getElementById('statPaid').textContent = '₹' + tPaid.toLocaleString('en-IN');
    document.getElementById('statPending').textContent = '₹' + tPending.toLocaleString('en-IN');
}

function editPayroll(staffId, basic, allow, deduct, status, pdate) {
    document.getElementById('selStaff').value = staffId;
    document.getElementById('selMonth').value = document.getElementById('monthFilter').value;
    document.getElementById('selYear').value = document.getElementById('yearFilter').value;
    document.getElementById('inpBasic').value = basic;
    document.getElementById('inpAllow').value = allow;
    document.getElementById('inpDeduct').value = deduct;
    document.getElementById('selStatus').value = status;
    document.getElementById('selDate').value = pdate;
    calcNet();
    openModal('addModal');
}

async function submitPayroll(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('addForm')));
    const res = await apiPost('/api/payroll/index.php', data);
    if (res.success) { showToast('Payroll updated'); closeModal('addModal'); loadPayroll(); }
    else showToast(res.error||'Failed','danger');
}

loadPayroll();
</script>
</body>
</html>
