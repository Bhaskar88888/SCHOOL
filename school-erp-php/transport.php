<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);
$pageTitle  = 'Transport Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .tabs { display:flex;gap:16px;border-bottom:1px solid var(--border);margin-bottom:20px; }
        .tab { padding:10px 16px;cursor:pointer;color:var(--text-secondary);font-weight:600;border-bottom:2px solid transparent;transition:all 0.2s; }
        .tab.active { color:var(--accent);border-bottom:2px solid var(--accent); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('routes')" id="tabRoutes">🛣️ Bus Routes</div>
            <div class="tab" onclick="switchTab('vehicles')" id="tabVehicles">🚌 Vehicles / Buses</div>
            <div class="tab" onclick="switchTab('assignments')" id="tabAssignments">🎓 Assignments</div>
        </div>

        <!-- Routes Tab -->
        <div id="viewRoutes">
            <div class="page-toolbar">
                <div style="font-weight:600">Transport Routes Map</div>
                <button class="btn btn-primary" onclick="openAddRoute()">+ Add New Route</button>
            </div>
            <div class="card"><div class="table-wrap">
                <table>
                    <thead><tr><th>Route Name</th><th>Assigned Vehicle</th><th>Driver Details</th><th>Stops</th><th>Monthly Fee</th><th>Actions</th></tr></thead>
                    <tbody id="routesBody"></tbody>
                </table>
            </div></div>
        </div>

        <!-- Vehicles Tab -->
        <div id="viewVehicles" style="display:none">
            <div class="page-toolbar">
                <div style="font-weight:600">Fleet Management</div>
                <button class="btn btn-primary" onclick="openAddVehicle()">+ Add Vehicle</button>
            </div>
            <div class="card"><div class="table-wrap">
                <table>
                    <thead><tr><th>Vehicle No.</th><th>Type</th><th>Capacity</th><th>Driver Name</th><th>Driver Phone</th><th>Actions</th></tr></thead>
                    <tbody id="vehiclesBody"></tbody>
                </table>
            </div></div>
        </div>

        <!-- Assignments Tab -->
        <div id="viewAssignments" style="display:none">
            <div class="page-toolbar">
                <div style="font-weight:600">Student Route Mapping</div>
            </div>
            <div class="card"><div class="table-wrap">
                <table>
                    <thead><tr><th>Student</th><th>Class</th><th>Transport Route</th></tr></thead>
                    <tbody id="assignmentsBody">
                        <?php foreach($students as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['class_name']?:'-') ?></td>
                            <td>
                                <select class="form-control" style="width:250px" onchange="assignTransport(<?= $s['id'] ?>, this.value)">
                                    <option value="0">No Transport Required</option>
                                    <!-- Options populated by JS -->
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>

<!-- Add Route Modal -->
<div class="modal-overlay" id="addRouteModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title" id="routeModalTitle">🛣️ Add Bus Route</div><button class="modal-close" onclick="closeModal('addRouteModal')">✕</button></div>
        <form id="addRouteForm" onsubmit="submitTransport(event, 'route')">
            <input type="hidden" name="type" id="routeFormType" value="route">
            <input type="hidden" name="id" id="editRouteId" value="">
            <div class="form-group"><label class="form-label">Route Name *</label><input type="text" class="form-control" name="route_name" required placeholder="e.g. City Center to Campus"></div>
            <div class="form-group"><label class="form-label">Assign Vehicle</label>
                <select class="form-control" name="vehicle_id" id="selVehicles">
                    <option value="">No Vehicle Assigned</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Stops (Comma separated)</label><textarea class="form-control" name="stops" rows="2"></textarea></div>
            <div class="form-group"><label class="form-label">Monthly Transport Fee (₹)</label><input type="number" class="form-control" name="monthly_fee" value="0"></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRouteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Route</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Vehicle Modal -->
<div class="modal-overlay" id="addVehicleModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title" id="vehicleModalTitle">🚌 Add Vehicle</div><button class="modal-close" onclick="closeModal('addVehicleModal')">✕</button></div>
        <form id="addVehicleForm" onsubmit="submitTransport(event, 'vehicle')">
            <input type="hidden" name="type" id="vehicleFormType" value="vehicle">
            <input type="hidden" name="id" id="editVehicleId" value="">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Vehicle Registration No. *</label><input type="text" class="form-control" name="vehicle_no" required></div>
                <div class="form-group"><label class="form-label">Vehicle Type</label>
                    <select class="form-control" name="vehicle_type"><option>Bus</option><option>Van</option><option>Mini-Bus</option></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Seating Capacity *</label><input type="number" class="form-control" name="capacity" value="40" required></div>
            </div>
            <hr style="border-color:var(--border);margin:10px 0 20px">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Driver Name</label><input type="text" class="form-control" name="driver_name"></div>
                <div class="form-group"><label class="form-label">Driver Phone</label><input type="tel" class="form-control" name="driver_phone"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addVehicleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Vehicle</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let routeList = [];

function switchTab(t) {
    document.getElementById('viewRoutes').style.display = t==='routes'?'block':'none';
    document.getElementById('viewVehicles').style.display = t==='vehicles'?'block':'none';
    document.getElementById('viewAssignments').style.display = t==='assignments'?'block':'none';
    document.getElementById('tabRoutes').className = 'tab' + (t==='routes'?' active':'');
    document.getElementById('tabVehicles').className = 'tab' + (t==='vehicles'?' active':'');
    document.getElementById('tabAssignments').className = 'tab' + (t==='assignments'?' active':'');
}

async function loadTransport() {
    const data = await apiGet('/api/transport/index.php');
    
    // Vehicles
    document.getElementById('vehiclesBody').innerHTML = data.vehicles.map(v => `
        <tr>
            <td><strong>${escHtml(v.vehicle_no)}</strong></td>
            <td><span class="badge badge-primary">${escHtml(v.type)}</span></td>
            <td>${v.capacity} Seats</td>
            <td><div style="font-size:12px">${escHtml(v.driver_name||'-')}</div></td>
            <td><div style="font-size:12px">${escHtml(v.driver_phone||'-')}</div></td>
            <td>
                <div style="display:flex;gap:4px;">
                    <button class="btn btn-secondary btn-sm" onclick='editVehicle(${JSON.stringify(v).replace(/'/g, "&apos;")})'>✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="delTransport(${v.id}, 'vehicle')">🗑️</button>
                </div>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No vehicles found</td></tr>';
    
    // Routes
    routeList = data.routes;
    document.getElementById('routesBody').innerHTML = data.routes.map(r => {
        const stopsHtml = r.stops ? r.stops.split(',').map(s=>`<span class="badge badge-light" style="margin-right:4px">${escHtml(s.trim())}</span>`).join('') : '-';
        return `
        <tr>
            <td><strong>${escHtml(r.route_name)}</strong></td>
            <td>${r.vehicle_no ? `<span class="badge badge-info">${escHtml(r.vehicle_no)}</span>` : '<span class="badge badge-danger">Unassigned</span>'}</td>
            <td><div style="font-size:12px">${escHtml(r.driver_name||'-')}</div></td>
            <td><div style="font-size:11px;max-width:200px;display:flex;flex-wrap:wrap;gap:4px;">${stopsHtml}</div></td>
            <td>₹${parseFloat(r.monthly_fee).toLocaleString('en-IN')} / mo</td>
            <td>
                <div style="display:flex;gap:4px">
                    <button class="btn btn-secondary btn-sm" onclick='editRoute(${JSON.stringify(r).replace(/'/g, "&apos;")})'>✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="delTransport(${r.id}, 'route')">🗑️</button>
                </div>
            </td>
        </tr>
    `}).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No routes configured</td></tr>';
    
    // Update select dropdown
    document.getElementById('selVehicles').innerHTML = '<option value="">No Vehicle Assigned</option>' + data.vehicles.map(v=>`<option value="${v.id}">${escHtml(v.vehicle_no)} (${v.capacity} Seats)</option>`).join('');
    
    // Update assignments logic
    const routeOptions = '<option value="0">No Transport Required</option>' + data.routes.map(r => `<option value="${r.id}">${escHtml(r.route_name)}</option>`).join('');
    document.querySelectorAll('#assignmentsBody select').forEach(sel => {
        const selected = sel.value; // preserve selected if any
        sel.innerHTML = routeOptions;
        sel.value = selected;
    });
    
    if (data.allocations) {
        data.allocations.forEach(a => {
            const sel = document.querySelector(`#assignmentsBody select[onchange="assignTransport(${a.student_id}, this.value)"]`);
            if (sel) sel.value = a.route_id;
        });
    }
}

