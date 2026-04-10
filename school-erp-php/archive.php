<?php
/**
 * Archive Page - View Archived Students, Staff, Fees, Exams
 * School ERP PHP v3.0
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';

require_auth();
require_role(['admin', 'superadmin']);

include 'includes/header.php';
include 'includes/sidebar.php';

$action = $_GET['action'] ?? 'students';
?>

<div class="main-content">
    <div class="page-header">
        <h1>📦 Archive</h1>
    </div>

    <div class="tabs">
        <a href="?action=students" class="tab <?= $action === 'students' ? 'active' : '' ?>">Students</a>
        <a href="?action=staff" class="tab <?= $action === 'staff' ? 'active' : '' ?>">Staff</a>
        <a href="?action=fees" class="tab <?= $action === 'fees' ? 'active' : '' ?>">Fees</a>
        <a href="?action=exams" class="tab <?= $action === 'exams' ? 'active' : '' ?>">Exams</a>
    </div>

    <div class="filters">
        <input type="text" id="searchInput" placeholder="Search archived records..." onkeyup="searchArchive()">
    </div>

    <div class="table-container">
        <table>
            <thead id="tableHead">
                <!-- Dynamic headers -->
            </thead>
            <tbody id="archiveTableBody">
                <tr><td colspan="6" class="text-center">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="pagination" class="pagination"></div>
</div>

<script>
let currentPage = 1;
const currentAction = '<?= $action ?>';

// Set headers based on action
const headers = {
    students: ['Admission No', 'Name', 'Class', 'Admission Date', 'Discharge Date', 'Reason'],
    staff: ['Employee ID', 'Name', 'Email', 'Role', 'Department', 'Archived Date'],
    fees: ['Receipt No', 'Student', 'Amount', 'Paid Date', 'Fee Type', 'Archived Date'],
    exams: ['Exam Name', 'Subject', 'Class', 'Exam Date', 'Max Marks', 'Archived Date']
};

document.addEventListener('DOMContentLoaded', () => {
    renderHeaders();
    loadArchive();
});

function renderHeaders() {
    const thead = document.getElementById('tableHead');
    const cols = headers[currentAction] || headers.students;
    
    thead.innerHTML = '<tr>' + cols.map(h => `<th>${h}</th>`).join('') + '</tr>';
}

async function loadArchive(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    
    const params = new URLSearchParams({ action: currentAction, page, search });
    const response = await apiGet(`/api/archive?${params}`);
    
    const tbody = document.getElementById('archiveTableBody');
    
    if (response.error) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-error">${response.error}</td></tr>`;
        return;
    }
    
    const records = response.archived || [];
    
    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No archived records found</td></tr>';
        return;
    }
    
    let html = '';
    
    if (currentAction === 'students') {
        html = records.map(r => `
            <tr>
                <td>${r.admission_no || '-'}</td>
                <td>${escHtml(r.name)}</td>
                <td>Class ${r.class_id || '-'}</td>
                <td>${formatDate(r.admission_date)}</td>
                <td>${formatDate(r.discharge_date)}</td>
                <td>${escHtml(r.discharge_reason || '-')}</td>
            </tr>
        `).join('');
    } else if (currentAction === 'staff') {
        html = records.map(r => `
            <tr>
                <td>${r.employee_id || '-'}</td>
                <td>${escHtml(r.name)}</td>
                <td>${escHtml(r.email || '-')}</td>
                <td>${role_label(r.role)}</td>
                <td>${escHtml(r.department || '-')}</td>
                <td>${formatDate(r.archived_at)}</td>
            </tr>
        `).join('');
    } else if (currentAction === 'fees') {
        html = records.map(r => `
            <tr>
                <td>${r.receipt_no || '-'}</td>
                <td>${escHtml(r.student_name || '-')}</td>
                <td>${formatCurrency(r.amount_paid)}</td>
                <td>${formatDate(r.paid_date)}</td>
                <td>${escHtml(r.fee_type)}</td>
                <td>${formatDate(r.created_at)}</td>
            </tr>
        `).join('');
    } else if (currentAction === 'exams') {
        html = records.map(r => `
            <tr>
                <td>${escHtml(r.name)}</td>
                <td>${escHtml(r.subject)}</td>
                <td>${escHtml(r.class_name || '-')}</td>
                <td>${formatDate(r.exam_date)}</td>
                <td>${r.max_marks}</td>
                <td>${formatDate(r.created_at)}</td>
            </tr>
        `).join('');
    }
    
    tbody.innerHTML = html;
    renderPagination(response.pagination);
}

function renderPagination(pagination) {
    const div = document.getElementById('pagination');
    if (!pagination || pagination.totalPages <= 1) {
        div.innerHTML = '';
        return;
    }
    
    let html = `<span>Page ${pagination.page} of ${pagination.totalPages} (${pagination.total} records)</span>`;
    
    if (pagination.page > 1) {
        html += `<button onclick="loadArchive(${pagination.page - 1})">Previous</button>`;
    }
    
    if (pagination.page < pagination.totalPages) {
        html += `<button onclick="loadArchive(${pagination.page + 1})">Next</button>`;
    }
    
    div.innerHTML = html;
}

function searchArchive() {
    loadArchive(1);
}
</script>

<style>
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
}

.tab {
    padding: 10px 20px;
    text-decoration: none;
    color: var(--text-muted);
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab:hover, .tab.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}
</style>

<?php include 'includes/footer.php'; ?>
