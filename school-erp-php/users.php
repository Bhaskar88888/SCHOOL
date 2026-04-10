<?php
/**
 * Users Management Page
 * School ERP PHP v3.0
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';

require_auth();
require_role(['admin', 'superadmin', 'hr']);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>👥 User Management</h1>
        <button class="btn btn-primary" onclick="openUserModal()">+ Add User</button>
    </div>

    <div class="filters">
        <input type="text" id="searchInput" placeholder="Search users..." onkeyup="searchUsers()">
        <select id="roleFilter" onchange="loadUsers()">
            <option value="">All Roles</option>
            <option value="superadmin">Super Admin</option>
            <option value="admin">Admin</option>
            <option value="teacher">Teacher</option>
            <option value="student">Student</option>
            <option value="parent">Parent</option>
            <option value="hr">HR</option>
            <option value="accounts">Accounts</option>
            <option value="librarian">Librarian</option>
            <option value="canteen">Canteen</option>
            <option value="conductor">Conductor</option>
            <option value="driver">Driver</option>
        </select>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <tr><td colspan="8" class="text-center">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="pagination" class="pagination"></div>
</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add User</h2>
            <span class="close" onclick="closeUserModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="userId">
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" id="userName" required>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="userEmail" required>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" id="userPassword" required>
                    <small>Leave blank to keep current password when editing</small>
                </div>

                <div class="form-group">
                    <label>Role *</label>
                    <select id="userRole" required>
                        <option value="">Select Role</option>
                        <option value="superadmin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                        <option value="parent">Parent</option>
                        <option value="hr">HR</option>
                        <option value="accounts">Accounts</option>
                        <option value="librarian">Librarian</option>
                        <option value="canteen">Canteen</option>
                        <option value="conductor">Conductor</option>
                        <option value="driver">Driver</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <input type="text" id="userDepartment">
                </div>

                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" id="userDesignation">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="userPhone">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="userActive" checked>
                        Active
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentUserId = null;

// Load users on page load
document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
});

async function loadUsers(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    
    const params = new URLSearchParams({ page, search, role });
    const response = await apiGet(`/api/users?${params}`);
    
    const tbody = document.getElementById('usersTableBody');
    
    if (response.error) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-error">${response.error}</td></tr>`;
        return;
    }
    
    if (response.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No users found</td></tr>';
        return;
    }
    
    tbody.innerHTML = response.users.map(user => `
        <tr>
            <td>${user.employee_id || '-'}</td>
            <td>${escHtml(user.name)}</td>
            <td>${escHtml(user.email)}</td>
            <td><span class="badge badge-${user.role}">${role_label(user.role)}</span></td>
            <td>${escHtml(user.department || '-')}</td>
            <td>${escHtml(user.phone || '-')}</td>
            <td><span class="badge badge-${user.is_active ? 'success' : 'danger'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
                <button class="btn btn-sm btn-edit" onclick="editUser(${user.id})">✏️ Edit</button>
                <button class="btn btn-sm btn-delete" onclick="deleteUser(${user.id}, '${escHtml(user.name)}')">🗑️ Delete</button>
            </td>
        </tr>
    `).join('');
    
    renderPagination(response.pagination);
}

function renderPagination(pagination) {
    const div = document.getElementById('pagination');
    if (pagination.totalPages <= 1) {
        div.innerHTML = '';
        return;
    }
    
    let html = `<span>Page ${pagination.page} of ${pagination.totalPages} (${pagination.total} users)</span>`;
    
    if (pagination.page > 1) {
        html += `<button onclick="loadUsers(${pagination.page - 1})">Previous</button>`;
    }
    
    if (pagination.page < pagination.totalPages) {
        html += `<button onclick="loadUsers(${pagination.page + 1})">Next</button>`;
    }
    
    div.innerHTML = html;
}

function searchUsers() {
    loadUsers(1);
}

function openUserModal(userId = null) {
    const modal = document.getElementById('userModal');
    modal.classList.add('active');
    
    if (userId) {
        currentUserId = userId;
        document.getElementById('modalTitle').textContent = 'Edit User';
        // Load user data
        loadUserData(userId);
    } else {
        currentUserId = null;
        document.getElementById('modalTitle').textContent = 'Add User';
        document.getElementById('userForm').reset();
    }
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
}

async function loadUserData(userId) {
    // Get user data from the users list API
    const response = await apiGet(`/api/users?page=1&limit=1000`);
    const user = response.users.find(u => u.id === userId);
    
    if (user) {
        document.getElementById('userId').value = user.id;
        document.getElementById('userName').value = user.name;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userRole').value = user.role;
        document.getElementById('userDepartment').value = user.department || '';
        document.getElementById('userDesignation').value = user.designation || '';
        document.getElementById('userPhone').value = user.phone || '';
        document.getElementById('userActive').checked = user.is_active;
    }
}

async function editUser(userId) {
    openUserModal(userId);
}

async function deleteUser(userId, userName) {
    if (!confirm(`Are you sure you want to delete user "${userName}"?`)) {
        return;
    }
    
    const response = await apiPost('/api/users', { id: userId, _method: 'DELETE' });
    
    if (response.error) {
        showToast(response.error, 'error');
    } else {
        showToast(response.message, 'success');
        loadUsers(currentPage);
    }
}

document.getElementById('userForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const data = {
        id: currentUserId,
        name: document.getElementById('userName').value,
        email: document.getElementById('userEmail').value,
        role: document.getElementById('userRole').value,
        department: document.getElementById('userDepartment').value,
        designation: document.getElementById('userDesignation').value,
        phone: document.getElementById('userPhone').value,
        is_active: document.getElementById('userActive').checked ? 1 : 0,
    };
    
    const password = document.getElementById('userPassword').value;
    if (password) {
        data.password = password;
    }
    
    const response = await apiPost('/api/users', data);
    
    if (response.error) {
        if (response.errors) {
            showToast(Object.values(response.errors).join(', '), 'error');
        } else {
            showToast(response.error, 'error');
        }
    } else {
        showToast(response.message, 'success');
        closeUserModal();
        loadUsers(currentPage);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
