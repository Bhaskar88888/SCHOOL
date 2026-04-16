<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();

$pageTitle = 'Communication Hub';
$role = normalize_role_name(get_authenticated_user()['role'] ?? '');
$isAdmin = in_array($role, ['superadmin', 'admin', 'hr'], true);
$isTeacher = $role === 'teacher';
$isParent = $role === 'parent';

$teacherComplaintStudents = [];
if ($isTeacher) {
    $teacherComplaintStudents = db_fetchAll(
        "SELECT s.id, s.name, s.parent_user_id, c.name AS class_name, COALESCE(s.section, '') AS section
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.is_active = 1
         ORDER BY s.name ASC
         LIMIT 1000"
    );
}

$classTeacherColumn = db_column_exists('classes', 'teacher_id')
    ? 'teacher_id'
    : (db_column_exists('classes', 'class_teacher_id') ? 'class_teacher_id' : 'NULL');

$parentComplaintClasses = [];
if ($isParent) {
    $parentComplaintClasses = db_fetchAll(
        "SELECT DISTINCT c.id, c.name, COALESCE(c.section, '') AS section, $classTeacherColumn AS teacher_user_id
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.is_active = 1 AND s.parent_user_id = ?
         ORDER BY c.name ASC",
        [get_current_user_id()]
    );

    if (empty($parentComplaintClasses)) {
        $parentComplaintClasses = db_fetchAll(
            "SELECT c.id, c.name, COALESCE(c.section, '') AS section, $classTeacherColumn AS teacher_user_id
             FROM classes c
             ORDER BY c.name ASC"
        );
    }
}

