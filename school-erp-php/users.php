<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['admin', 'superadmin', 'hr']);
$pageTitle = 'User Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .toolbar-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 180px auto;
            gap: 12px;
            width: 100%;
            align-items: center;
        }
        .action-group {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .summary-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
        }
        .summary-card-label {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card-value {
            font-size: 26px;
            font-weight: 700;
            margin-top: 6px;
        }
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .empty-row {
            text-align: center;
            color: var(--text-muted);
            padding: 28px 16px;
        }
        .id-card {
            width: min(360px, 100%);
            margin: 0 auto;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(79,142,247,0.16), rgba(28,35,51,1));
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .id-card-head {
            padding: 24px 24px 18px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .id-card-body {
            padding: 24px;
            display: grid;
            gap: 12px;
        }
        .id-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.12);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .id-field {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding-bottom: 10px;
        }
        .id-field:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .id-field-label {
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        .id-field-value {
            color: var(--text-primary);
            text-align: right;
            font-weight: 600;
        }
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }
        @media (max-width: 900px) {
            .toolbar-grid {
                grid-template-columns: 1fr;
            }
            .action-group {
                justify-content: flex-start;
            }
        }
        @media print {
            body * {
                visibility: hidden;
            }
            #idCardPrintArea, #idCardPrintArea * {
                visibility: visible;
            }
            #idCardPrintArea {
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
            <div>
                <div style="font-size:24px;font-weight:800">User Management</div>
                <div style="color:var(--text-muted);margin-top:6px">Create, edit, search, print, and manage ERP access.</div>
            </div>
        </div>

        <div class="summary-strip">
            <div class="summary-card">
                <div class="summary-card-label">Total Users</div>
                <div class="summary-card-value" id="totalUsers">0</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Active Users</div>
                <div class="summary-card-value" id="activeUsers">0</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Visible This Page</div>
                <div class="summary-card-value" id="visibleUsers">0</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="margin-bottom:20px">
                <div class="card-title">Access Directory</div>
            </div>

            <div class="toolbar-grid" style="margin-bottom:18px">
                <input class="form-control" type="search" id="searchInput" placeholder="Search by name, email, employee ID, or phone">
                <select class="form-control" id="roleFilter">
                    <option value="">All roles</option>
                    <option value="superadmin">Super Admin</option>
                    <option value="admin">Admin</option>
                    <option value="teacher">Teacher</option>
                    <option value="student">Student</option>
                    <option value="parent">Parent</option>
                    <option value="staff">Staff</option>
                    <option value="hr">HR</option>
                    <option value="accounts">Accounts</option>
                    <option value="librarian">Librarian</option>
                    <option value="canteen">Canteen</option>
                    <option value="conductor">Conductor</option>
                    <option value="driver">Driver</option>
                </select>
                <div class="action-group">
                    <button class="btn btn-secondary" type="button" id="refreshBtn">Refresh</button>
                    <button class="btn btn-primary" type="button" id="addUserBtn">Add User</button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="usersTableBody">
                    <tr><td colspan="7" class="empty-row">Loading users...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar">
                <div id="paginationInfo" style="color:var(--text-muted)">Page 1</div>
                <div class="action-group">
                    <button class="btn btn-secondary" type="button" id="prevPageBtn">Previous</button>
                    <button class="btn btn-secondary" type="button" id="nextPageBtn">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="userModalOverlay">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="userModalTitle">Add User</div>
            <button class="modal-close" type="button" onclick="closeUserModal()">x</button>
        </div>
        <form id="userForm">
            <input type="hidden" id="userId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="userEmployeeId">Employee ID</label>
                    <input class="form-control" type="text" id="userEmployeeId" placeholder="EMP2026001">
                </div>
                <div class="form-group">
                    <label class="form-label" for="userRole">Role</label>
                    <select class="form-control" id="userRole" required>
                        <option value="">Select role</option>
                        <option value="superadmin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                        <option value="parent">Parent</option>
                        <option value="staff">Staff</option>
                        <option value="hr">HR</option>
                        <option value="accounts">Accounts</option>
                        <option value="librarian">Librarian</option>
                        <option value="canteen">Canteen</option>
                        <option value="conductor">Conductor</option>
                        <option value="driver">Driver</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="userName">Full Name</label>
                    <input class="form-control" type="text" id="userName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="userEmail">Email</label>
                    <input class="form-control" type="email" id="userEmail" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="userDepartment">Department</label>
                    <input class="form-control" type="text" id="userDepartment">
                </div>
                <div class="form-group">
                    <label class="form-label" for="userDesignation">Designation</label>
                    <input class="form-control" type="text" id="userDesignation">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="userPhone">Phone</label>
                    <input class="form-control" type="text" id="userPhone">
                </div>
                <div class="form-group">
                    <label class="form-label" for="userPassword">Password</label>
                    <input class="form-control" type="password" id="userPassword" placeholder="Leave blank to keep current password">
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:10px;color:var(--text-secondary)">
                    <input type="checkbox" id="userActive" checked>
                    User is active
                </label>
            </div>
            <div class="action-group">
                <button class="btn btn-secondary" type="button" onclick="closeUserModal()">Cancel</button>
                <button class="btn btn-primary" type="submit">Save User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="idCardModalOverlay">
    <div class="modal" id="idCardPrintArea">
        <div class="modal-header">
            <div class="modal-title">Identity Card</div>
            <button class="modal-close" type="button" onclick="closeIdCardModal()">x</button>
        </div>
        <div class="id-card" id="idCardContainer"></div>
        <div class="action-group" style="margin-top:20px">
            <button class="btn btn-secondary" type="button" onclick="closeIdCardModal()">Close</button>
            <button class="btn btn-primary" type="button" onclick="window.print()">Print</button>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let currentPage = 1;
let totalPages = 1;
let currentUsers = [];

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('searchInput').addEventListener('input', debounce(() => loadUsers(1), 250));
    document.getElementById('roleFilter').addEventListener('change', () => loadUsers(1));
    document.getElementById('refreshBtn').addEventListener('click', () => loadUsers(currentPage));
    document.getElementById('addUserBtn').addEventListener('click', () => openUserModal());
    document.getElementById('prevPageBtn').addEventListener('click', () => loadUsers(Math.max(1, currentPage - 1)));
    document.getElementById('nextPageBtn').addEventListener('click', () => loadUsers(Math.min(totalPages, currentPage + 1)));
    document.getElementById('userForm').addEventListener('submit', saveUser);
    loadUsers();
});

