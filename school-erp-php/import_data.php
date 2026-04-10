<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role(['admin', 'superadmin', 'hr']);
$pageTitle = 'Import Data';
$module = $_GET['module'] ?? 'students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .import-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .import-tab {
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 10px 16px;
            color: var(--text-secondary);
            background: var(--bg-card);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }
        .import-tab.active {
            border-color: var(--accent);
            color: var(--accent-light);
            background: var(--accent-glow);
        }
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .step-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
        }
        .step-badge {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--accent-glow);
            color: var(--accent-light);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .upload-area {
            border: 1px dashed var(--border);
            border-radius: var(--radius);
            padding: 28px;
            text-align: center;
            cursor: pointer;
            background: rgba(255,255,255,0.02);
            transition: 0.2s ease;
        }
        .upload-area:hover {
            border-color: var(--accent);
            background: rgba(79,142,247,0.08);
        }
        .info-note {
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            background: rgba(88,166,255,0.08);
            border: 1px solid rgba(88,166,255,0.2);
            color: var(--text-secondary);
            margin-bottom: 18px;
        }
        .result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .result-card {
            border-radius: var(--radius);
            padding: 16px;
            border: 1px solid var(--border);
            background: var(--bg-secondary);
        }
        .result-card strong {
            display: block;
            font-size: 24px;
            margin-top: 8px;
        }
        .hidden {
            display: none;
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
                <div style="font-size:24px;font-weight:800">Bulk Data Import</div>
                <div style="color:var(--text-muted);margin-top:6px">Upload CSV or XLSX data, preview it, then import it into the PHP ERP.</div>
            </div>
        </div>

        <div class="info-note">
            Supported formats: CSV and XLSX. Legacy XLS files should be resaved as XLSX before upload.
        </div>

        <div class="import-tabs" id="importTabs">
            <button class="import-tab" type="button" data-module="students">Students</button>
            <button class="import-tab" type="button" data-module="staff">Staff</button>
            <button class="import-tab" type="button" data-module="fees">Fee Payments</button>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-badge">1</div>
                <div style="font-size:18px;font-weight:700;margin-bottom:8px">Download Template</div>
                <div style="color:var(--text-secondary);margin-bottom:14px">Start with the current import template for the selected module.</div>
                <button class="btn btn-secondary" type="button" id="downloadTemplateBtn">Download Template</button>
            </div>

            <div class="step-card">
                <div class="step-badge">2</div>
                <div style="font-size:18px;font-weight:700;margin-bottom:8px">Upload and Preview</div>
                <div style="color:var(--text-secondary);margin-bottom:14px">Upload your file to preview the first few rows before import.</div>
                <label class="upload-area" for="fileInput" id="uploadArea">
                    <div style="font-size:28px;font-weight:700">Upload File</div>
                    <div style="margin-top:8px;color:var(--text-muted)">Click to choose a CSV or XLSX file</div>
                </label>
                <input type="file" id="fileInput" accept=".csv,.xlsx" class="hidden">
                <div id="selectedFileInfo" style="margin-top:12px;color:var(--text-secondary)"></div>
            </div>
        </div>

        <div class="card hidden" id="previewCard">
            <div class="card-header">
                <div class="card-title">Preview</div>
            </div>
            <div style="margin-bottom:18px;color:var(--text-secondary)" id="previewMeta"></div>
            <div class="table-wrap">
                <table>
                    <thead id="previewHead"></thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>
        </div>

        <div class="card hidden" id="importCard" style="margin-top:20px">
            <div class="card-header">
                <div class="card-title">Import Options</div>
            </div>
            <div class="form-group hidden" id="passwordGroup">
                <label class="form-label" for="defaultPassword">Initial Password for Imported Students</label>
                <input class="form-control" type="text" id="defaultPassword" value="Password123">
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn btn-primary" type="button" id="startImportBtn">Start Import</button>
                <button class="btn btn-secondary" type="button" id="clearImportBtn">Clear</button>
            </div>
        </div>

        <div class="card hidden" id="resultCard" style="margin-top:20px">
            <div class="card-header">
                <div class="card-title">Import Results</div>
            </div>
            <div class="result-grid">
                <div class="result-card">
                    Successful
                    <strong id="successCount">0</strong>
                </div>
                <div class="result-card">
                    Failed
                    <strong id="failedCount">0</strong>
                </div>
                <div class="result-card">
                    Total Rows
                    <strong id="totalCount">0</strong>
                </div>
            </div>
            <div id="resultMessage" style="margin-bottom:16px;color:var(--text-secondary)"></div>
            <div id="resultErrors"></div>
        </div>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
let currentModule = '<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>';
let uploadedFileToken = '';
let previewRows = [];

document.addEventListener('DOMContentLoaded', () => {
    setActiveModule(currentModule);
    bindEvents();
});

function bindEvents() {
    document.querySelectorAll('[data-module]').forEach((button) => {
        button.addEventListener('click', () => {
            currentModule = button.dataset.module;
            setActiveModule(currentModule);
            clearImportState();
            history.replaceState({}, '', `?module=${currentModule}`);
        });
    });

    document.getElementById('downloadTemplateBtn').addEventListener('click', downloadTemplate);
    document.getElementById('fileInput').addEventListener('change', handleFileSelection);
    document.getElementById('startImportBtn').addEventListener('click', startImport);
    document.getElementById('clearImportBtn').addEventListener('click', clearImportState);
}

function setActiveModule(module) {
    document.querySelectorAll('.import-tab').forEach((button) => {
        button.classList.toggle('active', button.dataset.module === module);
    });
    document.getElementById('passwordGroup').classList.toggle('hidden', module !== 'students');
}

function downloadTemplate() {
    window.location.href = `/api/import/templates.php?type=${encodeURIComponent(currentModule)}`;
}

async function handleFileSelection(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }

    document.getElementById('selectedFileInfo').textContent = `Uploading ${file.name}...`;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch('/api/import?mode=upload', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.error) {
            throw new Error(data.error);
        }

        uploadedFileToken = data.importData.filepath;
        previewRows = data.importData.preview || [];
        document.getElementById('selectedFileInfo').textContent = `${data.importData.originalName} uploaded. ${data.importData.totalRows} rows detected.`;
        renderPreview(previewRows, data.importData.totalRows || previewRows.length);
        document.getElementById('previewCard').classList.remove('hidden');
        document.getElementById('importCard').classList.remove('hidden');
        document.getElementById('resultCard').classList.add('hidden');
        showToast('File uploaded and preview generated');
    } catch (error) {
        uploadedFileToken = '';
        previewRows = [];
        document.getElementById('selectedFileInfo').textContent = '';
        showToast(error.message || 'Upload failed', 'error');
    }
}

