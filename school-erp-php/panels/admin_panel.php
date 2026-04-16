<?php
/**
 * Admin Panel
 */
$totalStudents   = db_count("SELECT COUNT(*) FROM students WHERE is_active=1");
$todayAtt        = db_count("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='present'");
$monthRevenue    = (float)(db_fetch("SELECT COALESCE(SUM(amount_paid),0) AS t FROM fees WHERE YEAR(paid_date)=YEAR(NOW()) AND MONTH(paid_date)=MONTH(NOW())")['t'] ?? 0);
$pendingFees     = (float)(db_fetch("SELECT COALESCE(SUM(balance_amount),0) AS t FROM fees WHERE balance_amount>0")['t'] ?? 0);
$pendingLeave    = db_table_exists('leave_requests') ? db_count("SELECT COUNT(*) FROM leave_requests WHERE status='pending'") : 0;
$pendingComps    = db_table_exists('complaints')     ? db_count("SELECT COUNT(*) FROM complaints WHERE status='pending'") : 0;
$recentStudents  = db_fetchAll("SELECT s.name, s.admission_no, c.name AS class_name, s.created_at FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.is_active=1 ORDER BY s.created_at DESC LIMIT 5");
$recentFees      = db_fetchAll("SELECT f.receipt_no, f.amount_paid, f.fee_type, s.name AS student_name, f.created_at FROM fees f LEFT JOIN students s ON f.student_id=s.id ORDER BY f.created_at DESC LIMIT 5");

