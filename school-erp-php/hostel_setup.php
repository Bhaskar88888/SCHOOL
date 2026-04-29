<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);
$pageTitle = 'Hostel Setup';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Setup — School ERP</title>
    <meta name="description" content="Configure hostel room types and fee structures for dynamic allocation.">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .rt-card { background:var(--surface-container-lowest); border:1px solid rgba(172,179,180,.15); border-radius:14px; padding:20px; }
        .rt-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .rt-title { font-weight:700; font-size:15px; }
        .rt-badge { display:inline-flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
        .amenity-tag { background:rgba(99,102,241,.1); color:var(--accent); border-radius:999px; padding:2px 10px; font-size:11px; font-weight:600; }
        .tabs { display:flex; gap:12px; border-bottom:1px solid var(--border); margin-bottom:24px; }
        .tab { padding:10px 16px; cursor:pointer; color:var(--text-secondary); font-weight:600; border-bottom:2px solid transparent; }
        .tab.active { color:var(--accent); border-bottom:2px solid var(--accent); }
        .fs-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-hero">
            <div class="hero-content">
                <h1>Hostel Setup</h1>
                <p>Configure room types and their fee structures. These are used when adding rooms and allocating students.</p>
            </div>
        </div>

        <div class="tabs">
            <div class="tab active" id="tabRoomTypes" onclick="switchTab('roomtypes')">🛏️ Room Types</div>
            <div class="tab" id="tabFeeStructures" onclick="switchTab('feestructures')">💰 Hostel Fee Structures</div>
        </div>

        <!-- Room Types -->
        <div id="viewRoomTypes">
            <div class="page-toolbar" style="margin-bottom:16px">
                <div style="font-weight:600">Room Type Catalog</div>
                <button class="btn btn-primary" onclick="openModal('addRoomTypeModal')">+ Add Room Type</button>
            </div>
            <div class="fs-grid" id="roomTypesGrid">
                <div style="grid-column:1/-1;text-align:center;padding:40px"><div class="spinner"></div></div>
            </div>
        </div>

        <!-- Hostel Fee Structures -->
        <div id="viewFeeStructures" style="display:none">
            <div class="page-toolbar" style="margin-bottom:16px">
                <div style="font-weight:600">Hostel Fee Structures</div>
                <button class="btn btn-primary" onclick="openModal('addHostelFeeModal')">+ Add Fee Structure</button>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Room Type</th><th>Academic Year</th><th>Billing</th><th>Amount</th><th>Mess Charge</th><th>Caution Deposit</th><th>Actions</th></tr></thead>
                        <tbody id="hostelFeeBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Room Type Modal -->
<div class="modal-overlay" id="addRoomTypeModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">🛏️ Add Room Type</div>
            <button class="modal-close" type="button" onclick="closeModal('addRoomTypeModal')">✕</button>
        </div>
        <form id="roomTypeForm" onsubmit="submitRoomType(event)">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Type Name *</label>
                    <input type="text" class="form-control" name="name" required placeholder="e.g. Single Room">
                </div>
                <div class="form-group">
                    <label class="form-label">Occupancy (beds) *</label>
                    <input type="number" class="form-control" name="occupancy" value="2" min="1" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Gender Policy</label>
                    <select class="form-control" name="gender_policy">
                        <option value="separate">Separate (Boys / Girls)</option>
                        <option value="co-ed">Co-ed</option>
                        <option value="boys">Boys Only</option>
                        <option value="girls">Girls Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Default Fee (₹/month)</label>
                    <input type="number" class="form-control" name="default_fee" value="0" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Amenities (comma-separated)</label>
                <input type="text" class="form-control" name="amenities" placeholder="AC, Attached Bathroom, Study Table">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRoomTypeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Room Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Hostel Fee Structure Modal -->
<div class="modal-overlay" id="addHostelFeeModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">💰 Add Hostel Fee Structure</div>
            <button class="modal-close" type="button" onclick="closeModal('addHostelFeeModal')">✕</button>
        </div>
        <form id="hostelFeeForm" onsubmit="submitHostelFee(event)">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Room Type *</label>
                    <select class="form-control" name="room_type_id" id="feeRoomTypeSelect" required>
                        <option value="">Select Type…</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Academic Year *</label>
                    <input type="text" class="form-control" name="academic_year" value="<?= date('Y') . '-' . (date('Y') + 1) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Billing Cycle</label>
                    <select class="form-control" name="billing_cycle">
                        <option>Monthly</option><option>Quarterly</option><option>Half-Yearly</option><option>Annual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (₹) *</label>
                    <input type="number" class="form-control" name="amount" step="0.01" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Mess Charge (₹)</label>
                    <input type="number" class="form-control" name="mess_charge" value="0" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Caution Deposit (₹)</label>
                    <input type="number" class="form-control" name="caution_deposit" value="0" step="0.01">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addHostelFeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Fee Structure</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let roomTypes = [];

function switchTab(tab) {
    document.getElementById('viewRoomTypes').style.display    = tab === 'roomtypes'     ? 'block' : 'none';
    document.getElementById('viewFeeStructures').style.display = tab === 'feestructures' ? 'block' : 'none';
    document.getElementById('tabRoomTypes').className    = 'tab' + (tab === 'roomtypes'     ? ' active' : '');
    document.getElementById('tabFeeStructures').className = 'tab' + (tab === 'feestructures' ? ' active' : '');
    if (tab === 'feestructures') loadHostelFees();
}

async function loadRoomTypes() {
    const data = await apiGet('/api/hostel/setup.php');
    roomTypes = data.room_types || [];
    const grid = document.getElementById('roomTypesGrid');

    grid.innerHTML = roomTypes.length
        ? roomTypes.map(rt => `
            <div class="rt-card">
                <div class="rt-header">
                    <div class="rt-title">${escHtml(rt.name)}</div>
                    <span class="badge badge-info">${rt.occupancy} bed${rt.occupancy > 1 ? 's' : ''}</span>
                </div>
                <div style="font-size:13px;color:var(--text-secondary)">Policy: ${escHtml(rt.gender_policy || 'separate')}</div>
                <div style="font-size:18px;font-weight:700;color:var(--accent);margin:8px 0">₹${parseFloat(rt.default_fee || 0).toLocaleString('en-IN')}<span style="font-size:12px;font-weight:400;color:var(--text-muted)">/mo</span></div>
                ${rt.amenities ? `<div class="rt-badge">${JSON.parse(rt.amenities || '[]').map(a => `<span class="amenity-tag">${escHtml(a)}</span>`).join('')}</div>` : ''}
                <div style="margin-top:14px;display:flex;justify-content:flex-end">
                    <button class="btn btn-danger btn-sm" onclick="deleteRoomType(${rt.id})">🗑️ Delete</button>
                </div>
            </div>`).join('')
        : '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)">No room types configured yet.</div>';

    // Populate the fee form select
    document.getElementById('feeRoomTypeSelect').innerHTML =
        '<option value="">Select Type…</option>' +
        roomTypes.map(rt => `<option value="${rt.id}">${escHtml(rt.name)} (${rt.occupancy} beds)</option>`).join('');
}

async function loadHostelFees() {
    const data = await apiGet('/api/hostel/setup.php?fees=1');
    const fees = data.fees || [];
    document.getElementById('hostelFeeBody').innerHTML = fees.length
        ? fees.map(f => `
            <tr>
                <td>${escHtml(f.room_type_name || '-')}</td>
                <td>${escHtml(f.academic_year)}</td>
                <td><span class="badge badge-info">${escHtml(f.billing_cycle || 'Monthly')}</span></td>
                <td><strong>₹${parseFloat(f.amount).toLocaleString('en-IN')}</strong></td>
                <td>₹${parseFloat(f.mess_charge || 0).toLocaleString('en-IN')}</td>
                <td>₹${parseFloat(f.caution_deposit || 0).toLocaleString('en-IN')}</td>
                <td><button class="btn btn-danger btn-sm" onclick="deleteHostelFee(${f.id})">🗑️</button></td>
            </tr>`).join('')
        : '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted)">No fee structures configured.</td></tr>';
}

async function submitRoomType(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    // Parse amenities into JSON array
    if (data.amenities) {
        data.amenities = JSON.stringify(data.amenities.split(',').map(a => a.trim()).filter(Boolean));
    }
    const res = await apiPost('/api/hostel/setup.php', { action: 'create_room_type', ...data });
    if (res.success) { showToast('Room type created!'); closeModal('addRoomTypeModal'); e.target.reset(); loadRoomTypes(); }
    else showToast(res.error || 'Failed', 'danger');
}

async function deleteRoomType(id) {
    if (!confirm('Delete this room type? All associated fee structures will also be removed.')) return;
    const res = await apiDelete('/api/hostel/setup.php?type=room_type&id=' + id);
    if (res.success) { showToast('Deleted'); loadRoomTypes(); }
    else showToast(res.error || 'Failed', 'danger');
}

async function submitHostelFee(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res = await apiPost('/api/hostel/setup.php', { action: 'create_fee_structure', ...data });
    if (res.success) { showToast('Fee structure saved!'); closeModal('addHostelFeeModal'); e.target.reset(); loadHostelFees(); }
    else showToast(res.error || 'Failed', 'danger');
}

async function deleteHostelFee(id) {
    if (!confirm('Delete this fee structure?')) return;
    const res = await apiDelete('/api/hostel/setup.php?type=fee_structure&id=' + id);
    if (res.success) { showToast('Deleted'); loadHostelFees(); }
    else showToast(res.error || 'Failed', 'danger');
}

loadRoomTypes();
</script>
</body>
</html>
