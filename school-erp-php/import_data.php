<?php
/**
 * Import Data Page - Import Students, Staff, Fees from CSV/Excel
 * School ERP PHP v3.0
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';

require_auth();
require_role(['admin', 'superadmin', 'hr']);

include 'includes/header.php';
include 'includes/sidebar.php';

$module = $_GET['module'] ?? 'students';
?>

<div class="main-content">
    <div class="page-header">
        <h1>📥 Import Data</h1>
    </div>

    <div class="tabs">
        <a href="?module=students" class="tab <?= $module === 'students' ? 'active' : '' ?>">Students</a>
        <a href="?module=staff" class="tab <?= $module === 'staff' ? 'active' : '' ?>">Staff</a>
        <a href="?module=fees" class="tab <?= $module === 'fees' ? 'active' : '' ?>">Fees</a>
    </div>

    <div class="import-container">
        <div class="import-instructions">
            <h2>Import <?= ucfirst($module) ?></h2>
            <p>Upload a CSV or Excel file to import <?= $module ?>.</p>

            <div class="template-download">
                <h3>📋 Required Format:</h3>
                <div id="formatInfo"></div>
                <button class="btn btn-secondary" onclick="downloadTemplate()">Download Template</button>
            </div>

            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">📁</div>
                <p>Drag & drop your file here or click to browse</p>
                <input type="file" id="fileInput" accept=".csv,.xlsx,.xls" style="display: none;">
            </div>

            <div id="fileInfo" class="file-info" style="display: none;">
                <span id="fileName"></span>
                <button class="btn btn-sm btn-error" onclick="clearFile()">✕</button>
            </div>
        </div>

        <div class="import-actions">
            <button class="btn btn-primary" id="importBtn" onclick="importData()" disabled>
                Import Data
            </button>
        </div>
    </div>

    <div id="result" class="import-result" style="display: none;">
        <h3>Import Results</h3>
        <div id="resultContent"></div>
    </div>
</div>

<script>
const currentModule = '<?= $module ?>';
let selectedFile = null;

const formats = {
    students: {
        headers: ['Name', 'Admission No', 'Class', 'DOB', 'Gender', 'Parent Name', 'Parent Phone', 'Phone', 'Email', 'Address'],
        required: ['Name', 'Class'],
        description: 'Import students with their basic information'
    },
    staff: {
        headers: ['Name', 'Email', 'Role', 'Employee ID', 'Department', 'Designation', 'Phone', 'Password'],
        required: ['Name', 'Email', 'Role'],
        description: 'Import staff members (teachers, admin, etc.)'
    },
    fees: {
        headers: ['Admission No', 'Fee Type', 'Total Amount', 'Amount Paid', 'Payment Method', 'Paid Date', 'Month', 'Year', 'Receipt No'],
        required: ['Admission No', 'Total Amount'],
        description: 'Import fee payment records'
    }
};

document.addEventListener('DOMContentLoaded', () => {
    showFormatInfo();
    setupUploadArea();
});

function showFormatInfo() {
    const format = formats[currentModule];
    const div = document.getElementById('formatInfo');
    
    div.innerHTML = `
        <p><strong>${format.description}</strong></p>
        <p><strong>Required columns:</strong> ${format.required.join(', ')}</p>
        <p><strong>All columns:</strong> ${format.headers.join(', ')}</p>
    `;
}

function setupUploadArea() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    
    uploadArea.addEventListener('click', () => fileInput.click());
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        if (e.dataTransfer.files.length) {
            handleFile(e.dataTransfer.files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) {
            handleFile(e.target.files[0]);
        }
    });
}

function handleFile(file) {
    const allowedTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    
    if (!allowedTypes.includes(file.type) && !file.name.endsWith('.csv')) {
        alert('Only CSV and Excel files are allowed');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        return;
    }
    
    selectedFile = file;
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileInfo').style.display = 'flex';
    document.getElementById('uploadArea').style.display = 'none';
    document.getElementById('importBtn').disabled = false;
}

function clearFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('uploadArea').style.display = 'flex';
    document.getElementById('importBtn').disabled = true;
}

function downloadTemplate() {
    const format = formats[currentModule];
    const csv = format.headers.join(',') + '\n';
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${currentModule}_template.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

async function importData() {
    if (!selectedFile) {
        alert('Please select a file first');
        return;
    }
    
    const importBtn = document.getElementById('importBtn');
    importBtn.disabled = true;
    importBtn.textContent = 'Importing...';
    
    const formData = new FormData();
    formData.append('file', selectedFile);
    
    try {
        const response = await fetch(`/api/import?module=${currentModule}`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        showResult(data);
        
        if (!data.error) {
            clearFile();
        }
    } catch (error) {
        showResult({ error: 'Import failed: ' + error.message });
    } finally {
        importBtn.disabled = false;
        importBtn.textContent = 'Import Data';
    }
}

function showResult(data) {
    const resultDiv = document.getElementById('result');
    const contentDiv = document.getElementById('resultContent');
    
    resultDiv.style.display = 'block';
    
    if (data.error) {
        contentDiv.innerHTML = `<div class="alert alert-error">❌ ${data.error}</div>`;
        return;
    }
    
    let html = `
        <div class="alert alert-success">
            ✅ ${data.message}
        </div>
        <div class="import-stats">
            <p><strong>Imported:</strong> ${data.imported} records</p>
            <p><strong>Total rows:</strong> ${data.total_rows}</p>
        </div>
    `;
    
    if (data.errors && data.errors.length > 0) {
        html += `
            <div class="import-errors">
                <h4>Errors (${data.errors.length}):</h4>
                <ul>
                    ${data.errors.slice(0, 10).map(e => `<li>${e}</li>`).join('')}
                    ${data.errors.length > 10 ? `<li>... and ${data.errors.length - 10} more errors</li>` : ''}
                </ul>
            </div>
        `;
    }
    
    contentDiv.innerHTML = html;
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

.import-container {
    max-width: 800px;
}

.import-instructions {
    background: var(--card-bg);
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.template-download {
    background: var(--secondary-bg);
    padding: 20px;
    border-radius: 6px;
    margin: 20px 0;
}

.upload-area {
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    margin: 20px 0;
}

.upload-area:hover, .upload-area.dragover {
    border-color: var(--primary-color);
    background: var(--secondary-bg);
}

.upload-icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.file-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--secondary-bg);
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
}

.import-actions {
    text-align: center;
    margin: 20px 0;
}

.import-result {
    background: var(--card-bg);
    padding: 30px;
    border-radius: 8px;
    margin-top: 20px;
}

.import-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.import-errors {
    background: rgba(220, 53, 69, 0.1);
    padding: 15px;
    border-radius: 6px;
    margin-top: 15px;
}

.import-errors ul {
    margin: 10px 0;
    padding-left: 20px;
}

.import-errors li {
    color: var(--text-error);
    margin: 5px 0;
}
</style>

<?php include 'includes/footer.php'; ?>
