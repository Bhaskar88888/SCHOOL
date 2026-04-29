<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin', 'accounts', 'accountant']);
$pageTitle = 'Fee Structures';
$classes   = db_fetchAll("SELECT id, name FROM classes ORDER BY name ASC");
$years     = [];
$y         = (int) date('Y');
for ($i = $y - 1; $i <= $y + 1; $i++) {
    $years[] = $i . '-' . ($i + 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structures — School ERP</title>
    <meta name="description" content="Define class-wise fee structures and auto-generate invoices for all students.">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .fs-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; }
        .fs-card { background:var(--surface-container-lowest); border:1px solid rgba(172,179,180,.15); border-radius:14px; padding:20px; }
        .fs-card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
        .fs-card-title { font-weight:700; font-size:15px; }
        .fs-card-class { font-size:12px; color:var(--text-muted); margin-top:2px; }
        .fs-amount { font-size:24px; font-weight:700; color:var(--accent); margin:8px 0; }
        .fs-meta { font-size:12px; color:var(--text-secondary); display:flex; gap:12px; flex-wrap:wrap; margin-top:8px; }
        .fs-actions { display:flex; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid rgba(172,179,180,.12); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-hero">
            <div class="hero-content">
                <h1>Fee Structures</h1>
                <p>Define standard fees per class and academic year. Auto-generate invoices for all students at once.</p>
            </div>
        </div>

        <div class="page-toolbar" style="margin-top:-8px">
            <div class="toolbar-left">
                <select class="form-control" id="filterClass" onchange="loadStructures()" style="width:200px">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-control" id="filterYear" onchange="loadStructures()" style="width:160px">
                    <?php foreach ($years as $yr): ?>
                    <option value="<?= $yr ?>" <?= $yr === $y . '-' . ($y + 1) ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Fee Structure</button>
            </div>
        </div>

        <div class="fs-grid" id="structuresGrid">
            <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="addModalTitle">💰 Add Fee Structure</div>
            <button class="modal-close" type="button" onclick="closeModal('addModal')">✕</button>
        </div>
        <form id="addForm" onsubmit="submitStructure(event)">
            <input type="hidden" id="editId" name="id" value="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Class *</label>
                    <select class="form-control" name="class_id" id="formClass" required>
                        <option value="">Select Class…</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Academic Year *</label>
                    <select class="form-control" name="academic_year" id="formYear">
                        <?php foreach ($years as $yr): ?>
                        <option value="<?= $yr ?>" <?= $yr === $y . '-' . ($y + 1) ? 'selected' : '' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Fee Type *</label>
                    <select class="form-control" name="fee_type" required>
                        <option>Tuition Fee</option>
                        <option>Exam Fee</option>
                        <option>Transport Fee</option>
                        <option>Hostel Fee</option>
                        <option>Library Fee</option>
                        <option>Activity Fee</option>
                        <option>Development Fee</option>
                        <option>Miscellaneous</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Term</label>
                    <select class="form-control" name="term">
                        <option value="Annual">Annual</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Half-Yearly">Half-Yearly</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Amount (₹) *</label>
                    <input type="number" class="form-control" name="amount" step="0.01" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Late Fee (₹)</label>
                    <input type="number" class="form-control" name="late_fee" step="0.01" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-control" name="due_date">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2" placeholder="Optional notes…"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Structure</button>
            </div>
        </form>
    </div>
</div>

<!-- Generate Invoices Modal -->
<div class="modal-overlay" id="genModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">⚡ Generate Fee Invoices</div>
            <button class="modal-close" type="button" onclick="closeModal('genModal')">✕</button>
        </div>
        <div style="padding:8px 0 20px">
            <p style="color:var(--text-secondary);line-height:1.6" id="genDesc"></p>
            <div style="background:rgba(99,102,241,.08);border-radius:10px;padding:14px;margin-top:12px">
                <div style="font-size:13px;color:var(--text-secondary)">
                    This will create a pending fee invoice for <strong>every active student</strong> in this class who does not already have an invoice for this fee type in the current year. Existing invoices will not be duplicated.
                </div>
            </div>
        </div>
        <input type="hidden" id="genStructureId">
        <input type="hidden" id="genClassId">
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button type="button" class="btn btn-secondary" onclick="closeModal('genModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmGenerate()">⚡ Generate Now</button>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let structures = [];

async function loadStructures() {
    const classId = document.getElementById('filterClass').value;
    const year    = document.getElementById('filterYear').value;
    const qs      = new URLSearchParams({ ...(classId ? { class_id: classId } : {}), ...(year ? { academic_year: year } : {}) });
    const grid    = document.getElementById('structuresGrid');

    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)"><div class="spinner"></div></div>';
    const data = await apiGet('/api/fee/structures.php?' + qs.toString());
    structures = data.structures || [];

    if (!structures.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)">No fee structures defined yet. Click <strong>Add Fee Structure</strong> to get started.</div>';
        return;
    }

    grid.innerHTML = structures.map(s => `
        <div class="fs-card">
            <div class="fs-card-header">
                <div>
                    <div class="fs-card-title">${escHtml(s.fee_type)}</div>
                    <div class="fs-card-class">📚 ${escHtml(s.class_name || '-')} &nbsp;·&nbsp; ${escHtml(s.academic_year)}</div>
                </div>
                <span class="badge badge-info" style="font-size:11px">${escHtml(s.term || 'Annual')}</span>
            </div>
            <div class="fs-amount">₹${parseFloat(s.amount).toLocaleString('en-IN')}</div>
            <div class="fs-meta">
                ${s.due_date ? `<span>📅 Due: ${new Date(s.due_date).toLocaleDateString('en-IN')}</span>` : ''}
                ${parseFloat(s.late_fee) > 0 ? `<span>⚠️ Late fee: ₹${parseFloat(s.late_fee).toLocaleString('en-IN')}</span>` : ''}
                ${s.description ? `<span title="${escHtml(s.description)}">📝 ${escHtml(s.description.substring(0, 40))}${s.description.length > 40 ? '…' : ''}</span>` : ''}
            </div>
            <div class="fs-actions">
                <button class="btn btn-primary btn-sm" style="flex:1" onclick="openGenerateModal(${s.id}, ${s.class_id}, '${escHtml(s.fee_type)}', '${escHtml(s.class_name)}')">⚡ Generate Invoices</button>
                <button class="btn btn-secondary btn-sm" onclick="editStructure(${s.id})">✏️</button>
                <button class="btn btn-danger btn-sm" onclick="deleteStructure(${s.id})">🗑️</button>
            </div>
        </div>
    `).join('');
}