function openAddRoute() {
    const form = document.getElementById('addRouteForm');
    form.reset();
    document.getElementById('routeFormType').value = 'route';
    document.getElementById('editRouteId').value = '';
    document.getElementById('routeModalTitle').textContent = '🛣️ Add Bus Route';
    openModal('addRouteModal');
}

function editRoute(r) {
    const form = document.getElementById('addRouteForm');
    form.reset();
    document.getElementById('routeFormType').value = 'route'; // PUT request checks it
    document.getElementById('editRouteId').value = r.id;
    form.route_name.value = r.route_name;
    form.vehicle_id.value = r.vehicle_id || '';
    form.stops.value = r.stops || '';
    form.monthly_fee.value = r.monthly_fee || 0;
    document.getElementById('routeModalTitle').textContent = '✏️ Edit Bus Route';
    openModal('addRouteModal');
}

function openAddVehicle() {
    const form = document.getElementById('addVehicleForm');
    form.reset();
    document.getElementById('vehicleFormType').value = 'vehicle';
    document.getElementById('editVehicleId').value = '';
    document.getElementById('vehicleModalTitle').textContent = '🚌 Add Vehicle';
    openModal('addVehicleModal');
}

function editVehicle(v) {
    const form = document.getElementById('addVehicleForm');
    form.reset();
    document.getElementById('vehicleFormType').value = 'vehicle';
    document.getElementById('editVehicleId').value = v.id;
    form.vehicle_no.value = v.vehicle_no;
    form.vehicle_type.value = v.type || 'Bus';
    form.capacity.value = v.capacity || 50;
    form.driver_name.value = v.driver_name || '';
    form.driver_phone.value = v.driver_phone || '';
    document.getElementById('vehicleModalTitle').textContent = '✏️ Edit Vehicle';
    openModal('addVehicleModal');
}

