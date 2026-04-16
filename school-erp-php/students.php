<?php
require_once __DIR__ . '/includes/auth.php';

require_auth();
$pageTitle = 'Students';
$classes = db_fetchAll("SELECT id, name FROM classes ORDER BY name ASC");
$canManage = role_matches(get_current_role(), ['superadmin', 'admin']);
$autoOpenAdd = $canManage && (($_GET['action'] ?? '') === 'add');
$bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$categories = ['General', 'OBC', 'SC', 'ST', 'EWS', 'Minority'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .student-id-card {
            width: min(360px, 100%);
            margin: 0 auto;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(79,142,247,0.16), rgba(28,35,51,1));
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .student-id-head {
            padding: 24px 24px 18px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .student-id-body {
            padding: 24px;
            display: grid;
            gap: 12px;
        }
        .student-id-avatar {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.12);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .student-id-field {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding-bottom: 10px;
        }
        .student-id-field:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .student-id-label {
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        .student-id-value {
            color: var(--text-primary);
            text-align: right;
            font-weight: 600;
        }
        .student-id-qr {
            width: 72px;
            height: 72px;
            border: 1px dashed rgba(255,255,255,0.4);
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 11px;
            color: var(--text-muted);
        }
        @media print {
            body * {
                visibility: hidden;
            }
            #studentIdCardPrintArea, #studentIdCardPrintArea * {
                visibility: visible;
            }
            #studentIdCardPrintArea {
                position: fixed;
                inset: 0;
                margin: auto;
                width: 360px;
                height: fit-content;
            }
        }
    </style>
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
                    placeholder="Search by name, admission no, roll no, parent, or class..."
                    style="width:320px"
                    oninput="loadStudents(1)"
                >
                <select class="form-control" id="classFilter" onchange="loadStudents(1)" style="width:180px">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canManage): ?>
            <div class="toolbar-right">
                <button class="btn btn-secondary" onclick="openModal('importModal')">Import CSV</button>
                <button class="btn btn-secondary" onclick="exportData()">Export CSV</button>
                <button class="btn btn-primary" onclick="openStudentModal()">+ Add Student</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div id="tableLoading" style="text-align:center;padding:40px"><div class="spinner"></div></div>
            <div class="table-wrap" id="tableWrap" style="display:none">
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission No</th>
                            <th>Class</th>
                            <th>Parent / Guardian</th>
                            <th>Contact</th>
                            <th>Flags</th>
                            <?php if ($canManage): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="studentBody"></tbody>
                </table>
            </div>
            <div id="pagination" class="pagination"></div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="studentModal">
    <div class="modal" style="max-width:920px">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="studentModalTitle">Add Student</div>
                <div class="section-hint">Admission number auto-generates if left blank.</div>
            </div>
            <button class="modal-close" onclick="closeStudentModal()">X</button>
        </div>

        <form id="studentForm" onsubmit="submitStudent(event)">
            <input type="hidden" name="id" id="studentId">

            <div class="accordion-stack">
                <details class="accordion-section" open>
                    <summary>
                        <span>Student Info</span>
                        <span class="section-hint">Admission, academic, and statutory details</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Admission No</label>
                                <input type="text" class="form-control" name="admission_no">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Roll Number</label>
                                <input type="text" class="form-control" name="roll_number">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Class *</label>
                                <select class="form-control" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Section</label>
                                <input type="text" class="form-control" name="section" placeholder="A / B / C">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Gender *</label>
                                <select class="form-control" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" name="dob" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Aadhaar</label>
                                <input type="text" class="form-control" name="aadhaar" maxlength="20">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Blood Group</label>
                                <select class="form-control" name="blood_group">
                                    <option value="">Select</option>
                                    <?php foreach ($bloodGroups as $group): ?>
                                    <option value="<?= $group ?>"><?= $group ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nationality</label>
                                <input type="text" class="form-control" name="nationality" value="Indian">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Religion</label>
                                <input type="text" class="form-control" name="religion">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select class="form-control" name="category">
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category ?>"><?= $category ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mother Tongue</label>
                                <input type="text" class="form-control" name="mother_tongue">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Parent / Guardian</span>
                        <span class="section-hint">Primary, parental, and emergency contacts</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row-3" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Primary Parent / Guardian</label>
                                <input type="text" class="form-control" name="parent_name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Parent Phone</label>
                                <input type="tel" class="form-control" name="parent_phone">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Parent Email</label>
                                <input type="email" class="form-control" name="parent_email">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Father Name</label>
                                <input type="text" class="form-control" name="father_name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Father Occupation</label>
                                <input type="text" class="form-control" name="father_occupation">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mother Name</label>
                                <input type="text" class="form-control" name="mother_name">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Mother Phone</label>
                                <input type="tel" class="form-control" name="mother_phone">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Link Parent Account</label>
                                <select class="form-control" name="parent_user_id" id="parentUserSelect">
                                    <option value="">None (no portal access)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Guardian Name</label>
                                <input type="text" class="form-control" name="guardian_name">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">Guardian Phone</label>
                                <input type="tel" class="form-control" name="guardian_phone">
                            </div>
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Address</span>
                        <span class="section-hint">Structured address fields for fee, hostel, and transport use</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="form-row" style="margin-top:16px">
                            <div class="form-group">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" name="address_line1">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address_line2">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="form-group">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control" name="pincode">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Legacy Address Summary</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Optional plain-text address"></textarea>
                        </div>
                    </div>
                </details>

                <details class="accordion-section" open>
                    <summary>
                        <span>Medical & Flags</span>
                        <span class="section-hint">Transport, hostel, and health notes</span>
                    </summary>
                    <div class="accordion-body">
                        <div class="checkbox-grid" style="margin:16px 0 18px">
                            <label class="checkbox-tile">
                                <input type="checkbox" name="transport_required" value="1">
                                <span>Transport Required</span>
                            </label>
                            <label class="checkbox-tile">
                                <input type="checkbox" name="hostel_required" value="1">
                                <span>Hostel Required</span>
                            </label>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Medical Conditions</label>
                                <textarea class="form-control" name="medical_conditions" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Allergies</label>
                                <textarea class="form-control" name="allergies" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
                <button type="button" class="btn btn-secondary" onclick="closeStudentModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="studentSubmitBtn">Save Student</button>
            </div>
        </form>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-title">Import Students</div>
                <div class="section-hint">Expected CSV columns: Name, RollNumber, ClassName, Gender, ParentName, Phone, Email, Address</div>
            </div>
            <button class="modal-close" onclick="closeModal('importModal')">X</button>
        </div>
        <form id="importForm" onsubmit="submitImport(event)" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">CSV File *</label>
                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Import</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="studentIdCardModal">
    <div class="modal" id="studentIdCardPrintArea">
        <div class="modal-header">
            <div class="modal-title">Student ID Card</div>
            <button class="modal-close" type="button" onclick="closeModal('studentIdCardModal')">x</button>
        </div>
        <div class="student-id-card" id="studentIdCardContainer"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
            <button class="btn btn-secondary" type="button" onclick="closeModal('studentIdCardModal')">Close</button>
            <button class="btn btn-primary" type="button" onclick="window.print()">Print</button>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const canManage = <?= $canManage ? 'true' : 'false' ?>;