function openAddModal() {
    document.getElementById('addModalTitle').textContent = '💰 Add Fee Structure';
    document.getElementById('editId').value = '';
    document.getElementById('addForm').reset();
    openModal('addModal');
}

function editStructure(id) {
    const s = structures.find(x => x.id == id);
    if (!s) return;
    document.getElementById('addModalTitle').textContent = '✏️ Edit Fee Structure';
    document.getElementById('editId').value = s.id;
    const f = document.getElementById('addForm');
    f.class_id.value      = s.class_id;
    f.academic_year.value = s.academic_year;
    f.fee_type.value      = s.fee_type;
    f.term.value          = s.term || 'Annual';
    f.amount.value        = s.amount;
    f.late_fee.value      = s.late_fee || 0;
    f.due_date.value      = s.due_date ? s.due_date.substring(0, 10) : '';
    f.description.value   = s.description || '';
    openModal('addModal');
}

async function submitStructure(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const isEdit = !!data.id;
    const res = isEdit
        ? await apiPut('/api/fee/structures.php', data)
        : await apiPost('/api/fee/structures.php', data);

    if (res.success) {
        showToast(isEdit ? 'Fee structure updated!' : 'Fee structure created!');
        closeModal('addModal');
        loadStructures();
    } else {
        showToast(res.error || 'Failed', 'danger');
    }
}

async function deleteStructure(id) {
    if (!confirm('Delete this fee structure? Existing invoices will not be affected.')) return;
    const res = await apiDelete('/api/fee/structures.php?id=' + id);
    if (res.success) { showToast('Deleted'); loadStructures(); }
    else showToast(res.error || 'Failed', 'danger');
}

function openGenerateModal(structureId, classId, feeType, className) {
    document.getElementById('genStructureId').value = structureId;
    document.getElementById('genClassId').value     = classId;
    document.getElementById('genDesc').innerHTML    = `Generate <strong>${escHtml(feeType)}</strong> invoices for all active students in <strong>${escHtml(className)}</strong>.`;
    openModal('genModal');
}

async function confirmGenerate() {
    const structureId = document.getElementById('genStructureId').value;
    const classId     = document.getElementById('genClassId').value;
    const res = await apiPost('/api/fee/structures.php', { action: 'generate_invoices', structure_id: structureId, class_id: classId });
    if (res.success) {
        showToast(`✅ Generated ${res.generated} invoice${res.generated !== 1 ? 's' : ''}!`);
        closeModal('genModal');
    } else {
        showToast(res.error || 'Failed to generate invoices', 'danger');
    }
}

// Override the Add button in toolbar to reset properly
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[onclick="openModal(\'addModal\')"]').onclick = openAddModal;
    loadStructures();
});
</script>
</body>
</html>
