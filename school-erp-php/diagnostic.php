<?php
/**
 * School ERP PHP v3.0 — Diagnostic & Module Health Check
 * =========================================================
 * Access: http://localhost/school-erp-php/diagnostic.php
 * DELETE THIS FILE BEFORE PRODUCTION DEPLOYMENT!
 * =========================================================
 */

// Basic auth guard for this page - simple password check
$DIAG_PASSWORD = 'erp2025';
if (!isset($_GET['access']) || $_GET['access'] !== $DIAG_PASSWORD) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px">Access denied.<br><small>Add ?access=erp2025 to the URL</small></h2>');
}

// Load environment (no auth required for diagnostics)
$envFile = __DIR__ . '/.env.php';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!defined($key)) define($key, $value);
        }
    }
}

// Defaults
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'school_erp');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('APP_NAME')) define('APP_NAME', 'School ERP');
if (!defined('APP_VERSION')) define('APP_VERSION', '3.0');
if (!defined('APP_ENV')) define('APP_ENV', 'local');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost/school-erp-php');
if (!defined('APP_DEBUG')) define('APP_DEBUG', 'true');

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kolkata');

// ── Database connection attempt ─────────────────────────────────────────────
$pdo = null;
$dbError = null;
$dbConnected = false;
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $dbConnected = true;
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// ── Check all expected tables ───────────────────────────────────────────────
$expectedTables = [
    'users', 'classes', 'students', 'attendance', 'fees', 'fee_structures',
    'exams', 'exam_results', 'library_books', 'library_issues', 'hostel_rooms',
    'hostel_allocations', 'hostel_room_types', 'hostel_fee_structures',
    'transport_vehicles', 'bus_routes', 'bus_stops', 'transport_attendance',
    'notices', 'complaints', 'leave_applications', 'payroll', 'salary_structures',
    'homework', 'remarks', 'routine', 'canteen_items', 'canteen_orders',
    'canteen_sales', 'canteen_sale_items', 'notifications', 'notifications_enhanced',
    'audit_logs', 'audit_logs_enhanced', 'chatbot_logs', 'counters',
    'archived_students', 'archived_staff', 'class_subjects', 'staff_attendance_enhanced',
];

$tableStatus = [];
$existingTables = [];
if ($dbConnected) {
    try {
        $result = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $existingTables = array_map('strtolower', $result);
        foreach ($expectedTables as $t) {
            $exists = in_array(strtolower($t), $existingTables);
            $count = 0;
            if ($exists) {
                try { $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); } catch (Exception $e) {}
            }
            $tableStatus[$t] = ['exists' => $exists, 'count' => $count];
        }
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}

// ── Check PHP modules ───────────────────────────────────────────────────────
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session', 'fileinfo', 'openssl', 'curl', 'zip'];
$extStatus = [];
foreach ($requiredExtensions as $ext) {
    $extStatus[$ext] = extension_loaded($ext);
}

// ── Check PHP settings ──────────────────────────────────────────────────────
$phpSettings = [
    'PHP Version' => ['value' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '7.4', '>=')],
    'display_errors' => ['value' => ini_get('display_errors'), 'ok' => true],
    'memory_limit' => ['value' => ini_get('memory_limit'), 'ok' => true],
    'upload_max_filesize' => ['value' => ini_get('upload_max_filesize'), 'ok' => true],
    'post_max_size' => ['value' => ini_get('post_max_size'), 'ok' => true],
    'max_execution_time' => ['value' => ini_get('max_execution_time'), 'ok' => true],
    'session.save_path' => ['value' => session_save_path() ?: sys_get_temp_dir(), 'ok' => true],
];

