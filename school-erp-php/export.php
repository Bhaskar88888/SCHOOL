<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Export Data';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .export-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
        .export-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; transition: var(--transition); }
        .export-card:hover { border-color: var(--accent); transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .export-card-header { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
        .export-icon { font-size: 28px; background: var(--bg-primary); width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; border-radius: 12px; flex-shrink: 0; }
        .export-card-title { font-weight: 700; font-size: 16px; }
        .export-card-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 18px; line-height: 1.5; }
        .export-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .export-filters { margin: 12px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .section-label { font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 28px 0 12px; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-toolbar">
            <div style="font-size:18px;font-weight:700">📤 Export Data</div>
        </div>

        <p style="color:var(--text-muted);margin-bottom:24px">Download school data in CSV/Excel or PDF format. Use filters to narrow your results.</p>

        <div class="section-label">📊 Academic Data</div>
        <div class="export-grid">

            <!-- Students -->
            <div class="export-card">
                <div class="export-card-header">
                    <div class="export-icon">👨‍🎓</div>
                    <div>
                        <div class="export-card-title">Students</div>
                        <span class="badge badge-info">Academic</span>
                    </div>
                </div>
                <div class="export-card-desc">Export student list with class, contact, and admission details.</div>
                <div class="export-filters">
                    <input type="text" class="form-control" id="stuSearch" placeholder="Search name...">
                    <select class="form-control" id="stuClass"><option value="">All Classes</option><?php
                        $classes = db_fetchAll("SELECT id, name FROM classes ORDER BY name");
                        foreach($classes as $c) echo "<option value='{$c['id']}'>" . htmlspecialchars($c['name']) . "</option>";
                    ?></select>
                </div>
                <div class="export-actions">
                    <button class="btn btn-primary" onclick="exportData('students','excel')">📊 Excel / CSV</button>
                    <button class="btn btn-secondary" onclick="exportData('students','pdf')">📄 PDF</button>
                </div>
            </div>

            <!-- Attendance -->
            <div class="export-card">
                <div class="export-card-header">
                    <div class="export-icon">✅</div>
                    <div>
                        <div class="export-card-title">Attendance</div>
                        <span class="badge badge-success">Reports</span>
                    </div>
                </div>
                <div class="export-card-desc">Export student attendance records by date range and class.</div>
                <div class="export-filters">
                    <input type="date" class="form-control" id="attFrom">
                    <input type="date" class="form-control" id="attTo">
                </div>
                <div class="export-actions">
                    <button class="btn btn-primary" onclick="exportData('attendance','excel')">📊 Excel / CSV</button>
                    <button class="btn btn-secondary" onclick="exportData('attendance','pdf')">📄 PDF</button>
                </div>
            </div>

            <!-- Exams -->
            <div class="export-card">
                <div class="export-card-header">
                    <div class="export-icon">📝</div>
                    <div>
                        <div class="export-card-title">Exam Results</div>
                        <span class="badge badge-warning">Academic</span>
                    </div>
                </div>
                <div class="export-card-desc">Export examination results and grade summaries.</div>
                <div style="height:58px"></div>
                <div class="export-actions">
                    <button class="btn btn-primary" onclick="exportData('exams','excel')">📊 Excel / CSV</button>
                    <button class="btn btn-secondary" onclick="exportData('exams','pdf')">📄 PDF</button>
                </div>
            </div>

        </div>

        <div class="section-label">💰 Finance</div>
        <div class="export-grid">

            <!-- Fees -->
            <div class="export-card">
                <div class="export-card-header">
                    <div class="export-icon">💰</div>
                    <div>
                        <div class="export-card-title">Fee Collection</div>
                        <span class="badge badge-success">Finance</span>
                    </div>
                </div>
                <div class="export-card-desc">Export fee payment records, pending dues, and collection reports.</div>
                <div class="export-filters">
                    <input type="date" class="form-control" id="feeFrom">
                    <input type="date" class="form-control" id="feeTo">
                </div>
                <div class="export-actions">
                    <button class="btn btn-primary" onclick="exportData('fees','excel')">📊 Excel / CSV</button>
                    <button class="btn btn-secondary" onclick="exportData('fees','pdf')">📄 PDF</button>
                </div>
            </div>

            <!-- Tally XML -->
            <div class="export-card">
                <div class="export-card-header">
                    <div class="export-icon">🏦</div>
                    <div>
                        <div class="export-card-title">Tally XML</div>
                        <span class="badge badge-info">Accounting</span>
                    </div>
                </div>
                <div class="export-card-desc">Export ledger entries in Tally-compatible XML format for accounting software.</div>
                <div class="export-filters">
                    <input type="date" class="form-control" id="tallyFrom">
                    <input type="date" class="form-control" id="tallyTo">
                </div>
                <div class="export-actions">
                    <button class="btn btn-primary" onclick="exportTally()">🏦 Export Tally XML</button>
                </div>
            </div>

        </div>

        <div class="section-label">👥 HR & Staff</div>
        <div class="export-grid">

            <!-- Staff -->
            <div class="export-card">
                <div class="export-card-header">
                    <div class="export-icon">👔</div>
                    <div>
                        <div class="export-card-title">Staff Directory</div>
                        <span class="badge badge-info">HR</span>
                    </div>
                </div>
                <div class="export-card-desc">Export staff list with employee IDs, roles, and contact details.</div>
                <div style="height:58px"></div>
                <div class="export-actions">
                    <button class="btn btn-primary" onclick="exportData('staff','excel')">📊 Excel / CSV</button>
                    <button class="btn btn-secondary" onclick="exportData('staff','pdf')">📄 PDF</button>
                </div>
            </div>

            <!-- Library -->
            <div class="export-card">
                <div class="export-card-header">
                    <div class="export-icon">📚</div>
                    <div>
                        <div class="export-card-title">Library Books</div>
                        <span class="badge badge-info">Library</span>
                    </div>
                </div>
                <div class="export-card-desc">Export book catalog with availability, ISBN, and author information.</div>
                <div style="height:58px"></div>
                <div class="export-actions">
                    <button class="btn btn-primary" onclick="exportData('library','excel')">📊 Excel / CSV</button>
                    <button class="btn btn-secondary" onclick="exportData('library','pdf')">📄 PDF</button>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function buildParams(module) {
    const p = new URLSearchParams({module});
    if (module === 'students') {
        const s = document.getElementById('stuSearch').value;
        const c = document.getElementById('stuClass').value;
        if (s) p.append('search', s);
        if (c) p.append('class_id', c);
    } else if (module === 'attendance') {
        const f = document.getElementById('attFrom').value;
        const t = document.getElementById('attTo').value;
        if (f) p.append('date_from', f);
        if (t) p.append('date_to', t);
    } else if (module === 'fees') {
        const f = document.getElementById('feeFrom').value;
        const t = document.getElementById('feeTo').value;
        if (f) p.append('date_from', f);
        if (t) p.append('date_to', t);
    }
    return p;
}

function exportData(module, format) {
    const params = buildParams(module);
    const endpoint = format === 'pdf' ? '/api/export/pdf.php' : '/api/export/excel.php';
    showToast(`Preparing ${format.toUpperCase()} export...`);
    window.open(`${endpoint}?${params.toString()}`, '_blank');
}

function exportTally() {
    const from = document.getElementById('tallyFrom').value;
    const to = document.getElementById('tallyTo').value;
    const params = new URLSearchParams();
    if (from) params.append('from', from);
    if (to) params.append('to', to);
    showToast('Preparing Tally XML export...');
    window.open(`/api/export/tally.php?${params.toString()}`, '_blank');
}
</script>
</body>
</html>
