<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin', 'hr']);
$pageTitle = 'Salary Setup';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Setup — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-toolbar">
            <div style="font-size:18px;font-weight:700">💵 Salary Setup</div>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Component</button>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Salary Components</div>
            </div>
            <div class="table-wrap">
                <table id="salaryTable">
                    <thead>
                        <tr>
                            <th>Component Name</th>
                            <th>Type</th>
                            <th>Calculation</th>
                            <th>Amount / %</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salaryBody">
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">💵</div><div class="empty-state-text">Loading...</div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">💵 Add Salary Component</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form onsubmit="submitComponent(event)">
            <div class="form-group">
                <label class="form-label">Component Name *</label>
                <input type="text" class="form-control" name="name" placeholder="e.g. Basic Salary, HRA, PF" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-control" name="type">
                        <option value="earning">Earning (+)</option>
                        <option value="deduction">Deduction (-)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Calculation Method</label>
                    <select class="form-control" name="calculation_type" onchange="toggleAmount(this)">
                        <option value="fixed">Fixed Amount</option>
                        <option value="percentage">% of Basic</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Amount / Percentage *</label>
                <input type="number" class="form-control" name="amount" step="0.01" min="0" required placeholder="e.g. 5000 or 12">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2" placeholder="Optional description"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Component</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
async function loadComponents() {
    try {
        const data = await apiGet('/api/salary-setup/index.php');
        const items = Array.isArray(data) ? data : (data.data || []);
        document.getElementById('salaryBody').innerHTML = items.length ? items.map(c => `
            <tr>
                <td><strong>${escHtml(c.name)}</strong></td>
                <td><span class="badge ${c.type === 'earning' ? 'badge-success' : 'badge-danger'}">${c.type === 'earning' ? '+ Earning' : '- Deduction'}</span></td>
                <td>${escHtml(c.calculation_type || 'fixed')}</td>
                <td>${c.calculation_type === 'percentage' ? c.amount + '%' : '₹' + Number(c.amount).toLocaleString()}</td>
                <td><span class="badge ${c.is_active != 0 ? 'badge-success' : 'badge-secondary'}">${c.is_active != 0 ? 'Active' : 'Inactive'}</span></td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="deleteComponent(${c.id})">Delete</button>
                </td>
            </tr>
        `).join('') : '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">💵</div><div class="empty-state-text">No salary components defined yet</div></div></td></tr>';
    } catch(e) {
        document.getElementById('salaryBody').innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">⚠️</div><div class="empty-state-text">Error loading data</div></div></td></tr>';
    }
}

async function submitComponent(e) {
    e.preventDefault();
    const res = await apiPost('/api/salary-setup/index.php', Object.fromEntries(new FormData(e.target)));
    if (res.success || res.id) { showToast('Component added!'); closeModal('addModal'); e.target.reset(); loadComponents(); }
    else showToast(res.error || 'Error saving', 'danger');
}

async function deleteComponent(id) {
    if (!confirm('Delete this salary component?')) return;
    await fetch(`/api/salary-setup/index.php?id=${id}`, {method: 'DELETE'});
    showToast('Deleted'); loadComponents();
}

loadComponents();
</script>
</body>
</html>
