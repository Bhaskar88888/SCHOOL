<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role('superadmin');
$pageTitle = 'Audit Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .audit-filters {
            display: grid;
            grid-template-columns: 1.2fr repeat(4, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .mono {
            font-family: Consolas, monospace;
            font-size: 12px;
        }
        .details-block {
            color: var(--text-secondary);
            max-width: 340px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.45;
        }
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
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
        @media (max-width: 1100px) {
            .audit-filters {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 640px) {
            .audit-filters {
                grid-template-columns: 1fr;
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
                <div style="font-size:24px;font-weight:800">System Audit Logs</div>
                <div style="color:var(--text-muted);margin-top:6px">Review logins, CRUD actions, exports, imports, and archive activity.</div>
            </div>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <div class="summary-card-label">Total Logs</div>
                <div class="summary-card-value" id="auditTotal">0</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Visible Logs</div>
                <div class="summary-card-value" id="auditVisible">0</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Current Page</div>
                <div class="summary-card-value" id="auditPage">1</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Audit Timeline</div>
            </div>

            <div class="audit-filters">
                <input class="form-control" type="search" id="searchInput" placeholder="Search user, action, module, or details">
                <select class="form-control" id="moduleFilter">
                    <option value="">All modules</option>
                    <option value="auth">Auth</option>
                    <option value="users">Users</option>
                    <option value="students">Students</option>
                    <option value="attendance">Attendance</option>
                    <option value="fees">Fees</option>
                    <option value="import">Import</option>
                    <option value="archive">Archive</option>
                    <option value="pdf">PDF</option>
                </select>
                <select class="form-control" id="actionFilter">
                    <option value="">All actions</option>
                    <option value="CREATE">CREATE</option>
                    <option value="UPDATE">UPDATE</option>
                    <option value="DELETE">DELETE</option>
                    <option value="EXPORT">EXPORT</option>
                    <option value="IMPORT">IMPORT</option>
                    <option value="LOGIN_SUCCESS">LOGIN SUCCESS</option>
                    <option value="LOGIN_FAILED">LOGIN FAILED</option>
                    <option value="LOGOUT">LOGOUT</option>
                </select>
                <input class="form-control" type="date" id="dateFrom">
                <input class="form-control" type="date" id="dateTo">
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                    </thead>
                    <tbody id="auditTableBody">
                    <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">Loading logs...</td></tr>
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

<script src="/assets/js/main.js"></script>
<script>
let currentPage = 1;
let totalPages = 1;
let visibleLogs = [];

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('searchInput').addEventListener('input', debounce(() => loadAuditLogs(1), 250));
    document.getElementById('moduleFilter').addEventListener('change', () => loadAuditLogs(1));
    document.getElementById('actionFilter').addEventListener('change', () => loadAuditLogs(1));
    document.getElementById('dateFrom').addEventListener('change', () => loadAuditLogs(1));
    document.getElementById('dateTo').addEventListener('change', () => loadAuditLogs(1));
    document.getElementById('prevPageBtn').addEventListener('click', () => loadAuditLogs(Math.max(1, currentPage - 1)));
    document.getElementById('nextPageBtn').addEventListener('click', () => loadAuditLogs(Math.min(totalPages, currentPage + 1)));
    loadAuditLogs();
});

async function loadAuditLogs(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({
        page,
        limit: 20
    });

    const search = document.getElementById('searchInput').value.trim();
    const module = document.getElementById('moduleFilter').value;
    const action = document.getElementById('actionFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;

    if (search) params.set('search', search);
    if (module) params.set('module', module);
    if (action) params.set('action', action);
    if (dateFrom) params.set('date_from', dateFrom + ' 00:00:00');
    if (dateTo) params.set('date_to', dateTo + ' 23:59:59');

    const tbody = document.getElementById('auditTableBody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">Loading logs...</td></tr>';

    try {
        const response = await apiGet(`/api/audit?${params.toString()}`);
        if (response.error) {
            throw new Error(response.error);
        }

        visibleLogs = response.logs || response.data || [];
        totalPages = response.pagination?.totalPages || 1;
        renderAuditRows(visibleLogs);
        renderSummary(response);
        renderPagination(response.pagination || { page: 1, totalPages: 1, total: 0 });
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--danger)">${escHtml(error.message || 'Failed to load audit logs')}</td></tr>`;
    }
}

function renderAuditRows(logs) {
    const tbody = document.getElementById('auditTableBody');
    if (!logs.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">No logs found for the current filters.</td></tr>';
        return;
    }

    tbody.innerHTML = logs.map((log) => `
        <tr>
            <td>
                <div>${escHtml(formatDateTime(log.timestamp))}</div>
            </td>
            <td>
                <div style="font-weight:600">${escHtml(log.user?.name || 'System')}</div>
                <div style="color:var(--text-muted);font-size:12px">${escHtml(roleLabel(log.user?.role || ''))}${log.user?.email ? ` | ${escHtml(log.user.email)}` : ''}</div>
            </td>
            <td><span class="badge ${badgeClass(log.action)}">${escHtml(log.action || '-')}</span></td>
            <td>${escHtml(log.module || '-')}</td>
            <td><div class="details-block">${escHtml(formatDetails(log))}</div></td>
            <td class="mono">${escHtml(log.ipAddress || '-')}</td>
        </tr>
    `).join('');
}

function renderSummary(response) {
    const pagination = response.pagination || {};
    document.getElementById('auditTotal').textContent = pagination.total || visibleLogs.length;
    document.getElementById('auditVisible').textContent = visibleLogs.length;
    document.getElementById('auditPage').textContent = pagination.page || 1;
}

function renderPagination(pagination) {
    currentPage = pagination.page || 1;
    totalPages = pagination.totalPages || 1;
    document.getElementById('paginationInfo').textContent = `Page ${currentPage} of ${totalPages} (${pagination.total || 0} logs)`;
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    return date.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDetails(log) {
    if (typeof log.description === 'string' && log.description.trim() !== '') {
        return log.description;
    }
    if (log.recordId) {
        return `Record ID: ${log.recordId}`;
    }
    if (log.newValue) {
        return JSON.stringify(log.newValue);
    }
    if (log.oldValue) {
        return JSON.stringify(log.oldValue);
    }
    return 'No additional details';
}

function badgeClass(action) {
    const value = String(action || '').toUpperCase();
    if (value.includes('DELETE') || value.includes('FAILED')) return 'badge-danger';
    if (value.includes('CREATE') || value.includes('IMPORT') || value.includes('LOGIN_SUCCESS')) return 'badge-success';
    if (value.includes('UPDATE') || value.includes('EXPORT')) return 'badge-info';
    return 'badge-warning';
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
