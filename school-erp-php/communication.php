<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();

$pageTitle = 'Communication Hub';
$role = get_authenticated_user()['role'];
$isAdmin = in_array($role, ['superadmin', 'admin', 'hr']);
$isTeacher = $role === 'teacher';
$isParent = $role === 'parent';
$isStudent = $role === 'student';

// Also include data for selects
require_once __DIR__ . '/includes/data.php';
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
        .tabs { display:flex; gap:20px; border-bottom:1px solid var(--border-color); margin-bottom:20px; }
        .tab-btn { background:none; border:none; padding:10px 15px; font-weight:600; color:var(--text-muted); cursor:pointer; font-family:'Outfit',sans-serif; border-bottom:2px solid transparent; transition:0.2s; }
        .tab-btn:hover { color:var(--text-color); }
        .tab-btn.active { color:var(--primary-color); border-bottom-color:var(--primary-color); }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        
        .message-thread { border:1px solid var(--border-color); border-radius:var(--radius-md); padding:15px; margin-bottom:15px; background:var(--surface-color); }
        .message-header { display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; color:var(--text-muted); }
        .message-body { font-size:15px; line-height:1.5; }
        .msg-reply-box { margin-top:15px; display:flex; gap:10px; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>
            
            <div class="page-hero">
                <div class="hero-content">
                    <h1>🗣️ Communication Hub</h1>
                    <p>Connect with stakeholders, view notifications, and manage complaints.</p>
                </div>
            </div>

            <div class="card" style="margin-top:-30px;">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('tab-complaints')">📣 Complaints & Queries</button>
                    <button class="tab-btn" onclick="switchTab('tab-notices')">📢 Announcements</button>
                    <button class="tab-btn" onclick="switchTab('tab-notifications')">🔔 Notifications</button>
                </div>

                <!-- Complaints Tab -->
                <div id="tab-complaints" class="tab-content active">
                    <div class="page-toolbar">
                        <div class="toolbar-left" style="display:flex;gap:15px">
                            <select class="form-control" id="complaintStatus" onchange="loadComplaints()">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="openModal('complaintModal')">+ New Query/Complaint</button>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title & Description</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Submitted By</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="complaintsTable"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Announcements Tab -->
                <div id="tab-notices" class="tab-content">
                    <?php if($isAdmin || $isTeacher): ?>
                    <div class="page-toolbar">
                        <div style="flex:1"></div>
                        <button class="btn btn-primary" onclick="openModal('noticeModal')">+ New Notice</button>
                    </div>
                    <?php endif; ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;" id="noticesGrid"></div>
                </div>

                <!-- Notifications Tab -->
                <div id="tab-notifications" class="tab-content">
                    <div class="page-toolbar">
                        <div style="flex:1"></div>
                        <button class="btn btn-secondary" onclick="markAllNotificationsRead()">Mark All as Read</button>
                    </div>
                    <div id="notificationsList" style="display:flex;flex-direction:column;gap:10px"></div>
                </div>

            </div>
        </div>
    </div>

    <!-- Complaint Modal -->
    <div class="modal-overlay" id="complaintModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Submit Query / Complaint</div>
                <button class="modal-close" onclick="closeModal('complaintModal')">✕</button>
            </div>
            <form onsubmit="submitComplaint(event)">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" class="form-control" name="title" required placeholder="Brief summary">
                </div>
                
                <?php if ($isTeacher): ?>
                <div class="form-group">
                    <label class="form-label">Related Student (Optional, routes to Parent)</label>
                    <select class="form-control" name="student_id">
                        <option value="">None / General Admin</option>
                        <?php foreach($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['class_name']?:'-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($isParent): ?>
                <div class="form-group">
                    <label class="form-label">Related Class (Optional, routes to Teacher)</label>
                    <select class="form-control" name="class_id">
                        <option value="">None / General Admin</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> - <?= htmlspecialchars($c['section']?:'') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:15px;margin-bottom:15px">
                    <div style="flex:1">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="category">
                            <option value="general">General</option>
                            <option value="academic">Academic</option>
                            <option value="behavior">Behavior/Discipline</option>
                            <option value="infrastructure">Infrastructure</option>
                            <option value="fee">Fees / Finance</option>
                        </select>
                    </div>
                    <div style="flex:1">
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

    <!-- Resolve/Reply Modal -->
    <div class="modal-overlay" id="resolveModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Update Status / Reply</div>
                <button class="modal-close" onclick="closeModal('resolveModal')">✕</button>
            </div>
            <form onsubmit="submitResolution(event)">
                <input type="hidden" id="resolve_id" name="id">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status" id="resolve_status">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
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

    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script>
        const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
        
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            if(tabId === 'tab-complaints') loadComplaints();
            if(tabId === 'tab-notices') loadNotices();
            if(tabId === 'tab-notifications') loadNotifications();
        }

        // --- COMPLAINTS ---
        async function loadComplaints() {
            const status = document.getElementById('complaintStatus').value;
            const data = await apiGet(`/api/complaints/index.php?status=${status}`);
            
            document.getElementById('complaintsTable').innerHTML = data.map(c => `
                <tr>
                    <td>
                        <strong style="display:block;margin-bottom:4px">${escHtml(c.title)}</strong>
                        <span style="font-size:13px;color:var(--text-muted);display:block;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(c.description||'')}</span>
                        ${c.resolution_note ? `<div style="margin-top:5px;font-size:12px;background:var(--bg-color);padding:5px;border-radius:4px;border-left:2px solid var(--primary-color)"><strong>Reply:</strong> ${escHtml(c.resolution_note)}</div>` : ''}
                    </td>
                    <td>${escHtml(c.category)}</td>
                    <td><span class="badge badge-${c.priority==='urgent'?'danger':(c.priority==='high'?'warning':'info')}">${c.priority}</span></td>
                    <td>${escHtml(c.submitted_by_name)}<br><small style="color:var(--text-muted)">${c.raised_by_role}</small></td>
                    <td><span class="badge badge-${c.status==='resolved'?'success':(c.status==='pending'?'warning':'info')}">${c.status}</span></td>
                    <td style="font-size:12px;color:var(--text-muted)">${new Date(c.created_at).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="openResolveModal(${c.id}, '${c.status}')">Update</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted)">No items found</td></tr>';
        }

        async function submitComplaint(e) {
            e.preventDefault();
            const res = await apiPost('/api/complaints/index.php', Object.fromEntries(new FormData(e.target)));
            if(res.success) { showToast('Submitted'); closeModal('complaintModal'); e.target.reset(); loadComplaints(); }
            else showToast(res.error||'Error', 'danger');
        }

        function openResolveModal(id, status) {
            document.getElementById('resolve_id').value = id;
            document.getElementById('resolve_status').value = status;
            openModal('resolveModal');
        }

        async function submitResolution(e) {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target));
            const res = await fetch('/api/complaints/index.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify(payload)
            }).then(r => r.json());
            
            if(res.success) { showToast('Updated'); closeModal('resolveModal'); loadComplaints(); }
            else showToast(res.error||'Error', 'danger');
        }

        // --- NOTIFICATIONS ---
        async function loadNotifications() {
            const data = await apiGet('/api/notifications/index.php?limit=50');
            const list = document.getElementById('notificationsList');
            list.innerHTML = data.notifications.map(n => `
                <div class="message-thread" style="${parseInt(n.is_read) === 0 ? 'border-left: 4px solid var(--primary-color)' : ''}">
                    <div class="message-header">
                        <strong>${escHtml(n.title)}</strong>
                        <span>${new Date(n.created_at).toLocaleString()}</span>
                    </div>
                    <div class="message-body">${escHtml(n.message)}</div>
                    ${parseInt(n.is_read) === 0 ? `<button class="btn btn-sm btn-secondary" style="margin-top:10px" onclick="markNotificationRead(${n.id})">Mark Read</button>` : ''}
                </div>
            `).join('') || '<div style="padding:20px;text-align:center;color:var(--text-muted)">No recent notifications.</div>';
        }

        async function markNotificationRead(id) {
            await apiPost('/api/notifications/index.php', { id: id });
            loadNotifications();
        }

        async function markAllNotificationsRead() {
            await apiPost('/api/notifications/index.php', { mark_all: true });
            loadNotifications();
        }

        // --- NOTICES (reused logic) ---
        async function loadNotices() {
            const data = await apiGet('/api/notices/index.php');
            document.getElementById('noticesGrid').innerHTML = data.map(n => `
                <div class="card" style="margin:0;padding:20px">
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:5px">${new Date(n.published_date||n.created_at).toLocaleDateString()}</div>
                    <h3 style="margin-bottom:10px;font-size:18px">${escHtml(n.title)}</h3>
                    <p style="color:var(--text-color);margin-bottom:15px;line-height:1.5">${escHtml(n.content)}</p>
                    <div style="font-size:13px;color:var(--text-muted)">By: ${escHtml(n.created_by_name)}</div>
                </div>
            `).join('') || '<div style="padding:20px;color:var(--text-muted)">No announcements found.</div>';
        }

        // Init
        loadComplaints();
    </script>
</body>
</html>