const autoOpenAdd = <?= $autoOpenAdd ? 'true' : 'false' ?>;
const schoolName = <?= json_encode(defined('APP_NAME') ? APP_NAME : 'School ERP') ?>;
let currentPage = 1;
let editingId = null;
let currentStudents = [];
let parentUsers = [];
let parentUsersLoaded = false;

const studentFields = [
    'name', 'admission_no', 'class_id', 'section', 'roll_number', 'dob', 'gender',
    'phone', 'email', 'parent_name', 'parent_phone', 'parent_email', 'father_name',
    'father_occupation', 'mother_name', 'mother_phone', 'parent_user_id', 'guardian_name', 'guardian_phone',
    'aadhaar', 'blood_group', 'nationality', 'religion', 'category', 'mother_tongue',
    'address', 'address_line1', 'address_line2', 'city', 'state', 'pincode',
    'medical_conditions', 'allergies'
];

function defaultStudentValues() {
    return {
        nationality: 'Indian',
        category: 'General',
        transport_required: false,
        hostel_required: false
    };
}

function setFormValue(name, value) {
    const field = document.querySelector(`#studentForm [name="${name}"]`);
    if (!field) return;
    if (field.type === 'checkbox') {
        field.checked = value === 1 || value === '1' || value === true || value === 'true';
        return;
    }
    field.value = value ?? '';
}

function resetStudentForm() {
    editingId = null;
    document.getElementById('studentForm').reset();
    document.getElementById('studentId').value = '';
    Object.entries(defaultStudentValues()).forEach(([name, value]) => setFormValue(name, value));
    setFormValue('parent_user_id', '');
    document.getElementById('studentModalTitle').textContent = 'Add Student';
    document.getElementById('studentSubmitBtn').textContent = 'Save Student';
}