async function loadUsers(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value.trim();
    const role = document.getElementById('roleFilter').value;
    const params = new URLSearchParams({
        page,
        limit: 15
    });
    if (search) params.set('search', search);
    if (role) params.set('role', role);

    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="empty-row">Loading users...</td></tr>';

    try {
        const response = await apiGet(`/api/users/index.php?${params.toString()}`);
        if (response.error) {
            throw new Error(response.error);
        }

        currentUsers = response.users || [];
        totalPages = response.pagination?.totalPages || 1;
        renderUsers(currentUsers);
        renderSummary(response);
        renderPagination(response.pagination || { page: 1, totalPages: 1, total: 0 });
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-row">${escHtml(error.message || 'Failed to load users')}</td></tr>`;
    }
}

function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No users found for the current filters.</td></tr>';
        return;
    }

    tbody.innerHTML = users.map((user) => `
        <tr>
            <td>${escHtml(user.employee_id || '-')}</td>
            <td>
                <div style="font-weight:600">${escHtml(user.name || '-')}</div>
                <div style="color:var(--text-muted);font-size:12px">${escHtml(user.email || '-')}</div>
            </td>
            <td><span class="badge badge-primary">${escHtml(roleLabel(user.role))}</span></td>
            <td>${escHtml(user.department || user.designation || '-')}</td>
            <td>${escHtml(user.phone || '-')}</td>
            <td><span class="badge ${user.is_active ? 'badge-success' : 'badge-danger'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
                <div class="table-actions">
                    <button class="btn btn-secondary btn-sm" type="button" onclick="openIdCard(${user.id})">ID Card</button>
                    <button class="btn btn-secondary btn-sm" type="button" onclick="openUserModal(${user.id})">Edit</button>
                    <button class="btn btn-danger btn-sm" type="button" onclick="deleteUser(${user.id}, '${escapeJs(user.name || 'this user')}')">Delete</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderSummary(response) {
    const users = response.users || [];
    const pagination = response.pagination || {};
    document.getElementById('totalUsers').textContent = pagination.total || users.length || 0;
    document.getElementById('activeUsers').textContent = users.filter((user) => Number(user.is_active) === 1).length;
    document.getElementById('visibleUsers').textContent = users.length;
}

function renderPagination(pagination) {
    currentPage = pagination.page || 1;
    totalPages = pagination.totalPages || 1;
    document.getElementById('paginationInfo').textContent = `Page ${currentPage} of ${totalPages} (${pagination.total || 0} users)`;
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
}

async function openUserModal(userId = null) {
    resetUserForm();
    document.getElementById('userModalTitle').textContent = userId ? 'Edit User' : 'Add User';
    document.getElementById('userId').value = userId || '';
    openModal('userModalOverlay');

    if (!userId) {
        return;
    }

    try {
        const response = await apiGet(`/api/users/index.php?id=${userId}`);
        if (response.error) {
            throw new Error(response.error);
        }
        const user = response.user;
        document.getElementById('userEmployeeId').value = user.employee_id || '';
        document.getElementById('userName').value = user.name || '';
        document.getElementById('userEmail').value = user.email || '';
        document.getElementById('userRole').value = user.role || '';
        document.getElementById('userDepartment').value = user.department || '';
        document.getElementById('userDesignation').value = user.designation || '';
        document.getElementById('userPhone').value = user.phone || '';
        document.getElementById('userActive').checked = Number(user.is_active) === 1;
    } catch (error) {
        closeUserModal();
        showToast(error.message || 'Failed to load user', 'error');
    }
}

function closeUserModal() {
    closeModal('userModalOverlay');
}

function resetUserForm() {
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userActive').checked = true;
}

async function saveUser(event) {
    event.preventDefault();
    const userId = document.getElementById('userId').value;
    const payload = {
        id: userId ? Number(userId) : undefined,
        employee_id: document.getElementById('userEmployeeId').value.trim(),
        name: document.getElementById('userName').value.trim(),
        email: document.getElementById('userEmail').value.trim(),
        role: document.getElementById('userRole').value,
        department: document.getElementById('userDepartment').value.trim(),
        designation: document.getElementById('userDesignation').value.trim(),
        phone: document.getElementById('userPhone').value.trim(),
        is_active: document.getElementById('userActive').checked ? 1 : 0
    };

    const password = document.getElementById('userPassword').value.trim();
    if (password) {
        payload.password = password;
    }

    try {
        const response = await apiPost('/api/users/index.php', payload);
        if (response.error) {
            throw new Error(response.error);
        }
        if (response.errors) {
            throw new Error(Object.values(response.errors).join(', '));
        }
        closeUserModal();
        showToast(response.message || 'User saved successfully');
        loadUsers(currentPage);
    } catch (error) {
        showToast(error.message || 'Failed to save user', 'error');
    }
}

async function deleteUser(userId, userName) {
    if (!confirm(`Delete ${userName}? This cannot be undone.`)) {
        return;
    }

    try {
        const response = await apiPost('/api/users/index.php', { _method: 'DELETE', id: userId });
        if (response.error) {
            throw new Error(response.error);
        }
        showToast(response.message || 'User deleted successfully');
        loadUsers(currentPage);
    } catch (error) {
        showToast(error.message || 'Failed to delete user', 'error');
    }
}

function openIdCard(userId) {
    const user = currentUsers.find((item) => Number(item.id) === Number(userId));
    if (!user) {
        showToast('User not found', 'warning');
        return;
    }

    document.getElementById('idCardContainer').innerHTML = `
        <div class="id-card-head">
            <div class="id-avatar">${escHtml((user.name || 'U').charAt(0).toUpperCase())}</div>
            <div style="font-size:24px;font-weight:800">${escHtml(user.name || 'User')}</div>
            <div style="color:var(--text-muted);margin-top:6px">${escHtml(roleLabel(user.role))}</div>
        </div>
        <div class="id-card-body">
            <div class="id-field">
                <div class="id-field-label">Employee ID</div>
                <div class="id-field-value">${escHtml(user.employee_id || 'Not assigned')}</div>
            </div>
            <div class="id-field">
                <div class="id-field-label">Email</div>
                <div class="id-field-value">${escHtml(user.email || '-')}</div>
            </div>
            <div class="id-field">
                <div class="id-field-label">Phone</div>
                <div class="id-field-value">${escHtml(user.phone || '-')}</div>
            </div>
            <div class="id-field">
                <div class="id-field-label">Department</div>
                <div class="id-field-value">${escHtml(user.department || user.designation || '-')}</div>
            </div>
            <div class="id-field">
                <div class="id-field-label">Status</div>
                <div class="id-field-value">${user.is_active ? 'Active' : 'Inactive'}</div>
            </div>
        </div>
    `;
    openModal('idCardModalOverlay');
}

function closeIdCardModal() {
    closeModal('idCardModalOverlay');
}

function debounce(callback, delay) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => callback(...args), delay);
    };
}

function escapeJs(value) {
    return String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}
</script>
</body>
</html>