$staffMembers = [];
if ($isAdmin) {
    $staffMembers = db_fetchAll(
        "SELECT id, name
         FROM users
         WHERE role NOT IN ('student', 'parent') AND is_active = 1
         ORDER BY name ASC"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .tab-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
        .tab-btn { border:1px solid var(--border); background:var(--bg-card); color:var(--text-secondary); padding:10px 14px; border-radius:999px; cursor:pointer; font-weight:600; }
        .tab-btn.active { background:var(--accent); color:#fff; border-color:var(--accent); }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .panel-toolbar { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:18px; flex-wrap:wrap; }
        .panel-toolbar .filters { display:flex; gap:12px; flex-wrap:wrap; }
        .message-thread { border:1px solid var(--border); border-radius:var(--radius); background:var(--bg-card); padding:16px; }
        .message-thread.unread { border-left:4px solid var(--accent); }
        .message-meta { display:flex; justify-content:space-between; gap:12px; font-size:12px; color:var(--text-muted); margin-bottom:8px; flex-wrap:wrap; }
        .message-title { font-size:16px; font-weight:700; margin-bottom:6px; }
        .message-body { color:var(--text-secondary); line-height:1.6; }
        .stack { display:grid; gap:12px; }
        .routing-note { margin-top:8px; font-size:12px; color:var(--text-muted); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-hero">
            <div class="hero-content">
                <h1>Communication Hub</h1>
                <p>Complaints, announcements, inbox items, and notifications in one place.</p>
            </div>
        </div>

        <div class="card" style="margin-top:-24px;">
            <div class="tab-row">
                <button class="tab-btn active" type="button" data-tab="tab-complaints" onclick="switchTab(event, 'tab-complaints')">Complaints</button>
                <button class="tab-btn" type="button" data-tab="tab-inbox" onclick="switchTab(event, 'tab-inbox')">My Inbox</button>
                <button class="tab-btn" type="button" data-tab="tab-notices" onclick="switchTab(event, 'tab-notices')">Announcements</button>
                <button class="tab-btn" type="button" data-tab="tab-notifications" onclick="switchTab(event, 'tab-notifications')">Notifications</button>
            </div>

            <div id="tab-complaints" class="tab-panel active">
                <div class="panel-toolbar">
                    <div class="filters">
                        <select class="form-control" id="complaintStatus" onchange="loadComplaints()" style="min-width:180px">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="button" onclick="openModal('complaintModal')">New Complaint / Query</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Route</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="complaintsTable"></tbody>
                    </table>
                </div>
            </div>

            <div id="tab-inbox" class="tab-panel">
                <div class="panel-toolbar">
                    <div style="font-weight:600">Complaints and queries directed to you</div>
                </div>
                <div class="stack" id="inboxList"></div>
            </div>

            <div id="tab-notices" class="tab-panel">
                <?php if ($isAdmin || $isTeacher): ?>
                <div class="panel-toolbar">
                    <div style="font-weight:600">Announcements</div>
                    <button class="btn btn-primary" type="button" onclick="openModal('noticeModal')">Create Notice</button>
                </div>
                <?php endif; ?>
                <div class="stack" id="noticesList"></div>
            </div>

            <div id="tab-notifications" class="tab-panel">
                <div class="panel-toolbar">
                    <div style="font-weight:600">Recent notifications</div>
                    <button class="btn btn-secondary" type="button" onclick="markAllNotificationsRead()">Mark All as Read</button>
                </div>
                <div class="stack" id="notificationsList"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="complaintModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Submit Complaint / Query</div>
            <button class="modal-close" type="button" onclick="closeModal('complaintModal')">x</button>
        </div>
        <form id="complaintForm" onsubmit="submitComplaint(event)">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" class="form-control" name="title" required placeholder="Brief summary">
            </div>

            <?php if ($isTeacher): ?>
            <div class="form-group">
                <label class="form-label">Related Student</label>
                <select class="form-control" name="student_id" id="complaintStudentSelect" onchange="updateTeacherRouteNote()">
                    <option value="">None (send to admin)</option>
                    <?php foreach ($teacherComplaintStudents as $student): ?>
                    <option value="<?= (int) $student['id'] ?>" data-linked-parent="<?= !empty($student['parent_user_id']) ? '1' : '0' ?>">
                        <?= htmlspecialchars($student['name']) ?><?php if (!empty($student['class_name'])): ?> (<?= htmlspecialchars($student['class_name']) ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="routing-note" id="teacherRouteNote">Select a student to route this directly to the linked parent account. If no parent account is linked, it will fall back to admin.</div>
            </div>
            <?php endif; ?>

            <?php if ($isParent): ?>
            <div class="form-group">
                <label class="form-label">Related Class</label>
                <select class="form-control" name="class_id" id="complaintClassSelect" onchange="updateParentRouteNote()">
                    <option value="">None (send to admin)</option>
                    <?php foreach ($parentComplaintClasses as $class): ?>
                    <option value="<?= (int) $class['id'] ?>" data-linked-teacher="<?= !empty($class['teacher_user_id']) ? '1' : '0' ?>">
                        <?= htmlspecialchars($class['name']) ?><?= !empty($class['section']) ? ' - ' . htmlspecialchars($class['section']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="routing-note" id="parentRouteNote">Select your child&apos;s class to notify the class teacher directly. If the class has no linked teacher, it will fall back to admin.</div>
            </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-control" name="category">
                        <option value="general">General</option>
                        <option value="academic">Academic</option>
                        <option value="behavior">Behavior</option>
                        <option value="infrastructure">Infrastructure</option>
                        <option value="fee">Fees / Finance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-control" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Details *</label>
                <textarea class="form-control" name="description" rows="4" required></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('complaintModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal-overlay" id="resolveModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Update Complaint</div>
            <button class="modal-close" type="button" onclick="closeModal('resolveModal')">x</button>
        </div>
        <form id="resolveForm" onsubmit="submitResolution(event)">
            <input type="hidden" id="resolveId" name="id">
            <div class="form-group">
                <label class="form-label">Assign To</label>
                <select class="form-control" name="assigned_to" id="resolveAssignedTo">
                    <option value="">Unassigned</option>
                    <?php foreach ($staffMembers as $staffMember): ?>
                    <option value="<?= (int) $staffMember['id'] ?>"><?= htmlspecialchars($staffMember['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" name="status" id="resolveStatus">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Reply / Note</label>
                <textarea class="form-control" name="resolution_note" rows="3"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resolveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Update</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($isAdmin || $isTeacher): ?>
<div class="modal-overlay" id="noticeModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Create Notice</div>
            <button class="modal-close" type="button" onclick="closeModal('noticeModal')">x</button>
        </div>
        <form id="noticeForm" onsubmit="submitNotice(event)">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" class="form-control" name="title" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Target Roles</label>
                    <select class="form-control" name="target_roles">
                        <option value="all">All</option>
                        <option value="teacher">Teachers</option>
                        <option value="student">Students</option>
                        <option value="parent">Parents</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-control" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Content *</label>
                <textarea class="form-control" name="content" rows="5" required></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('noticeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Publish Notice</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
let currentComplaints = [];

function switchTab(event, tabId) {
    document.querySelectorAll('.tab-btn').forEach((button) => button.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach((panel) => panel.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');

    if (tabId === 'tab-complaints') {
        loadComplaints();
    } else if (tabId === 'tab-inbox') {
        loadInbox();
    } else if (tabId === 'tab-notices') {
        loadNotices();
    } else if (tabId === 'tab-notifications') {
        loadNotifications();
    }
}

function complaintRouteLabel(complaint) {
    if (complaint.target_user_name) {
        return escHtml(complaint.target_user_name);
    }
    if (complaint.assigned_to_name) {
        return escHtml(complaint.assigned_to_name);
    }
    return 'Admin';
}

async function loadComplaints() {
    const status = document.getElementById('complaintStatus').value;
    const data = await apiGet(`/api/complaints/index.php?status=${encodeURIComponent(status)}`);
    const complaints = Array.isArray(data) ? data : [];
    currentComplaints = complaints;

    document.getElementById('complaintsTable').innerHTML = complaints.map((complaint) => `
        <tr>
            <td>
                <strong style="display:block;margin-bottom:4px">${escHtml(complaint.title || '')}</strong>
                <span style="font-size:12px;color:var(--text-muted)">${escHtml(complaint.description || '')}</span>
                ${complaint.resolution_note ? `<div style="margin-top:8px;font-size:12px;color:var(--text-secondary)"><strong>Reply:</strong> ${escHtml(complaint.resolution_note)}</div>` : ''}
            </td>
            <td>${escHtml(complaint.category || '-')}</td>
            <td><span class="badge badge-${complaint.priority === 'urgent' ? 'danger' : (complaint.priority === 'high' ? 'warning' : 'info')}">${escHtml(complaint.priority || 'medium')}</span></td>
            <td>${complaintRouteLabel(complaint)}</td>
            <td><span class="badge badge-${complaint.status === 'resolved' ? 'success' : (complaint.status === 'pending' ? 'warning' : 'info')}">${escHtml((complaint.status || 'pending').replace('_', ' '))}</span></td>
            <td>${complaint.created_at ? new Date(complaint.created_at).toLocaleDateString() : '-'}</td>
            <td>${isAdmin ? `<button class="btn btn-secondary btn-sm" type="button" onclick="openResolveModalById(${complaint.id})">Update</button>` : '<span style="color:var(--text-muted)">View only</span>'}</td>
        </tr>
    `).join('') || '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted)">No complaints found.</td></tr>';
}

async function loadInbox() {
    const data = await apiGet('/api/complaints/index.php?inbox=1');
    const complaints = Array.isArray(data) ? data : [];
    document.getElementById('inboxList').innerHTML = complaints.map((complaint) => `
        <div class="message-thread ${complaint.status !== 'resolved' ? 'unread' : ''}">
            <div class="message-meta">
                <span>From: ${escHtml(complaint.submitted_by_name || 'Unknown')}</span>
                <span>${complaint.created_at ? new Date(complaint.created_at).toLocaleString() : ''}</span>
            </div>
            <div class="message-title">${escHtml(complaint.title || '')}</div>
            <div class="message-body">${escHtml(complaint.description || '')}</div>
            <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <span class="badge badge-${complaint.status === 'resolved' ? 'success' : (complaint.status === 'pending' ? 'warning' : 'info')}">${escHtml((complaint.status || 'pending').replace('_', ' '))}</span>
                ${complaint.resolution_note ? `<span style="font-size:12px;color:var(--text-secondary)">Reply: ${escHtml(complaint.resolution_note)}</span>` : ''}
            </div>
        </div>
    `).join('') || '<div style="padding:20px;text-align:center;color:var(--text-muted)">No complaints are currently assigned to you.</div>';
}

async function submitComplaint(event) {
    event.preventDefault();
    const response = await apiPost('/api/complaints/index.php', Object.fromEntries(new FormData(event.target)));
    if (response.success) {
        showToast(response.fallback_to_admin ? 'Submitted. No direct recipient was linked, so this was routed to admin.' : 'Complaint submitted successfully.');
        event.target.reset();
        updateTeacherRouteNote();
        updateParentRouteNote();
        closeModal('complaintModal');
        loadComplaints();
        loadInbox();
        return;
    }

    showToast(response.error || 'Unable to submit complaint.', 'danger');
}

function openResolveModal(complaint) {
    document.getElementById('resolveId').value = complaint.id || '';
    document.getElementById('resolveStatus').value = complaint.status || 'pending';
    document.getElementById('resolveAssignedTo').value = complaint.assigned_to || '';
    document.querySelector('#resolveForm [name="resolution_note"]').value = complaint.resolution_note || '';
    openModal('resolveModal');
}

function openResolveModalById(complaintId) {
    const complaint = currentComplaints.find((item) => Number(item.id) === Number(complaintId));
    if (!complaint) {
        showToast('Complaint not found.', 'warning');
        return;
    }

    openResolveModal(complaint);
}

async function submitResolution(event) {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target));
    const response = await apiPut('/api/complaints/index.php', payload);
    if (response.success) {
        showToast('Complaint updated successfully.');
        closeModal('resolveModal');
        loadComplaints();
        loadInbox();
        return;
    }

    showToast(response.error || 'Unable to update complaint.', 'danger');
}

async function loadNotices() {
    const data = await apiGet('/api/notices/index.php');
    const notices = Array.isArray(data) ? data : [];
    document.getElementById('noticesList').innerHTML = notices.map((notice) => `
        <div class="message-thread">
            <div class="message-meta">
                <span>${escHtml(notice.created_by_name || 'School')}</span>
                <span>${new Date(notice.published_date || notice.created_at).toLocaleDateString()}</span>
            </div>
            <div class="message-title">${escHtml(notice.title || '')}</div>
            <div class="message-body">${escHtml(notice.content || '')}</div>
        </div>
    `).join('') || '<div style="padding:20px;text-align:center;color:var(--text-muted)">No announcements found.</div>';
}

async function submitNotice(event) {
    event.preventDefault();
    const response = await apiPost('/api/notices/index.php', Object.fromEntries(new FormData(event.target)));
    if (response.success) {
        showToast('Notice published successfully.');
        event.target.reset();
        closeModal('noticeModal');
        loadNotices();
        return;
    }

    showToast(response.error || 'Unable to publish notice.', 'danger');
}

async function loadNotifications() {
    const data = await apiGet('/api/notifications/index.php?limit=50');
    const notifications = Array.isArray(data.notifications) ? data.notifications : [];
    document.getElementById('notificationsList').innerHTML = notifications.map((notification) => `
        <div class="message-thread ${parseInt(notification.is_read || 0, 10) === 0 ? 'unread' : ''}">
            <div class="message-meta">
                <strong>${escHtml(notification.title || '')}</strong>
                <span>${notification.created_at ? new Date(notification.created_at).toLocaleString() : ''}</span>
            </div>
            <div class="message-body">${escHtml(notification.message || '')}</div>
            ${parseInt(notification.is_read || 0, 10) === 0 ? `<button class="btn btn-secondary btn-sm" type="button" style="margin-top:10px" onclick="markNotificationRead(${notification.id})">Mark Read</button>` : ''}
        </div>
    `).join('') || '<div style="padding:20px;text-align:center;color:var(--text-muted)">No recent notifications.</div>';
}

async function markNotificationRead(id) {
    await apiPost('/api/notifications/index.php', { id });
    loadNotifications();
}

async function markAllNotificationsRead() {
    await apiPost('/api/notifications/index.php', { mark_all: true });
    loadNotifications();
}

function updateTeacherRouteNote() {
    const select = document.getElementById('complaintStudentSelect');
    const note = document.getElementById('teacherRouteNote');
    if (!select || !note) {
        return;
    }

    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        note.textContent = 'No student selected. This complaint will go to admin.';
        return;
    }

    note.textContent = selectedOption.dataset.linkedParent === '1'
        ? 'This will notify the selected student\'s parent directly.'
        : 'This student does not have a linked parent account yet, so the complaint will fall back to admin.';
}

function updateParentRouteNote() {
    const select = document.getElementById('complaintClassSelect');
    const note = document.getElementById('parentRouteNote');
    if (!select || !note) {
        return;
    }

    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        note.textContent = 'No class selected. This complaint will go to admin.';
        return;
    }

    note.textContent = selectedOption.dataset.linkedTeacher === '1'
        ? 'This will notify the linked class teacher directly.'
        : 'This class does not have a linked teacher account yet, so the complaint will fall back to admin.';
}

document.addEventListener('DOMContentLoaded', () => {
    updateTeacherRouteNote();
    updateParentRouteNote();
    loadComplaints();
});
</script>
</body>
</html>
