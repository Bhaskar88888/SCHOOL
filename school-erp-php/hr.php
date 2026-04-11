<?php
require_once __DIR__ . '/includes/auth.php';

require_auth();
require_role(['superadmin', 'admin', 'hr']);
$pageTitle = 'HR & Staff';
$departments = db_fetchAll("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
$staffRoles = ['teacher', 'staff', 'admin', 'hr', 'accounts', 'librarian', 'canteen', 'conductor', 'driver'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR & Staff - School ERP</title>
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
                <input
                    type="text"
                    class="form-control"
                    id="searchInput"
                    placeholder="Search by name, email, phone, or employee ID..."
                    style="width:320px"
                    oninput="loadStaff()"
                >
                <select class="form-control" id="roleFilter" onchange="loadStaff()" style="width:170px">
                    <option value="">All Roles</option>
                    <?php foreach ($staffRoles as $role): ?>
                    <option value="<?= $role ?>"><?= role_label($role) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-control" id="departmentFilter" onchange="loadStaff()" style="width:180px">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                    <option value="<?= htmlspecialchars($department['department']) ?>"><?= htmlspecialchars($department['department']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openStaffModal()">+ Add Staff Member</button>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Employee ID</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Joining Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="staffModal">
    <div class="modal" style="max-width:980px">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="staffModalTitle">Add Staff Member</div>
                <div class="section-hint">Employee ID auto-generates if left blank.</div>
            </div>
            <button class="modal-close" onclick="closeStaffModal()">X</button>
        </div>

        <form id="staffForm" onsubmit="submitStaff(event)">
            <input type="hidden" name="id" id="staffId">

            <div class="accordion-stack">
                <details class="accordion-section" open>
                    <summary>
                        <span>Employment</span>
                        <span class="section-hint">Core profile, role, and joining details</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Official Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password <span id="passwordHint">*</span></label>
                                <input type="password" class="form-control" name="password" id="staffPassword">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Employee ID</label>
                                <input type="text" class="form-control" name="employee_id">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role *</label>
                                <select class="form-control" name="role" required>
                                    <?php foreach ($staffRoles as $role): ?>
                                    <option value="<?= $role ?>"><?= role_label($role) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Employment Type</label>
                                <select class="form-control" name="employment_type">
                                    <option value="permanent">Permanent</option>
                                    <option value="contractual">Contractual</option>
                                    <option value="part-time">Part-Time</option>
                                    <option value="visiting">Visiting</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" name="department">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Designation</label>
                                <input type="text" class="form-control" name="designation">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Joining Date</label>
                            <input type="date" class="form-control" name="joining_date">
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Personal & Statutory</span>
                        <span class="section-hint">Identity, qualification, and compliance fields</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select class="form-control" name="gender">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Blood Group</label>
                                <select class="form-control" name="blood_group">
                                    <option value="">Select</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Highest Qualification</label>
                                <input type="text" class="form-control" name="highest_qualification">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Experience (Years)</label>
                                <input type="number" class="form-control" name="experience_years" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Aadhaar</label>
                                <input type="text" class="form-control" name="aadhaar" maxlength="20">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">PAN</label>
                            <input type="text" class="form-control" name="pan" maxlength="20">
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Address & Emergency</span>
                        <span class="section-hint">Staff address and emergency contact details</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control" name="pincode">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" name="address_line1">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address_line2">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="form-group">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Salary Structure</span>
                        <span class="section-hint">Payroll components used by salary setup and batch generation</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" name="basic_salary" step="0.01" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">HRA</label>
                                <input type="number" class="form-control" name="hra" step="0.01" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">DA</label>
                                <input type="number" class="form-control" name="da" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Conveyance</label>
                                <input type="number" class="form-control" name="conveyance" step="0.01" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Medical Allowance</label>
                                <input type="number" class="form-control" name="medical_allowance" step="0.01" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Special Allowance</label>
                                <input type="number" class="form-control" name="special_allowance" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">PF Deduction</label>
                                <input type="number" class="form-control" name="pf_deduction" step="0.01" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">ESI Deduction</label>
                                <input type="number" class="form-control" name="esi_deduction" step="0.01" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tax Deduction</label>
                                <input type="number" class="form-control" name="tax_deduction" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Banking</span>
                        <span class="section-hint">Payout account details</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number">
                            </div>
                            <div class="form-group">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" class="form-control" name="ifsc_code">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Leave Allocation</span>
                        <span class="section-hint">Default balances and internal HR notes</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Casual Leave</label>
                                <input type="number" class="form-control" name="casual_leave_balance" value="12">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Earned Leave</label>
                                <input type="number" class="form-control" name="earned_leave_balance" value="15">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sick Leave</label>
                                <input type="number" class="form-control" name="sick_leave_balance" value="10">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">HR Notes</label>
                            <textarea class="form-control" name="hr_notes" rows="3"></textarea>
                        </div>
                    </div>
                </details>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="staffSubmitBtn">Save Staff</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let staffRecords = [];
let editingId = null;

const staffFields = [
    'name', 'email', 'phone', 'role', 'employee_id', 'employment_type', 'department',
    'designation', 'joining_date', 'date_of_birth', 'gender', 'blood_group',
    'highest_qualification', 'experience_years', 'aadhaar', 'pan',
    'emergency_contact_name', 'emergency_contact_phone', 'address_line1',
    'address_line2', 'city', 'state', 'pincode', 'basic_salary', 'hra', 'da',
    'conveyance', 'medical_allowance', 'special_allowance', 'pf_deduction',
    'esi_deduction', 'tax_deduction', 'bank_name', 'account_number', 'ifsc_code',
    'casual_leave_balance', 'earned_leave_balance', 'sick_leave_balance', 'hr_notes'
];

function defaultStaffValues() {
    return {
        role: 'teacher',
        employment_type: 'permanent',
        experience_years: 0,
        basic_salary: 0,
        hra: 0,
        da: 0,
        conveyance: 0,
        medical_allowance: 0,
        special_allowance: 0,
        pf_deduction: 0,
        esi_deduction: 0,
        tax_deduction: 0,
        casual_leave_balance: 12,
        earned_leave_balance: 15,
        sick_leave_balance: 10
    };
}

function setStaffField(name, value) {
    const field = document.querySelector(`#staffForm [name="${name}"]`);
    if (!field) return;
    field.value = value ?? '';
}

function normalizeStaffRecord(record) {
    const normalized = { ...record };
    if (record.staff_address) {
        try {
            const address = typeof record.staff_address === 'string' ? JSON.parse(record.staff_address) : record.staff_address;
            normalized.address_line1 = normalized.address_line1 || address.line1 || '';
            normalized.address_line2 = normalized.address_line2 || address.line2 || '';
            normalized.city = normalized.city || address.city || '';
            normalized.state = normalized.state || address.state || '';
            normalized.pincode = normalized.pincode || address.pincode || '';
        } catch (error) {
            // Ignore malformed legacy JSON.
        }
    }
    ['joining_date', 'date_of_birth'].forEach((field) => {
        if (normalized[field]) {
            normalized[field] = String(normalized[field]).slice(0, 10);
        }
    });
    return normalized;
}

function resetStaffForm() {
    editingId = null;
    document.getElementById('staffForm').reset();
    document.getElementById('staffId').value = '';
    Object.entries(defaultStaffValues()).forEach(([field, value]) => setStaffField(field, value));
    document.getElementById('staffPassword').required = true;
    document.getElementById('passwordHint').textContent = '*';
    document.getElementById('staffModalTitle').textContent = 'Add Staff Member';
    document.getElementById('staffSubmitBtn').textContent = 'Save Staff';
}

function openStaffModal(record = null) {
    resetStaffForm();
    if (record) {
        const staff = normalizeStaffRecord(record);
        editingId = staff.id;
        document.getElementById('staffId').value = staff.id;
        staffFields.forEach((field) => setStaffField(field, staff[field]));
        document.getElementById('staffPassword').required = false;
        document.getElementById('passwordHint').textContent = '(leave blank to keep current)';
        document.getElementById('staffModalTitle').textContent = 'Edit Staff Member';
        document.getElementById('staffSubmitBtn').textContent = 'Update Staff';
    } else {
        const today = new Date().toISOString().slice(0, 10);
        setStaffField('joining_date', today);
    }
    openModal('staffModal');
}

function closeStaffModal() {
    closeModal('staffModal');
}

async function loadStaff() {
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const department = document.getElementById('departmentFilter').value;
    const result = await apiGet(`/api/hr/index.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&department=${encodeURIComponent(department)}`);
    staffRecords = Array.isArray(result) ? result : [];

    document.getElementById('staffBody').innerHTML = staffRecords.length ? staffRecords.map((record) => {
        const joinedOn = record.joining_date || record.created_at || '';
        return `
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:12px">${escHtml((record.name || 'U').charAt(0).toUpperCase())}</div>
                        <div>
                            <div style="font-weight:600">${escHtml(record.name || '')}</div>
                            <div style="font-size:11px;color:var(--ink-4)">${escHtml(record.email || '')}</div>
                        </div>
                    </div>
                </td>
                <td>${escHtml(record.employee_id || '-')}</td>
                <td><span class="badge badge-info">${escHtml(roleLabel(record.role))}</span></td>
                <td>${escHtml(record.department || '-')}</td>
                <td>${escHtml(record.designation || '-')}</td>
                <td>${joinedOn ? new Date(joinedOn).toLocaleDateString('en-IN') : '-'}</td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-secondary btn-sm" onclick="editStaff(${record.id})">Edit</button>
                        ${record.role !== 'superadmin' ? `<button class="btn btn-danger btn-sm" onclick="archiveStaff(${record.id}, ${JSON.stringify(record.name || '')})">Archive</button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('') : '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">HR</div><div class="empty-state-text">No staff records found.</div></div></td></tr>';
}

async function editStaff(id) {
    const record = staffRecords.find((item) => Number(item.id) === Number(id));
    if (!record) return;
    openStaffModal(record);
}

function formPayload() {
    return Object.fromEntries(new FormData(document.getElementById('staffForm')));
}

async function submitStaff(event) {
    event.preventDefault();
    const payload = formPayload();
    let response;

    if (editingId) {
        payload.id = editingId;
        response = await fetch('/api/hr/index.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then((res) => res.json());
    } else {
        response = await apiPost('/api/hr/index.php', payload);
    }

    if (response.success) {
        showToast(editingId ? 'Staff record updated.' : 'Staff member created.');
        closeStaffModal();
        resetStaffForm();
        loadStaff();
        return;
    }

    showToast(response.error || 'Unable to save staff member.', 'danger');
}

async function archiveStaff(id, name) {
    if (!confirm(`Archive "${name}"?`)) return;
    const response = await fetch(`/api/hr/index.php?id=${id}`, { method: 'DELETE' }).then((res) => res.json());
    if (response.success) {
        showToast('Staff member archived.');
        loadStaff();
        return;
    }
    showToast(response.error || 'Unable to archive staff member.', 'danger');
}

document.addEventListener('DOMContentLoaded', () => {
    resetStaffForm();
    loadStaff();
});
</script>
</body>
</html>