// ── Check critical files exist ──────────────────────────────────────────────
$criticalModules = [
    'index.php'           => 'Login page',
    'dashboard.php'       => 'Dashboard',
    'students.php'        => 'Students module',
    'attendance.php'      => 'Attendance module',
    'fee.php'             => 'Fee module',
    'exams.php'           => 'Exams module',
    'hr.php'              => 'HR module',
    'payroll.php'         => 'Payroll module',
    'library.php'         => 'Library module',
    'hostel.php'          => 'Hostel module',
    'transport.php'       => 'Transport module',
    'canteen.php'         => 'Canteen module',
    'homework.php'        => 'Homework module',
    'notices.php'         => 'Notices module',
    'routine.php'         => 'Routine/Timetable module',
    'leave.php'           => 'Leave module',
    'complaints.php'      => 'Complaints module',
    'chatbot.php'         => 'AI Chatbot module',
    'remarks.php'         => 'Remarks module',
    'classes.php'         => 'Classes module',
    'notifications.php'   => 'Notifications module',
    'users.php'           => 'Users module',
    'salary-setup.php'    => 'Salary Setup module',
    'staff-attendance.php'=> 'Staff Attendance module',
    'archive.php'         => 'Archive module',
    'export.php'          => 'Export module',
    'audit.php'           => 'Audit Log module',
    'profile.php'         => 'Profile module',
    'forgot_password.php' => 'Forgot Password',
];

$criticalIncludes = [
    'includes/auth.php'          => 'Auth helpers',
    'includes/db.php'            => 'DB connection',
    'includes/env_loader.php'    => 'Env loader',
    'includes/sidebar.php'       => 'Sidebar navigation',
    'includes/header.php'        => 'Header component',
    'includes/helpers.php'       => 'Helper functions',
    'includes/validator.php'     => 'Input validator',
    'includes/api_response.php'  => 'API response helpers',
    'includes/rate_limiter.php'  => 'Rate limiter',
    'includes/audit_logger.php'  => 'Audit logger',
    'includes/secure_upload.php' => 'Secure upload handler',
    'includes/excel_export.php'  => 'Excel/CSV export',
    'includes/chatbot_knowledge_en.php' => 'Chatbot knowledge base',
    'config/env.php'             => 'Config env',
    'assets/css/style.css'       => 'Main stylesheet',
    'assets/js/main.js'          => 'Main JavaScript',
    'setup.sql'                  => 'Database setup SQL',
];

$fileStatus = [];
foreach (array_merge($criticalModules, $criticalIncludes) as $file => $label) {
    $fileStatus[$file] = file_exists(__DIR__ . '/' . $file);
}

// ── Count missing tables and files ─────────────────────────────────────────
$missingTables = array_filter($tableStatus, fn($v) => !$v['exists']);
$missingFiles  = array_filter($fileStatus, fn($v) => !$v);
$missingExts   = array_filter($extStatus, fn($v) => !$v);
$allGood       = $dbConnected && empty($missingTables) && empty($missingFiles) && empty($missingExts);