function populateStudentForm(student) {
    resetStudentForm();
    editingId = student.id;
    document.getElementById('studentId').value = student.id;
    studentFields.forEach((field) => setFormValue(field, student[field]));
    setFormValue('transport_required', student.transport_required);
    setFormValue('hostel_required', student.hostel_required);
    document.getElementById('studentModalTitle').textContent = 'Edit Student';
    document.getElementById('studentSubmitBtn').textContent = 'Update Student';
}

async function loadParentUsers(selectedId = '') {
    if (!parentUsersLoaded) {
        const response = await apiGet('/api/users/index.php?role=parent&limit=500');
        parentUsers = Array.isArray(response.users) ? response.users : [];
        parentUsersLoaded = true;
    }

    const select = document.getElementById('parentUserSelect');
    select.innerHTML = '<option value="">None (no portal access)</option>' + parentUsers.map((user) => `
        <option value="${user.id}">${escHtml(user.name || 'Parent')} ${user.email ? `(${escHtml(user.email)})` : ''}</option>
    `).join('');
    select.value = selectedId || '';
}

async function openStudentModal(student = null) {
    if (!canManage) return;
    if (student) {
        populateStudentForm(student);
    } else {
        resetStudentForm();
    }
    await loadParentUsers(student?.parent_user_id || '');
    openModal('studentModal');
}

function closeStudentModal() {
    closeModal('studentModal');
}

function studentPayloadFromForm() {
    const form = document.getElementById('studentForm');
    const data = Object.fromEntries(new FormData(form));
    data.transport_required = form.querySelector('[name="transport_required"]').checked ? '1' : '0';
    data.hostel_required = form.querySelector('[name="hostel_required"]').checked ? '1' : '0';
    if (editingId) {
        data.id = editingId;
    }
    return data;
}

async function loadStudents(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const classId = document.getElementById('classFilter').value;
    document.getElementById('tableLoading').style.display = 'block';
    document.getElementById('tableWrap').style.display = 'none';

    const data = await apiGet(`/api/students/index.php?page=${page}&search=${encodeURIComponent(search)}&class_id=${encodeURIComponent(classId)}`);
    const rows = Array.isArray(data.data) ? data.data : [];
    currentStudents = rows;
    const body = document.getElementById('studentBody');
    const colspan = canManage ? 7 : 6;

    if (!rows.length) {
        body.innerHTML = `<tr><td colspan="${colspan}"><div class="empty-state"><div class="empty-state-icon">Students</div><div class="empty-state-text">No student records found.</div></div></td></tr>`;
    } else {
        body.innerHTML = rows.map((student) => {
            const admissionNo = escHtml(student.admission_no || '-');
            const classLabel = [student.class_name || '-', student.section || ''].filter(Boolean).join(' · ');
            const parentLabel = escHtml(student.parent_name || student.guardian_name || student.father_name || '-');
            const contactLabel = escHtml(student.parent_phone || student.phone || '-');
            const flags = [
                student.transport_required == 1 ? '<span class="chip">Transport</span>' : '',
                student.hostel_required == 1 ? '<span class="chip">Hostel</span>' : '',
                student.blood_group ? `<span class="chip">${escHtml(student.blood_group)}</span>` : ''
            ].filter(Boolean).join('');

            return `
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="user-avatar" style="width:34px;height:34px;font-size:13px">${escHtml((student.name || 'S').charAt(0).toUpperCase())}</div>
                            <div>
                                <div style="font-weight:600">${escHtml(student.name || '')}</div>
                                <div style="font-size:11px;color:var(--ink-4)">${escHtml(student.gender || '-')}${student.roll_number ? ` · Roll ${escHtml(student.roll_number)}` : ''}</div>
                            </div>
                        </div>
                    </td>
                    <td>${admissionNo}</td>
                    <td>${escHtml(classLabel)}</td>
                    <td>${parentLabel}</td>
                    <td>
                        <div>${contactLabel}</div>
                        <div style="font-size:11px;color:var(--ink-4)">${escHtml(student.parent_email || student.email || '')}</div>
                    </td>
                    <td><div class="chip-list">${flags || '<span class="chip">No flags</span>'}</div></td>
                    ${canManage ? `
                    <td>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-secondary btn-sm" onclick="openStudentIdCard(${student.id})">ID Card</button>
                            <button class="btn btn-secondary btn-sm" onclick="editStudent(${student.id})">Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="archiveStudent(${student.id}, ${JSON.stringify(student.name || '')})">Archive</button>
                        </div>
                    </td>` : ''}
                </tr>
            `;
        }).join('');
    }

    let paginationHtml = '';
    const totalPages = Number(data.pages || 1);
    if (totalPages > 1) {
        for (let index = 1; index <= totalPages; index += 1) {
            paginationHtml += `<a href="#" onclick="loadStudents(${index}); return false;" class="${index === currentPage ? 'current' : ''}">${index}</a>`;
        }
    }
    document.getElementById('pagination').innerHTML = paginationHtml;
    document.getElementById('tableLoading').style.display = 'none';
    document.getElementById('tableWrap').style.display = 'block';
}