async function submitTransport(e, type) {
    e.preventDefault();
    const form = document.getElementById(type === 'route' ? 'addRouteForm' : 'addVehicleForm');
    const modalId = type === 'route' ? 'addRouteModal' : 'addVehicleModal';
    const data = Object.fromEntries(new FormData(form));
    
    // Check if it's edit
    const isEdit = data.id && data.id !== '';
    const method = isEdit ? apiPut : apiPost;
    
    const res = await method('/api/transport/index.php', data);
    if(res.success){ showToast(type==='route'?'Route saved':'Vehicle saved'); closeModal(modalId); form.reset(); loadTransport(); }
    else showToast(res.error||'Error','danger');
}

async function assignTransport(studentId, routeId) {
    const res = await apiPost('/api/transport/index.php', { type: 'assign', student_id: studentId, route_id: routeId });
    if(res.success){
        showToast('Assignment updated');
    } else {
        showToast(res.error||'Cannot allocate. Try updating Schema patch.', 'danger');
    }
}

async function delTransport(id, type) {
    if(!confirm(`Delete this ${type}?`)) return;
    await fetch(`/api/transport/index.php?id=${id}&type=${type}`,{method:'DELETE'});
    showToast('Deleted successfully'); loadTransport();
}

loadTransport();
</script>
</body>
</html>