// ── Check uploads directory ─────────────────────────────────────────────────
$uploadsWritable = is_writable(__DIR__ . '/uploads');
$tmpWritable     = is_writable(__DIR__ . '/tmp') || is_writable(sys_get_temp_dir());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnostic — School ERP</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Outfit', sans-serif;
    background: #f4f4f5;
    color: #111;
    min-height: 100vh;
    padding: 32px 16px 64px;
}
.container { max-width: 980px; margin: 0 auto; }
.page-title {
    font-size: 26px; font-weight: 700;
    margin-bottom: 4px;
}
.page-sub {
    font-size: 13px; color: #666;
    margin-bottom: 28px;
}
.delete-warning {
    background: #fff3cd; border: 1px solid #ffc107;
    border-radius: 10px; padding: 14px 18px;
    font-size: 13px; color: #856404;
    margin-bottom: 24px;
    display: flex; align-items: center; gap: 10px;
}
.status-banner {
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex; align-items: center; gap: 16px;
}
.status-banner.ok { background: #f0fdf4; border: 1px solid #bbf7d0; }
.status-banner.fail { background: #fef2f2; border: 1px solid #fecaca; }
.status-dot { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.status-dot.ok { background: #dcfce7; }
.status-dot.fail { background: #fee2e2; }
.status-text { font-size: 17px; font-weight: 600; }
.status-text.ok { color: #16a34a; }
.status-text.fail { color: #dc2626; }
.status-sub { font-size: 13px; color: #666; margin-top: 3px; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
@media (max-width: 680px) { .grid { grid-template-columns: 1fr; } }
.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}
.card-head {
    padding: 14px 18px;
    border-bottom: 1px solid #f0f0f0;
    display: flex; align-items: center; justify-content: space-between;
}
.card-title { font-size: 14px; font-weight: 600; color: #111; }
.card-count { font-size: 12px; color: #888; }
.card-body { padding: 0; }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #999; padding: 8px 14px; text-align: left; border-bottom: 1px solid #f0f0f0; }
td { padding: 9px 14px; font-size: 13px; border-bottom: 1px solid #f9f9f9; }
tr:last-child td { border-bottom: none; }
.badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
.badge-ok { background: #dcfce7; color: #16a34a; }
.badge-fail { background: #fee2e2; color: #dc2626; }
.badge-info { background: #e0f2fe; color: #0369a1; }
.badge-warn { background: #fff3cd; color: #856404; }
.full-card { grid-column: 1 / -1; }
.mono { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #555; }
.section-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #aaa; margin: 24px 0 8px; }
.count-chip { font-size: 11px; background: #f0f0f0; color: #666; border-radius: 20px; padding: 2px 7px; }
.count-chip.green { background: #dcfce7; color: #16a34a; }
.count-chip.red { background: #fee2e2; color: #dc2626; }
</style>
</head>
<body>
<div class="container">

    <div class="page-title">🔍 School ERP — Diagnostic Panel</div>
    <div class="page-sub">Environment: <strong><?= htmlspecialchars(APP_ENV) ?></strong> &nbsp;|&nbsp; Server: <strong><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'localhost') ?></strong> &nbsp;|&nbsp; Time: <strong><?= date('d M Y H:i:s') ?></strong></div>

    <div class="delete-warning">
        ⚠️ <strong>IMPORTANT:</strong> Delete or password-protect this file before deploying to production! URL: <code>diagnostic.php?access=erp2025</code>
    </div>

    <!-- Overall Status Banner -->
    <div class="status-banner <?= $allGood ? 'ok' : 'fail' ?>">
        <div class="status-dot <?= $allGood ? 'ok' : 'fail' ?>"><?= $allGood ? '✅' : '❌' ?></div>
        <div>
            <div class="status-text <?= $allGood ? 'ok' : 'fail' ?>"><?= $allGood ? 'System Healthy — All checks passed!' : 'Issues Detected — See details below' ?></div>
            <div class="status-sub">
                <?php
                $issues = [];
                if (!$dbConnected) $issues[] = 'Database not connected';
                if (!empty($missingTables)) $issues[] = count($missingTables) . ' missing table(s)';
                if (!empty($missingFiles)) $issues[] = count($missingFiles) . ' missing file(s)';
                if (!empty($missingExts)) $issues[] = count($missingExts) . ' missing PHP extension(s)';
                echo $allGood ? 'All modules, tables, and files verified.' : implode(' · ', $issues);
                ?>
            </div>
        </div>
    </div>

    <!-- Database + PHP -->
    <div class="grid">

        <!-- Database Status -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Database Connection</div>
                <span class="badge <?= $dbConnected ? 'badge-ok' : 'badge-fail' ?>"><?= $dbConnected ? 'CONNECTED' : 'FAILED' ?></span>
            </div>
            <div class="card-body">
                <table>
                    <tr><td>Host</td><td class="mono"><?= htmlspecialchars(DB_HOST) ?></td></tr>
                    <tr><td>Database</td><td class="mono"><?= htmlspecialchars(DB_NAME) ?></td></tr>
                    <tr><td>User</td><td class="mono"><?= htmlspecialchars(DB_USER) ?></td></tr>
                    <tr><td>Charset</td><td class="mono"><?= htmlspecialchars(DB_CHARSET) ?></td></tr>
                    <tr>
                        <td>Status</td>
                        <td>
                            <?php if ($dbConnected): ?>
                                <span class="badge badge-ok">OK</span>
                            <?php else: ?>
                                <span class="badge badge-fail">ERROR</span>
                                <div style="font-size:11px;color:#dc2626;margin-top:4px;font-family:monospace"><?= htmlspecialchars($dbError ?? '') ?></div>
                                <div style="font-size:11px;color:#666;margin-top:6px">
                                    Fix: Create database "<strong><?= htmlspecialchars(DB_NAME) ?></strong>" in phpMyAdmin,<br>
                                    then import <code>setup.sql</code>.
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($dbConnected): ?>
                    <tr><td>Total Tables</td><td class="mono"><?= count($existingTables) ?> found</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- PHP Extensions -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">PHP Extensions</div>
                <span class="count-chip <?= empty($missingExts) ? 'green' : 'red' ?>"><?= count($extStatus) - count($missingExts) ?>/<?= count($extStatus) ?> loaded</span>
            </div>
            <div class="card-body">
                <table>
                    <?php foreach ($extStatus as $ext => $loaded): ?>
                    <tr>
                        <td><?= htmlspecialchars($ext) ?></td>
                        <td><span class="badge <?= $loaded ? 'badge-ok' : 'badge-fail' ?>"><?= $loaded ? 'OK' : 'MISSING' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- PHP Settings -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">PHP Configuration</div>
                <span class="badge badge-info">v<?= PHP_VERSION ?></span>
            </div>
            <div class="card-body">
                <table>
                    <?php foreach ($phpSettings as $key => $info): ?>
                    <tr>
                        <td><?= htmlspecialchars($key) ?></td>
                        <td class="mono"><span class="badge <?= $info['ok'] ? 'badge-ok' : 'badge-warn' ?>"><?= htmlspecialchars($info['value']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Permissions -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">File Permissions</div>
            </div>
            <div class="card-body">
                <table>
                    <tr>
                        <td>uploads/ directory</td>
                        <td>
                            <span class="badge <?= $uploadsWritable ? 'badge-ok' : 'badge-fail' ?>"><?= $uploadsWritable ? 'WRITABLE' : 'NOT WRITABLE' ?></span>
                            <?php if (!$uploadsWritable): ?>
                            <div style="font-size:11px;color:#dc2626;margin-top:4px">Create or chmod 755 the uploads/ folder</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>tmp/ directory</td>
                        <td><span class="badge <?= $tmpWritable ? 'badge-ok' : 'badge-warn' ?>"><?= $tmpWritable ? 'WRITABLE' : 'CHECK' ?></span></td>
                    </tr>
                    <tr>
                        <td>.env.php</td>
                        <td><span class="badge <?= file_exists(__DIR__.'/.env.php') ? 'badge-ok' : 'badge-fail' ?>"><?= file_exists(__DIR__.'/.env.php') ? 'EXISTS' : 'MISSING' ?></span></td>
                    </tr>
                    <tr>
                        <td>APP_ENV</td>
                        <td><span class="badge <?= APP_ENV === 'local' ? 'badge-info' : 'badge-warn' ?>"><?= htmlspecialchars(APP_ENV) ?></span></td>
                    </tr>
                    <tr>
                        <td>APP_DEBUG</td>
                        <td><span class="badge <?= filter_var(APP_DEBUG, FILTER_VALIDATE_BOOLEAN) ? 'badge-warn' : 'badge-ok' ?>"><?= htmlspecialchars(APP_DEBUG) ?></span></td>
                    </tr>
                </table>
            </div>
        </div>

    </div>

    <!-- Database Tables -->
    <div class="section-label">Database Tables (<?= count($tableStatus) - count($missingTables) ?>/<?= count($tableStatus) ?> exist)</div>
    <div class="card full-card" style="margin-bottom:20px">
        <div class="card-head">
            <div class="card-title">All Required Tables</div>
            <?php if (!empty($missingTables)): ?>
                <span class="badge badge-fail"><?= count($missingTables) ?> MISSING — Run setup.sql!</span>
            <?php else: ?>
                <span class="badge badge-ok">All Present</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                        <th>Records</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableStatus as $table => $info): ?>
                    <tr>
                        <td class="mono"><?= htmlspecialchars($table) ?></td>
                        <td><span class="badge <?= $info['exists'] ? 'badge-ok' : 'badge-fail' ?>"><?= $info['exists'] ? 'OK' : 'MISSING' ?></span></td>
                        <td class="mono"><?= $info['exists'] ? number_format($info['count']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Module Files -->
    <div class="section-label">Module Files (<?= count($criticalModules) - count(array_filter($fileStatus, fn($v,$k) => !$v && array_key_exists($k, $criticalModules), ARRAY_FILTER_USE_BOTH)) ?>/<?= count($criticalModules) ?> present)</div>
    <div class="grid">
        <div class="card">
            <div class="card-head">
                <div class="card-title">PHP Module Pages</div>
                <span class="count-chip"><?= count($criticalModules) ?> modules</span>
            </div>
            <div class="card-body">
                <table>
                    <thead><tr><th>File</th><th>Description</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($criticalModules as $file => $desc): ?>
                        <tr>
                            <td class="mono"><?= htmlspecialchars($file) ?></td>
                            <td style="color:#666;font-size:12px"><?= htmlspecialchars($desc) ?></td>
                            <td><span class="badge <?= $fileStatus[$file] ? 'badge-ok' : 'badge-fail' ?>"><?= $fileStatus[$file] ? 'OK' : 'MISSING' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-title">Includes & Assets</div>
                <span class="count-chip"><?= count($criticalIncludes) ?> files</span>
            </div>
            <div class="card-body">
                <table>
                    <thead><tr><th>File</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($criticalIncludes as $file => $label): ?>
                        <tr>
                            <td class="mono"><?= htmlspecialchars($file) ?></td>
                            <td><span class="badge <?= $fileStatus[$file] ? 'badge-ok' : 'badge-fail' ?>"><?= $fileStatus[$file] ? 'OK' : 'MISSING' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick fix instructions -->
    <?php if (!$dbConnected || !empty($missingTables)): ?>
    <div class="section-label">Fix Instructions</div>
    <div class="card full-card">
        <div class="card-head"><div class="card-title">🛠 How to Fix Database Issues</div></div>
        <div class="card-body" style="padding:18px">
            <ol style="padding-left:20px;line-height:2;font-size:14px">
                <li>Open <strong>XAMPP Control Panel</strong> → Start <strong>Apache</strong> and <strong>MySQL</strong></li>
                <li>Open <a href="http://localhost/phpmyadmin" target="_blank" style="color:#2563eb">http://localhost/phpmyadmin</a></li>
                <li>Click <strong>New</strong> on the left sidebar → Create database: <code><?= htmlspecialchars(DB_NAME) ?></code> with charset <code>utf8mb4_unicode_ci</code></li>
                <li>Select the database <code><?= htmlspecialchars(DB_NAME) ?></code> → Click <strong>Import</strong></li>
                <li>Choose file: <code>school-erp-php/setup.sql</code> → Click <strong>Go</strong></li>
                <li>Refresh this page to verify</li>
            </ol>
            <p style="margin-top:12px;font-size:13px;color:#666">
                After import, login at: <a href="<?= htmlspecialchars(APP_URL) ?>" target="_blank" style="color:#2563eb"><?= htmlspecialchars(APP_URL) ?></a><br>
                Username: <code>admin@school.com</code> &nbsp; Password: <code>password</code>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Links -->
    <div class="section-label">Quick Links</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:32px">
        <a href="<?= htmlspecialchars(APP_URL) ?>/" target="_blank" style="text-decoration:none;background:#0a0a0a;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500">🏠 Open App</a>
        <a href="http://localhost/phpmyadmin" target="_blank" style="text-decoration:none;background:#f59e0b;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500">🗄️ phpMyAdmin</a>
        <a href="<?= htmlspecialchars(APP_URL) ?>/api/health.php" target="_blank" style="text-decoration:none;background:#2563eb;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500">📡 API Health JSON</a>
        <a href="<?= htmlspecialchars(APP_URL) ?>/diagnostic.php?access=erp2025" style="text-decoration:none;background:#6b7280;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500">🔄 Refresh</a>
    </div>

    <div style="font-size:12px;color:#aaa;text-align:center">
        School ERP v<?= APP_VERSION ?> &middot; Diagnostic Page &middot; <?= date('Y') ?> &middot; <strong>DELETE BEFORE PRODUCTION</strong>
    </div>

</div>
</body>
</html>
