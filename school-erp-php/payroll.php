<?php
require_once __DIR__ . '/includes/auth.php';

require_auth();
$pageTitle = 'Payroll';
$currentRole = normalize_role_name(get_current_role());
$canViewAll = role_matches($currentRole, ['superadmin', 'admin', 'accounts']);
$canManageAttendance = $canViewAll || $currentRole === 'hr';
$canManageSalarySetup = $canViewAll;
$canGeneratePayroll = $canViewAll;
$staff = db_fetchAll("SELECT id, name, role, department FROM users WHERE is_active = 1 AND role NOT IN ('student', 'parent', 'superadmin') ORDER BY name ASC");
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$defaultMonth = (int) date('n');
$defaultYear = (int) date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - School ERP</title>
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
                <select class="form-control" id="monthFilter" onchange="reloadActiveTab()" style="width:150px">
                    <?php foreach ($months as $monthNumber => $monthLabel): ?>
                    <option value="<?= $monthNumber ?>" <?= $monthNumber === $defaultMonth ? 'selected' : '' ?>><?= $monthLabel ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" class="form-control" id="yearFilter" value="<?= $defaultYear ?>" onchange="reloadActiveTab()" style="width:110px">
            </div>
        </div>

        <div class="page-tabs">
            <button class="page-tab active" id="tab-my" onclick="switchTab('my')">My Payslips</button>
            <?php if ($canViewAll): ?><button class="page-tab" id="tab-all" onclick="switchTab('all')">All Payslips</button><?php endif; ?>
            <?php if ($canManageAttendance): ?><button class="page-tab" id="tab-attendance" onclick="switchTab('attendance')">Staff Attendance</button><?php endif; ?>
            <?php if ($canManageSalarySetup): ?><button class="page-tab" id="tab-setup" onclick="switchTab('setup')">Salary Setup</button><?php endif; ?>
            <?php if ($canGeneratePayroll): ?><button class="page-tab" id="tab-run" onclick="switchTab('run')">Generate Run</button><?php endif; ?>
        </div>

        <div id="panel-my" class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">My Payslips</div>
                    <div class="card-sub">Download your current payroll slips.</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Basic</th>
                            <th>Allowances</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="myPayrollBody"></tbody>
                </table>
            </div>
        </div>

        <?php if ($canViewAll): ?>
        <div id="panel-all" class="card" style="display:none">
            <div class="card-header">
                <div>
                    <div class="card-title">All Payslips</div>
                    <div class="card-sub">Accounts and admin view across staff.</div>
                </div>
            </div>
            <div class="summary-grid" style="margin-bottom:18px">
                <div class="summary-tile">
                    <div class="summary-kicker">Paid</div>
                    <div class="summary-value" id="paidTotal">0</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-kicker">Pending</div>
                    <div class="summary-value" id="pendingTotal">0</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Basic</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="allPayrollBody"></tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canManageAttendance): ?>
        <div id="panel-attendance" class="card" style="display:none">
            <div class="card-header">
                <div>
                    <div class="card-title">Staff Attendance</div>
                    <div class="card-sub">Mark daily attendance used by payroll batch generation.</div>
                </div>
                <div style="display:flex;gap:10px">
                    <input type="date" class="form-control" id="attendanceDate" value="<?= date('Y-m-d') ?>" onchange="loadStaffAttendance()" style="width:170px">
                    <button class="btn btn-primary" onclick="saveStaffAttendance()">Save Register</button>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Employee ID</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceBody"></tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canManageSalarySetup): ?>
        <div id="panel-setup" class="card" style="display:none">
            <div class="card-header">
                <div>
                    <div class="card-title">Salary Setup</div>
                    <div class="card-sub">Set the current salary structure for a staff member.</div>
                </div>
            </div>
            <form id="salarySetupForm" onsubmit="saveSalaryStructure(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Select Staff *</label>
                        <select class="form-control" name="staff_id" id="setupStaff" onchange="loadSalaryStructure()" required>
                            <option value="">Choose staff</option>
                            <?php foreach ($staff as $member): ?>
                            <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?> (<?= role_label($member['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Effective From *</label>
                        <input type="date" class="form-control" name="effective_from" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group"><label class="form-label">Basic Salary</label><input type="number" class="form-control" name="basic_salary" step="0.01" value="0"></div>
                    <div class="form-group"><label class="form-label">HRA</label><input type="number" class="form-control" name="hra" step="0.01" value="0"></div>
                    <div class="form-group"><label class="form-label">DA</label><input type="number" class="form-control" name="da" step="0.01" value="0"></div>
                </div>
                <div class="form-row-3">
                    <div class="form-group"><label class="form-label">Conveyance</label><input type="number" class="form-control" name="conveyance" step="0.01" value="0"></div>
                    <div class="form-group"><label class="form-label">Medical Allowance</label><input type="number" class="form-control" name="medical_allowance" step="0.01" value="0"></div>
                    <div class="form-group"><label class="form-label">Special Allowance</label><input type="number" class="form-control" name="special_allowance" step="0.01" value="0"></div>
                </div>
                <div class="form-row-3">
                    <div class="form-group"><label class="form-label">PF Deduction</label><input type="number" class="form-control" name="pf_deduction" step="0.01" value="0"></div>
                    <div class="form-group"><label class="form-label">ESI Deduction</label><input type="number" class="form-control" name="esi_deduction" step="0.01" value="0"></div>
                    <div class="form-group"><label class="form-label">Other Deductions</label><input type="number" class="form-control" name="other_deductions" step="0.01" value="0"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Tax Deduction</label><input type="number" class="form-control" name="tax_deduction" step="0.01" value="0"></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btn btn-secondary" onclick="resetSalaryStructure()">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Salary Structure</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($canGeneratePayroll): ?>
        <div id="panel-run" class="card" style="display:none">
            <div class="card-header">
                <div>
                    <div class="card-title">Generate Payroll Run</div>
                    <div class="card-sub">Build payroll from saved salary structures and staff attendance.</div>
                </div>
            </div>
            <form id="runForm" onsubmit="generatePayroll(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Month *</label>
                        <select class="form-control" name="month" required>
                            <?php foreach ($months as $monthNumber => $monthLabel): ?>
                            <option value="<?= $monthNumber ?>" <?= $monthNumber === $defaultMonth ? 'selected' : '' ?>><?= $monthLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year *</label>
                        <input type="number" class="form-control" name="year" value="<?= $defaultYear ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Specific Staff (Optional)</label>
                    <select class="form-control" name="staff_id">
                        <option value="">Generate for all eligible staff</option>
                        <?php foreach ($staff as $member): ?>
                        <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?> (<?= role_label($member['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-primary">Execute Run</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const canViewAll = <?= $canViewAll ? 'true' : 'false' ?>;
const canManageAttendance = <?= $canManageAttendance ? 'true' : 'false' ?>;
const canManageSalarySetup = <?= $canManageSalarySetup ? 'true' : 'false' ?>;
const canGeneratePayroll = <?= $canGeneratePayroll ? 'true' : 'false' ?>;
const monthLabels = <?= json_encode($months) ?>;
const staffOptions = <?= json_encode($staff) ?>;

let activeTab = 'my';
let attendanceMap = {};

function currentMonth() {
    return Number(document.getElementById('monthFilter').value);
}

function currentYear() {
    return Number(document.getElementById('yearFilter').value);
}

function periodLabel(record) {
    return `${monthLabels[record.month_number || record.month] || record.month_label || record.month} ${record.year}`;
}

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.page-tab').forEach((button) => button.classList.remove('active'));
    document.getElementById(`tab-${tab}`).classList.add('active');
    document.querySelectorAll('[id^="panel-"]').forEach((panel) => { panel.style.display = 'none'; });
    document.getElementById(`panel-${tab}`).style.display = 'block';
    reloadActiveTab();
}

function reloadActiveTab() {
    if (activeTab === 'my') loadMyPayslips();
    if (activeTab === 'all' && canViewAll) loadAllPayslips();
    if (activeTab === 'attendance' && canManageAttendance) loadStaffAttendance();
}

async function loadMyPayslips() {
    const result = await apiGet(`/api/payroll/index.php?my=1&month=${currentMonth()}&year=${currentYear()}`);
    const rows = Array.isArray(result.data) ? result.data : [];
    document.getElementById('myPayrollBody').innerHTML = rows.length ? rows.map((record) => `
        <tr>
            <td>${escHtml(periodLabel(record))}</td>
            <td>${formatCurrency(record.basic_salary || 0)}</td>
            <td>${formatCurrency(record.allowances || 0)}</td>
            <td>${formatCurrency(record.deductions || 0)}</td>
            <td><strong>${formatCurrency(record.net_salary || 0)}</strong></td>
            <td><span class="badge ${record.status === 'paid' ? 'badge-success' : 'badge-warning'}">${escHtml(record.status || 'pending')}</span></td>
            <td><a class="btn btn-secondary btn-sm" href="/api/pdf/generate.php?action=payslip&id=${record.id}" target="_blank">Download</a></td>
        </tr>
    `).join('') : '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">Pay</div><div class="empty-state-text">No payslips found for the selected month.</div></div></td></tr>';
}

async function loadAllPayslips() {
    const result = await apiGet(`/api/payroll/index.php?month=${currentMonth()}&year=${currentYear()}`);
    const rows = Array.isArray(result.data) ? result.data : [];
    let paid = 0;
    let pending = 0;

    document.getElementById('allPayrollBody').innerHTML = rows.length ? rows.map((record) => {
        const net = Number(record.net_salary || 0);
        if (record.status === 'paid') paid += net; else pending += net;
        return `
            <tr>
                <td>
                    <div style="font-weight:600">${escHtml(record.staff_name || '')}</div>
                    <div style="font-size:11px;color:var(--ink-4)">${escHtml(record.employee_id || '')}</div>
                </td>
                <td>${escHtml(roleLabel(record.role || ''))}</td>
                <td>${formatCurrency(record.basic_salary || 0)}</td>
                <td><strong>${formatCurrency(net)}</strong></td>
                <td><span class="badge ${record.status === 'paid' ? 'badge-success' : 'badge-warning'}">${escHtml(record.status || 'pending')}</span></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <a class="btn btn-secondary btn-sm" href="/api/pdf/generate.php?action=payslip&id=${record.id}" target="_blank">View</a>
                        ${record.status !== 'paid' ? `<button class="btn btn-primary btn-sm" onclick="markPayrollPaid(${record.id})">Mark Paid</button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('') : '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">Payroll</div><div class="empty-state-text">No payroll rows found for the selected month.</div></div></td></tr>';

    document.getElementById('paidTotal').textContent = formatCurrency(paid);
    document.getElementById('pendingTotal').textContent = formatCurrency(pending);
}

async function markPayrollPaid(id) {
    const response = await fetch('/api/payroll/index.php?action=mark_paid', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then((res) => res.json());

    if (response.success) {
        showToast('Payroll marked as paid.');
        loadAllPayslips();
        loadMyPayslips();
        return;
    }
    showToast(response.error || 'Unable to mark payroll as paid.', 'danger');
}

async function loadStaffAttendance() {
    if (!canManageAttendance) return;
    const date = document.getElementById('attendanceDate').value;
    const result = await apiGet(`/api/staff-attendance/index.php?date=${encodeURIComponent(date)}`);
    const rows = Array.isArray(result.data) ? result.data : [];
    attendanceMap = {};
    rows.forEach((row) => { attendanceMap[row.staff_id] = row.status || 'present'; });

    document.getElementById('attendanceBody').innerHTML = rows.length ? rows.map((row) => `
        <tr>
            <td>
                <div style="font-weight:600">${escHtml(row.name || '')}</div>
                <div style="font-size:11px;color:var(--ink-4)">${escHtml(row.department || '')}</div>
            </td>
            <td>${escHtml(row.employee_id || '-')}</td>
            <td>${escHtml(roleLabel(row.role || ''))}</td>
            <td>
                <select class="form-control" style="width:170px" onchange="attendanceMap[${row.staff_id}] = this.value">
                    <option value="not_marked" ${attendanceMap[row.staff_id] === 'not_marked' ? 'selected' : ''}>Not Marked</option>
                    <option value="present" ${attendanceMap[row.staff_id] === 'present' ? 'selected' : ''}>Present</option>
                    <option value="late" ${attendanceMap[row.staff_id] === 'late' ? 'selected' : ''}>Late</option>
                    <option value="half_day" ${attendanceMap[row.staff_id] === 'half_day' ? 'selected' : ''}>Half Day</option>
                    <option value="absent" ${attendanceMap[row.staff_id] === 'absent' ? 'selected' : ''}>Absent</option>
                    <option value="on_leave" ${attendanceMap[row.staff_id] === 'on_leave' ? 'selected' : ''}>On Leave</option>
                </select>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon">Attendance</div><div class="empty-state-text">No staff records available.</div></div></td></tr>';
}

async function saveStaffAttendance() {
    const date = document.getElementById('attendanceDate').value;
    const records = Object.entries(attendanceMap)
        .filter(([, status]) => status && status !== 'not_marked')
        .map(([staff_id, status]) => ({ staff_id, status }));

    const response = await apiPost('/api/staff-attendance/index.php', { date, records });
    if (response.success) {
        showToast(`Attendance saved for ${response.saved} staff members.`);
        loadStaffAttendance();
        return;
    }
    showToast(response.error || 'Unable to save attendance.', 'danger');
}

async function loadSalaryStructure() {
    const staffId = document.getElementById('setupStaff').value;
    if (!staffId) {
        resetSalaryStructure();
        return;
    }

    const result = await apiGet(`/api/salary-setup/index.php?staff_id=${encodeURIComponent(staffId)}`);
    const structure = Array.isArray(result.structures) && result.structures.length ? result.structures[0] : null;
    const form = document.getElementById('salarySetupForm');

    [
        'basic_salary', 'hra', 'da', 'conveyance', 'medical_allowance',
        'special_allowance', 'pf_deduction', 'esi_deduction',
        'tax_deduction', 'other_deductions', 'effective_from'
    ].forEach((field) => {
        if (form.elements[field]) {
            form.elements[field].value = structure?.[field] ?? (field === 'effective_from' ? new Date().toISOString().slice(0, 10) : 0);
        }
    });
}

function resetSalaryStructure() {
    const form = document.getElementById('salarySetupForm');
    form.reset();
    form.elements['effective_from'].value = new Date().toISOString().slice(0, 10);
    [
        'basic_salary', 'hra', 'da', 'conveyance', 'medical_allowance',
        'special_allowance', 'pf_deduction', 'esi_deduction',
        'tax_deduction', 'other_deductions'
    ].forEach((field) => { form.elements[field].value = 0; });
}

async function saveSalaryStructure(event) {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(document.getElementById('salarySetupForm')));
    const response = await apiPost('/api/salary-setup/index.php', payload);
    if (response.success) {
        showToast('Salary structure saved.');
        return;
    }
    showToast(response.error || 'Unable to save salary structure.', 'danger');
}

async function generatePayroll(event) {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(document.getElementById('runForm')));
    const response = await apiPost('/api/payroll/index.php?action=generate_batch', payload);
    if (response.success) {
        showToast(response.message || 'Payroll run completed.');
        if (canViewAll) {
            switchTab('all');
        } else {
            loadMyPayslips();
        }
        return;
    }
    showToast(response.error || 'Unable to generate payroll.', 'danger');
}

document.addEventListener('DOMContentLoaded', () => {
    if (!canViewAll && canManageAttendance) {
        switchTab('attendance');
        return;
    }
    switchTab('my');
});
</script>
</body>
</html>