$adminModules = [
    ['key'=>'students','icon'=>'👥','label'=>'Students',   'desc'=>'Manage student records'],
    ['key'=>'attendance','icon'=>'📋','label'=>'Attendance','desc'=>'Daily attendance'],
    ['key'=>'fee','icon'=>'💰','label'=>'Fee',              'desc'=>'Fee collection'],
    ['key'=>'exams','icon'=>'📝','label'=>'Exams',          'desc'=>'Tests & grades'],
    ['key'=>'hr','icon'=>'🏢','label'=>'HR/Staff',          'desc'=>'Staff management'],
    ['key'=>'payroll','icon'=>'💵','label'=>'Payroll',       'desc'=>'Salary processing'],
    ['key'=>'library','icon'=>'📚','label'=>'Library',       'desc'=>'Books & issues'],
    ['key'=>'hostel','icon'=>'🏠','label'=>'Hostel',         'desc'=>'Room allocation'],
    ['key'=>'transport','icon'=>'🚌','label'=>'Transport',   'desc'=>'Routes & vehicles'],
    ['key'=>'canteen','icon'=>'🍽','label'=>'Canteen',       'desc'=>'Menu & sales'],
    ['key'=>'notices','icon'=>'📢','label'=>'Notices',       'desc'=>'Announcements'],
    ['key'=>'communication','icon'=>'💬','label'=>'Comms',  'desc'=>'Complaints & routing'],
    ['key'=>'messages','icon'=>'✉','label'=>'Messages',     'desc'=>'Direct messaging'],
    ['key'=>'leave','icon'=>'⏰','label'=>'Leave',           'desc'=>'Leave approvals'],
    ['key'=>'classes','icon'=>'🏫','label'=>'Classes',       'desc'=>'Class sections'],
    ['key'=>'users','icon'=>'👤','label'=>'Users',           'desc'=>'All accounts'],
    ['key'=>'export','icon'=>'📊','label'=>'Export',         'desc'=>'Download data'],
    ['key'=>'archive','icon'=>'🗂','label'=>'Archive',       'desc'=>'Archived records'],
];
?>
<style>
.adm-kpi { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px; }
.adm-kpi-card { background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px 18px;border-left:4px solid var(--kc,#6366f1); }
.adm-kpi-v { font-size:28px;font-weight:800; }
.adm-kpi-l { font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-top:4px; }
.adm-mods { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:28px; }
.adm-mod { background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:16px;text-decoration:none;color:inherit;display:block;transition:transform .15s,border-color .15s; }
.adm-mod:hover { transform:translateY(-3px);border-color:var(--accent); }
.adm-mod-icon { font-size:24px;margin-bottom:8px; }
.adm-mod-label { font-size:13px;font-weight:700; }
.adm-mod-desc { font-size:11px;color:var(--text-muted);margin-top:2px; }
.adm-tables { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
@media(max-width:640px){ .adm-tables{grid-template-columns:1fr;} }
.sh { font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:14px; }
.mini-row { display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px; }
.mini-row:last-child { border-bottom:none; }
.alert-pill { padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;margin-left:auto; }
.alert-warn { background:rgba(251,191,36,.15);color:#fbbf24; }
.alert-danger { background:rgba(239,68,68,.15);color:#ef4444; }
.qa { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px; }
.qa a { display:inline-flex;align-items:center;gap:5px;padding:8px 14px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--bg-card);color:var(--text-primary);text-decoration:none;transition:background .15s; }
.qa a:hover { background:var(--accent);color:#fff;border-color:var(--accent); }
</style>

<div style="font-size:22px;font-weight:800;margin-bottom:20px">
    Good <?= (date('H')<12)?'morning':((date('H')<17)?'afternoon':'evening') ?>,
    <?= htmlspecialchars(explode(' ', get_authenticated_user()['name'])[0]) ?> 👋
</div>

<!-- KPIs -->
<div class="adm-kpi">
    <div class="adm-kpi-card" style="--kc:#6366f1">
        <div class="adm-kpi-v"><?= $totalStudents ?></div>
        <div class="adm-kpi-l">Students</div>
    </div>
    <div class="adm-kpi-card" style="--kc:#10b981">
        <div class="adm-kpi-v"><?= $todayAtt ?></div>
        <div class="adm-kpi-l">Present Today</div>
    </div>
    <div class="adm-kpi-card" style="--kc:#f59e0b">
        <div class="adm-kpi-v">₹<?= number_format($monthRevenue,0) ?></div>
        <div class="adm-kpi-l">Month Revenue</div>
    </div>
    <div class="adm-kpi-card" style="--kc:#ef4444">
        <div class="adm-kpi-v">₹<?= number_format($pendingFees,0) ?></div>
        <div class="adm-kpi-l">Dues Pending</div>
    </div>
</div>

<!-- Alerts Row -->
<?php if ($pendingLeave > 0 || $pendingComps > 0): ?>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
    <?php if ($pendingLeave > 0): ?>
    <a href="<?= BASE_URL ?>/leave.php" style="text-decoration:none">
        <span class="alert-pill alert-warn">⏰ <?= $pendingLeave ?> Leave Requests Pending</span>
    </a>
    <?php endif; ?>
    <?php if ($pendingComps > 0): ?>
    <a href="<?= BASE_URL ?>/communication.php" style="text-decoration:none">
        <span class="alert-pill alert-danger">💬 <?= $pendingComps ?> Complaints Unresolved</span>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="qa">
    <a href="<?= BASE_URL ?>/students.php">👥 Students</a>
    <a href="<?= BASE_URL ?>/fee.php">💰 Fee</a>
    <a href="<?= BASE_URL ?>/notices.php">📢 Notice</a>
    <a href="<?= BASE_URL ?>/messages.php">✉ Message</a>
    <a href="<?= BASE_URL ?>/leave.php">⏰ Leave</a>
    <a href="<?= BASE_URL ?>/users.php">👤 Users</a>
</div>

<!-- Module Grid -->
<div class="sh">Modules</div>
<div class="adm-mods" style="margin-bottom:28px">
<?php foreach ($adminModules as $m): ?>
    <a class="adm-mod" href="<?= BASE_URL ?>/<?= htmlspecialchars($m['key']) ?>.php">
        <div class="adm-mod-icon"><?= $m['icon'] ?></div>
        <div class="adm-mod-label"><?= htmlspecialchars($m['label']) ?></div>
        <div class="adm-mod-desc"><?= htmlspecialchars($m['desc']) ?></div>
    </a>
<?php endforeach; ?>
</div>

<!-- Recent Tables -->
<div class="adm-tables">
    <div class="card" style="padding:20px">
        <div class="sh">Recent Admissions</div>
        <?php if (empty($recentStudents)): ?>
            <div style="color:var(--text-muted);font-size:13px">No recent admissions.</div>
        <?php else: foreach ($recentStudents as $s): ?>
        <div class="mini-row">
            <div>
                <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($s['class_name'] ?? '-') ?> · <?= htmlspecialchars($s['admission_no'] ?? '') ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card" style="padding:20px">
        <div class="sh">Recent Fee Receipts</div>
        <?php if (empty($recentFees)): ?>
            <div style="color:var(--text-muted);font-size:13px">No recent receipts.</div>
        <?php else: foreach ($recentFees as $f): ?>
        <div class="mini-row">
            <div>
                <div style="font-weight:600"><?= htmlspecialchars($f['student_name'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($f['fee_type'] ?? '') ?> · <?= htmlspecialchars($f['receipt_no'] ?? '') ?></div>
            </div>
            <div style="font-weight:700;color:#10b981">₹<?= number_format((float)$f['amount_paid'],0) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
