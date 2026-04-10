<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
require_role('superadmin');
$pageTitle = 'Audit Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar"><div style="font-size:18px;font-weight:700">📊 System Audit Logs</div></div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP Address</th></tr></thead>
                    <tbody id="dataTable">
                        <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">Logging started. No logs recorded yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