function renderPreview(rows, totalRows) {
    const head = document.getElementById('previewHead');
    const body = document.getElementById('previewBody');
    const meta = document.getElementById('previewMeta');

    if (!rows.length) {
        head.innerHTML = '';
        body.innerHTML = '<tr><td style="padding:24px;text-align:center;color:var(--text-muted)">No preview rows available</td></tr>';
        meta.textContent = 'The uploaded file does not contain previewable rows.';
        return;
    }

    const headers = Object.keys(rows[0]);
    head.innerHTML = '<tr>' + headers.map((header) => `<th>${escHtml(header)}</th>`).join('') + '</tr>';
    body.innerHTML = rows.map((row) => '<tr>' + headers.map((header) => `<td>${escHtml(row[header] || '')}</td>`).join('') + '</tr>').join('');
    meta.textContent = `Showing the first ${rows.length} rows out of ${totalRows}.`;
}

async function startImport() {
    if (!uploadedFileToken) {
        showToast('Upload a file first', 'warning');
        return;
    }

    const payload = {
        filepath: uploadedFileToken,
        defaultPassword: document.getElementById('defaultPassword').value.trim()
    };

    try {
        const response = await apiPost(`/api/import?module=${encodeURIComponent(currentModule)}`, payload);
        if (response.error) {
            throw new Error(response.error);
        }
        renderResults(response.results || {});
        document.getElementById('resultMessage').textContent = response.message || 'Import completed.';
        document.getElementById('resultCard').classList.remove('hidden');
        uploadedFileToken = '';
        document.getElementById('fileInput').value = '';
        showToast('Import completed');
    } catch (error) {
        showToast(error.message || 'Import failed', 'error');
    }
}

function renderResults(results) {
    const success = results.success || [];
    const failed = results.failed || [];
    const total = results.total || success.length + failed.length;

    document.getElementById('successCount').textContent = success.length;
    document.getElementById('failedCount').textContent = failed.length;
    document.getElementById('totalCount').textContent = total;

    const errorsContainer = document.getElementById('resultErrors');
    if (!failed.length) {
        errorsContainer.innerHTML = '<div style="color:var(--success)">All rows imported successfully.</div>';
        return;
    }

    errorsContainer.innerHTML = `
        <div style="font-weight:700;margin-bottom:10px">Failed Rows</div>
        <div style="display:grid;gap:8px">
            ${failed.map((item) => `
                <div class="alert alert-danger" style="margin-bottom:0">
                    Row ${escHtml(item.row)}: ${escHtml(item.error)}
                </div>
            `).join('')}
        </div>
    `;
}

function clearImportState() {
    uploadedFileToken = '';
    previewRows = [];
    document.getElementById('fileInput').value = '';
    document.getElementById('selectedFileInfo').textContent = '';
    document.getElementById('previewCard').classList.add('hidden');
    document.getElementById('importCard').classList.add('hidden');
    document.getElementById('resultCard').classList.add('hidden');
    document.getElementById('previewHead').innerHTML = '';
    document.getElementById('previewBody').innerHTML = '';
    document.getElementById('resultErrors').innerHTML = '';
}
</script>
</body>
</html>
