<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['admin', 'superadmin']);
$pageTitle = 'Archive';
$currentAction = $_GET['action'] ?? 'students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .archive-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .archive-tab {
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 10px 16px;
            color: var(--text-secondary);
            background: var(--bg-card);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }
        .archive-tab.active {
            border-color: var(--accent);
            color: var(--accent-light);
            background: var(--accent-glow);
        }
        .archive-toolbar {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 160px auto;
            gap: 12px;
            margin-bottom: 18px;
        }
        .summary-row {
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
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card-value {
            margin-top: 8px;
            font-size: 24px;
            font-weight: 700;
        }
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .empty-row {
            padding: 26px 12px;
            text-align: center;
            color: var(--text-muted);
        }
        .details-grid {
            display: grid;
            gap: 12px;
        }
        .details-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-light);
        }
        .details-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .details-label {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        @media (max-width: 900px) {
            .archive-toolbar {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                justify-content: flex-start;
            }
            .details-row {
                grid-template-columns: 1fr;
                gap: 6px;
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
                <div style="font-size:24px;font-weight:800">Archive and Historical Data</div>
                <div style="color:var(--text-muted);margin-top:6px">View former students, previous staff, historical fees, past exams, and old attendance.</div>
            </div>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <div class="summary-card-label">Current Section</div>
                <div class="summary-card-value" id="archiveSectionLabel">Students</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Visible Records</div>
                <div class="summary-card-value" id="archiveVisible">0</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Total Records</div>
                <div class="summary-card-value" id="archiveTotal">0</div>
            </div>
        </div>

        <div class="archive-tabs" id="archiveTabs">
            <button class="archive-tab" type="button" data-action="students">Passed Out Students</button>
            <button class="archive-tab" type="button" data-action="staff">Former Staff</button>
            <button class="archive-tab" type="button" data-action="fees">Previous Fees</button>
            <button class="archive-tab" type="button" data-action="exams">Past Exams</button>
            <button class="archive-tab" type="button" data-action="attendance">Old Attendance</button>
        </div>

        <div class="card">
            <div class="archive-toolbar">
                <input class="form-control" type="search" id="searchInput" placeholder="Search archive records">
                <select class="form-control" id="yearFilter">
                    <option value="">All years</option>
                </select>
                <div class="action-buttons">
                    <button class="btn btn-secondary" type="button" id="refreshBtn">Refresh</button>
                    <button class="btn btn-primary" type="button" id="exportBtn">Export CSV</button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead id="archiveTableHead"></thead>
                    <tbody id="archiveTableBody">
                    <tr><td colspan="6" class="empty-row">Loading archive...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar">
                <div id="paginationInfo" style="color:var(--text-muted)">Page 1 of 1</div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-secondary" type="button" id="prevPageBtn">Previous</button>
                    <button class="btn btn-secondary" type="button" id="nextPageBtn">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="detailsModalOverlay">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Archive Details</div>
            <button class="modal-close" type="button" onclick="closeModal('detailsModalOverlay')">x</button>
        </div>
        <div class="details-grid" id="detailsGrid"></div>
        <div class="action-buttons" style="margin-top:18px">
            <button class="btn btn-secondary" type="button" onclick="closeModal('detailsModalOverlay')">Close</button>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const archiveConfig = {
    students: {
        label: 'Students',
        headers: ['Admission No', 'Name', 'Class', 'Admission Date', 'Discharge Date', 'Reason', 'Actions'],
        mapRow(record) {
            return [
                record.admission_no || '-',
                escHtml(record.name || '-'),
                escHtml(record.class_name || '-'),
                formatDate(record.admission_date),
                formatDate(record.discharge_date || record.archive_date),
                escHtml(record.discharge_reason || '-'),
                detailButton(record)
            ];
        }
    },
    staff: {
        label: 'Staff',
        headers: ['Employee ID', 'Name', 'Email', 'Role', 'Department', 'Archived Date', 'Actions'],
        mapRow(record) {
            return [
                escHtml(record.employee_id || '-'),
                escHtml(record.name || '-'),
                escHtml(record.email || '-'),
                escHtml(roleLabel(record.role)),
                escHtml(record.department || record.designation || '-'),
                formatDate(record.archive_date),
                detailButton(record)
            ];
        }
    },
    fees: {
        label: 'Fees',
        headers: ['Receipt No', 'Student', 'Class', 'Fee Type', 'Paid', 'Date', 'Actions'],
        mapRow(record) {
            return [
                escHtml(record.receipt_no || '-'),
                escHtml(record.student_name || '-'),
                escHtml(record.class_name || '-'),
                escHtml(record.fee_type || '-'),
                formatCurrency(record.amount_paid || 0),
                formatDate(record.paid_date || record.archive_date),
                detailButton(record)
            ];
        }
    },
    exams: {
        label: 'Exams',
        headers: ['Exam', 'Subject', 'Class', 'Exam Date', 'Max Marks', 'Actions'],
        mapRow(record) {
            return [
                escHtml(record.name || '-'),
                escHtml(record.subject || '-'),
                escHtml(record.class_name || '-'),
                formatDate(record.exam_date),
                escHtml(record.max_marks || '-'),
                detailButton(record)
            ];
        }
    },
    attendance: {
        label: 'Attendance',
        headers: ['Date', 'Student', 'Admission No', 'Class', 'Status', 'Subject', 'Actions'],
        mapRow(record) {
            return [
                formatDate(record.date),
                escHtml(record.student_name || '-'),
                escHtml(record.admission_no || '-'),
                escHtml(record.class_name || '-'),
                escHtml(record.status || '-'),
                escHtml(record.subject || '-'),
                detailButton(record)
            ];
        }
    }
};

let currentAction = '<?= htmlspecialchars($currentAction, ENT_QUOTES, 'UTF-8') ?>';
let currentPage = 1;
let totalPages = 1;
let currentRecords = [];

document.addEventListener('DOMContentLoaded', () => {
    hydrateYearOptions();
    bindEvents();
    setActiveTab(currentAction);
    loadArchive();
});

function bindEvents() {
    document.querySelectorAll('[data-action]').forEach((button) => {
        button.addEventListener('click', () => {
            currentAction = button.dataset.action;
            setActiveTab(currentAction);
            loadArchive(1);
            history.replaceState({}, '', `?action=${currentAction}`);
        });
    });

    document.getElementById('searchInput').addEventListener('input', debounce(() => loadArchive(1), 250));
    document.getElementById('yearFilter').addEventListener('change', () => loadArchive(1));
    document.getElementById('refreshBtn').addEventListener('click', () => loadArchive(currentPage));
    document.getElementById('exportBtn').addEventListener('click', exportArchive);
    document.getElementById('prevPageBtn').addEventListener('click', () => loadArchive(Math.max(1, currentPage - 1)));
    document.getElementById('nextPageBtn').addEventListener('click', () => loadArchive(Math.min(totalPages, currentPage + 1)));
}

function hydrateYearOptions() {
    const select = document.getElementById('yearFilter');
    const currentYear = new Date().getFullYear();
    for (let year = currentYear; year >= currentYear - 10; year -= 1) {
        const option = document.createElement('option');
        option.value = String(year);
        option.textContent = String(year);
        select.appendChild(option);
    }
}

function setActiveTab(action) {
    document.querySelectorAll('.archive-tab').forEach((button) => {
        button.classList.toggle('active', button.dataset.action === action);
    });
    document.getElementById('archiveSectionLabel').textContent = archiveConfig[action]?.label || 'Archive';
}

async function loadArchive(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({
        action: currentAction,
        page,
        limit: 20
    });
    const search = document.getElementById('searchInput').value.trim();
    const year = document.getElementById('yearFilter').value;
    if (search) params.set('search', search);
    if (year) params.set('year', year);

    document.getElementById('archiveTableBody').innerHTML = '<tr><td colspan="7" class="empty-row">Loading archive...</td></tr>';

    try {
        const response = await apiGet(`/api/archive?${params.toString()}`);
        if (response.error) {
            throw new Error(response.error);
        }
        currentRecords = response.archived || response.data || [];
        totalPages = response.pagination?.totalPages || 1;
        renderTable(currentRecords);
        renderSummary(response);
        renderPagination(response.pagination || { page: 1, totalPages: 1, total: 0 });
    } catch (error) {
        document.getElementById('archiveTableBody').innerHTML = `<tr><td colspan="7" class="empty-row">${escHtml(error.message || 'Failed to load archive')}</td></tr>`;
    }
}

function renderTable(records) {
    const config = archiveConfig[currentAction];
    const head = document.getElementById('archiveTableHead');
    head.innerHTML = '<tr>' + config.headers.map((header) => `<th>${header}</th>`).join('') + '</tr>';

    const body = document.getElementById('archiveTableBody');
    if (!records.length) {
        body.innerHTML = `<tr><td colspan="${config.headers.length}" class="empty-row">No archived records found.</td></tr>`;
        return;
    }

    body.innerHTML = records.map((record, index) => {
        const columns = config.mapRow(record, index).map((value) => `<td>${value}</td>`).join('');
        return `<tr>${columns}</tr>`;
    }).join('');
}

function renderSummary(response) {
    document.getElementById('archiveVisible').textContent = currentRecords.length;
    document.getElementById('archiveTotal').textContent = response.pagination?.total || currentRecords.length;
}

function renderPagination(pagination) {
    currentPage = pagination.page || 1;
    totalPages = pagination.totalPages || 1;
    document.getElementById('paginationInfo').textContent = `Page ${currentPage} of ${totalPages} (${pagination.total || 0} records)`;
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
}

function exportArchive() {
    const rows = currentRecords.map((record) => flattenRecord(record));
    downloadCsv(`${currentAction}_archive_${Date.now()}.csv`, rows);
}

function viewDetails(index) {
    const record = currentRecords[index];
    if (!record) {
        return;
    }

    const detailsGrid = document.getElementById('detailsGrid');
    detailsGrid.innerHTML = Object.entries(record).map(([key, value]) => `
        <div class="details-row">
            <div class="details-label">${escHtml(formatKey(key))}</div>
            <div>${escHtml(formatValue(value))}</div>
        </div>
    `).join('');
    openModal('detailsModalOverlay');
}

function detailButton(record) {
    const index = currentRecords.indexOf(record);
    return `<button class="btn btn-secondary btn-sm" type="button" onclick="viewDetails(${index})">View</button>`;
}

function flattenRecord(record) {
    const output = {};
    Object.entries(record).forEach(([key, value]) => {
        if (value == null) {
            output[key] = '';
        } else if (typeof value === 'object') {
            output[key] = JSON.stringify(value);
        } else {
            output[key] = value;
        }
    });
    return output;
}

function formatKey(key) {
    return key.replace(/_/g, ' ');
}

function formatValue(value) {
    if (value == null || value === '') return '-';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

function debounce(callback, delay) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => callback(...args), delay);
    };
}
</script>
</body>
</html>
