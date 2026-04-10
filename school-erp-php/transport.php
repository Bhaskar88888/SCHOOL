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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
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
        </div>

        <!-- Routes Tab -->
        <div id="viewRoutes">
            <div class="page-toolbar">
                <div style="font-weight:600">Transport Routes Map</div>
                <button class="btn btn-primary" onclick="openModal('addRouteModal')">+ Add New Route</button>
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
                <button class="btn btn-primary" onclick="openModal('addVehicleModal')">+ Add Vehicle</button>
            </div>
            <div class="card"><div class="table-wrap">
                <table>
                    <thead><tr><th>Vehicle No.</th><th>Type</th><th>Capacity</th><th>Driver Name</th><th>Driver Phone</th><th>Actions</th></tr></thead>
                    <tbody id="vehiclesBody"></tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>

<!-- Add Route Modal -->
<div class="modal-overlay" id="addRouteModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">🛣️ Add Bus Route</div><button class="modal-close" onclick="closeModal('addRouteModal')">✕</button></div>
        <form id="addRouteForm" onsubmit="submitTransport(event, 'route')">
            <input type="hidden" name="type" value="route">
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
        <div class="modal-header"><div class="modal-title">🚌 Add Vehicle</div><button class="modal-close" onclick="closeModal('addVehicleModal')">✕</button></div>
        <form id="addVehicleForm" onsubmit="submitTransport(event, 'vehicle')">
            <input type="hidden" name="type" value="vehicle">
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

<script src="/assets/js/main.js"></script>
<script>
function switchTab(t) {
    document.getElementById('viewRoutes').style.display = t==='routes'?'block':'none';
    document.getElementById('viewVehicles').style.display = t==='vehicles'?'block':'none';
    document.getElementById('tabRoutes').className = 'tab' + (t==='routes'?' active':'');
    document.getElementById('tabVehicles').className = 'tab' + (t==='vehicles'?' active':'');
}

async function loadTransport() {
    const data = await apiGet('/api/transport/index.php');
    
    // Vehicles
    document.getElementById('vehiclesBody').innerHTML = data.vehicles.map(v => `
        <tr>
            <td><strong>${escHtml(v.vehicle_no)}</strong></td>
            <td><span class="badge badge-primary">${escHtml(v.type)}</span></td>
            <td>${v.capacity} Seats</td>
            <td>${escHtml(v.driver_name||'-')}</td>
            <td>${escHtml(v.driver_phone||'-')}</td>
            <td><button class="btn btn-danger btn-sm" onclick="delTransport(${v.id}, 'vehicle')">🗑️</button></td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No vehicles found</td></tr>';
    
    // Routes
    document.getElementById('routesBody').innerHTML = data.routes.map(r => `
        <tr>
            <td><strong>${escHtml(r.route_name)}</strong></td>
            <td>${r.vehicle_no ? `<span class="badge badge-info">${escHtml(r.vehicle_no)}</span>` : '<span class="badge badge-danger">Unassigned</span>'}</td>
            <td><div style="font-size:12px">${escHtml(r.driver_name||'-')}</div></td>
            <td><p style="margin:0;font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(r.stops||'-')}</p></td>
            <td>₹${parseFloat(r.monthly_fee).toLocaleString('en-IN')} / mo</td>
            <td><button class="btn btn-danger btn-sm" onclick="delTransport(${r.id}, 'route')">🗑️</button></td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No routes configured</td></tr>';
    
    // Update select dropdown
    document.getElementById('selVehicles').innerHTML = '<option value="">No Vehicle Assigned</option>' + data.vehicles.map(v=>`<option value="${v.id}">${escHtml(v.vehicle_no)} (${v.capacity} Seats)</option>`).join('');
}

async function submitTransport(e, type) {
    e.preventDefault();
    const form = document.getElementById(type === 'route' ? 'addRouteForm' : 'addVehicleForm');
    const modalId = type === 'route' ? 'addRouteModal' : 'addVehicleModal';
    const data = Object.fromEntries(new FormData(form));
    const res = await apiPost('/api/transport/index.php', data);
    if(res.success){ showToast(type==='route'?'Route added':'Vehicle added'); closeModal(modalId); form.reset(); loadTransport(); }
    else showToast(res.error||'Error','danger');
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
