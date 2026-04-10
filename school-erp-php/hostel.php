<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);
$pageTitle  = 'Hostel Management';
$needsStudents = true;
require_once __DIR__ . '/includes/data.php';

$blocks = ['Block A', 'Block B', 'Girls Hostel', 'Boys Hostel'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div class="toolbar-left" style="font-size:18px;font-weight:700">🏠 Hostel Rooms & Beds</div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addRoomModal')">+ Add Room</button>
                <button class="btn btn-secondary" onclick="openModal('allocateModal')">🛏️ Allocate Bed</button>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Room No</th><th>Block</th><th>Floor</th><th>Type</th><th>Capacity / Occupied</th><th>Monthly Fee</th></tr></thead>
                    <tbody id="roomsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Room -->
<div class="modal-overlay" id="addRoomModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">🏠 Add Room</div><button class="modal-close" onclick="closeModal('addRoomModal')">✕</button></div>
        <form id="addRoomForm" onsubmit="submitRoom(event)">
            <input type="hidden" name="action" value="add_room">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Room Number *</label><input type="text" class="form-control" name="room_number" required></div>
                <div class="form-group"><label class="form-label">Block</label>
                    <select class="form-control" name="block">
                        <?php foreach($blocks as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Floor</label><input type="number" class="form-control" name="floor" value="1"></div>
                <div class="form-group"><label class="form-label">Room Type *</label>
                    <select class="form-control" name="type">
                        <option value="single">Single Bed</option>
                        <option value="double" selected>Double Bed</option>
                        <option value="triple">Triple Bed</option>
                        <option value="dormitory">Dormitory</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Capacity (Beds) *</label><input type="number" class="form-control" name="capacity" value="2" required></div>
                <div class="form-group"><label class="form-label">Monthly Fee (₹)</label><input type="number" class="form-control" name="monthly_fee" value="0"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRoomModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Room</button>
            </div>
        </form>
    </div>
</div>

<!-- Allocate Bed -->
<div class="modal-overlay" id="allocateModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">🛏️ Allocate Bed to Student</div><button class="modal-close" onclick="closeModal('allocateModal')">✕</button></div>
        <form id="allocateForm" onsubmit="submitAllocate(event)">
            <input type="hidden" name="action" value="allocate">
            <div class="form-group"><label class="form-label">Select Student *</label>
                <select class="form-control" name="student_id" required>
                    <option value="">Choose Student...</option>
                    <?php foreach($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['class_name']?:'-') ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Select Room *</label>
                <select class="form-control" name="room_id" id="selRoomList" required><option value="">Loading...</option></select>
            </div>
            <div class="form-group"><label class="form-label">Check-in Date</label><input type="date" class="form-control" name="check_in_date" value="<?= date('Y-m-d') ?>" required></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('allocateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Allocate</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
async function loadRooms() {
    const rooms = await apiGet('/api/hostel/index.php');
    document.getElementById('roomsBody').innerHTML = rooms.map(r => `
        <tr>
            <td><strong>Room ${escHtml(r.room_number)}</strong></td>
            <td>${escHtml(r.block)}</td>
            <td>Floor ${r.floor}</td>
            <td><span class="badge badge-info" style="text-transform:capitalize">${r.type}</span></td>
            <td><span class="badge ${parseInt(r.occupants)>=parseInt(r.capacity)?'badge-danger':'badge-success'}">${r.occupants} / ${r.capacity} Beds</span></td>
            <td>₹${parseFloat(r.monthly_fee).toLocaleString('en-IN')} / mo</td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No rooms configured</td></tr>';
    
    document.getElementById('selRoomList').innerHTML = rooms.filter(r=>parseInt(r.occupants)<parseInt(r.capacity)).map(r=>`<option value="${r.id}">Room ${escHtml(r.room_number)} (${escHtml(r.block)})</option>`).join('') || '<option value="">All rooms are full</option>';
}

async function submitRoom(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('addRoomForm')));
    const res = await apiPost('/api/hostel/index.php', data);
    if(res.success){ showToast('Room added'); closeModal('addRoomModal'); document.getElementById('addRoomForm').reset(); loadRooms(); }
    else showToast(res.error||'Error','danger');
}

async function submitAllocate(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('allocateForm')));
    const res = await apiPost('/api/hostel/index.php', data);
    if(res.success){ showToast('Allocation saved'); closeModal('allocateModal'); document.getElementById('allocateForm').reset(); loadRooms(); }
    else showToast(res.error||'Error','danger');
}

loadRooms();
</script>
</body>
</html>