async function editStudent(id) {
    const student = await apiGet(`/api/students/index.php?id=${id}`);
    if (student.error) {
        showToast(student.error, 'danger');
        return;
    }
    openStudentModal(student);
}

async function submitStudent(event) {
    event.preventDefault();
    if (!canManage) return;

    const payload = studentPayloadFromForm();
    let response;
    if (editingId) {
        response = await apiPut('/api/students/index.php', payload);
    } else {
        response = await apiPost('/api/students/index.php', payload);
    }

    if (response.success) {
        showToast(editingId ? 'Student updated successfully.' : 'Student created successfully.');
        closeStudentModal();
        resetStudentForm();
        loadStudents(editingId ? currentPage : 1);
        return;
    }

    showToast(response.error || 'Unable to save student record.', 'danger');
}

async function archiveStudent(id, name) {
    if (!confirm(`Archive student "${name}"?`)) return;
    const reason = prompt('Discharge reason (optional):', '') || '';
    const response = await fetch('/api/students/index.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify({
            id,
            discharge_date: new Date().toISOString().slice(0, 10),
            discharge_reason: reason
        })
    }).then((res) => res.json());

    if (response.success) {
        showToast('Student archived successfully.');
        loadStudents(currentPage);
        return;
    }

    showToast(response.error || 'Unable to archive student.', 'danger');
}

function openStudentIdCard(studentId) {
    const student = currentStudents.find((row) => Number(row.id) === Number(studentId));
    if (!student) {
        showToast('Student record not loaded yet.', 'warning');
        return;
    }

    const classLabel = [student.class_name || 'No Class', student.section || ''].filter(Boolean).join(' - ');
    document.getElementById('studentIdCardContainer').innerHTML = `
        <div class="student-id-head">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start">
                <div>
                    <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em">${escHtml(schoolName)}</div>
                    <div class="student-id-avatar">${escHtml((student.name || 'S').charAt(0).toUpperCase())}</div>
                    <div style="font-size:24px;font-weight:800">${escHtml(student.name || 'Student')}</div>
                    <div style="color:var(--text-muted);margin-top:6px">${escHtml(classLabel)}</div>
                </div>
                <div class="student-id-qr">QR</div>
            </div>
        </div>
        <div class="student-id-body">
            <div class="student-id-field">
                <div class="student-id-label">Admission No</div>
                <div class="student-id-value">${escHtml(student.admission_no || 'Pending')}</div>
            </div>
            <div class="student-id-field">
                <div class="student-id-label">Roll Number</div>
                <div class="student-id-value">${escHtml(student.roll_number || '-')}</div>
            </div>
            <div class="student-id-field">
                <div class="student-id-label">Date of Birth</div>
                <div class="student-id-value">${escHtml(student.dob || '-')}</div>
            </div>
            <div class="student-id-field">
                <div class="student-id-label">Blood Group</div>
                <div class="student-id-value">${escHtml(student.blood_group || '-')}</div>
            </div>
            <div class="student-id-field">
                <div class="student-id-label">Parent / Guardian</div>
                <div class="student-id-value">${escHtml(student.parent_name || student.guardian_name || student.father_name || '-')}</div>
            </div>
        </div>
    `;
    openModal('studentIdCardModal');
}

function exportData() {
    const classId = document.getElementById('classFilter').value;
    window.location.href = `/api/students/export.php?class_id=${encodeURIComponent(classId)}`;
}

async function submitImport(event) {
    event.preventDefault();
    const formData = new FormData(document.getElementById('importForm'));
    const response = await fetch('/api/students/import.php', {
        method: 'POST',
        body: formData
    }).then((res) => res.json());

    if (response.success) {
        showToast(`Import complete: ${response.imported} imported, ${response.errors} errors.`);
        closeModal('importModal');
        document.getElementById('importForm').reset();
        loadStudents(1);
        return;
    }

    showToast(response.error || 'Import failed.', 'danger');
}

document.addEventListener('DOMContentLoaded', () => {
    loadStudents();
    if (autoOpenAdd) {
        openStudentModal();
    }
});
</script>
</body>
</html>
