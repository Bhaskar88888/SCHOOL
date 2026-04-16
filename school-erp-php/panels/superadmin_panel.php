<?php
/**
 * SuperAdmin Panel — Module Launcher + Full KPI Dashboard
 */
$totalStudents  = db_count("SELECT COUNT(*) FROM students WHERE is_active = 1");
$totalStaff     = db_count("SELECT COUNT(*) FROM users WHERE role NOT IN ('student','parent') AND is_active = 1");
$totalParents   = db_count("SELECT COUNT(*) FROM users WHERE role = 'parent' AND is_active = 1");
$monthRevenue   = (float)(db_fetch("SELECT COALESCE(SUM(amount_paid),0) AS t FROM fees WHERE YEAR(paid_date)=YEAR(NOW()) AND MONTH(paid_date)=MONTH(NOW())")['t'] ?? 0);
$pendingFees    = (float)(db_fetch("SELECT COALESCE(SUM(balance_amount),0) AS t FROM fees WHERE balance_amount > 0")['t'] ?? 0);
$pendingComps   = db_table_exists('complaints') ? db_count("SELECT COUNT(*) FROM complaints WHERE status='pending'") : 0;
$pendingLeave   = db_table_exists('leave_applications') ? db_count("SELECT COUNT(*) FROM leave_applications WHERE status='pending'") : 0;
$todayAttendance = db_count("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='present'");

$recentAudit    = db_table_exists('audit_log')
    ? db_fetchAll("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 8")
    : [];

// Chart data — 7 days attendance
$chartDates = $chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chartDates[] = date('D', strtotime($day));
    $chartData[]  = db_count("SELECT COUNT(*) FROM attendance WHERE date=? AND status='present'", [$day]);
}

$modules = [
    ['key'=>'students',      'icon'=>'👥', 'label'=>'Students',       'desc'=>'Admissions & profiles',       'badge'=> $totalStudents . ' active'],
    ['key'=>'attendance',    'icon'=>'📋', 'label'=>'Attendance',      'desc'=>'Daily roll call',              'badge'=> $todayAttendance . ' today'],
    ['key'=>'fee',           'icon'=>'💰', 'label'=>'Fee',             'desc'=>'Collections & receipts',      'badge'=>'₹'.number_format($monthRevenue/100000,1).'L this month'],
    ['key'=>'exams',         'icon'=>'📝', 'label'=>'Exams',           'desc'=>'Tests & results',             'badge'=>''],
    ['key'=>'library',       'icon'=>'📚', 'label'=>'Library',         'desc'=>'Books & issues',              'badge'=>''],
    ['key'=>'hr',            'icon'=>'🏢', 'label'=>'HR / Staff',      'desc'=>'Staff profiles',              'badge'=> $totalStaff . ' staff'],
    ['key'=>'payroll',       'icon'=>'💵', 'label'=>'Payroll',         'desc'=>'Salaries & slips',            'badge'=>''],
    ['key'=>'hostel',        'icon'=>'🏠', 'label'=>'Hostel',          'desc'=>'Rooms & allocations',         'badge'=>''],
    ['key'=>'transport',     'icon'=>'🚌', 'label'=>'Transport',       'desc'=>'Routes & vehicles',           'badge'=>''],
    ['key'=>'canteen',       'icon'=>'🍽', 'label'=>'Canteen',         'desc'=>'Menu & sales',                'badge'=>''],
    ['key'=>'homework',      'icon'=>'📖', 'label'=>'Homework',        'desc'=>'Assignments',                 'badge'=>''],
    ['key'=>'notices',       'icon'=>'📢', 'label'=>'Notices',         'desc'=>'School announcements',        'badge'=>''],
    ['key'=>'routine',       'icon'=>'🗓', 'label'=>'Timetable',       'desc'=>'Class schedules',             'badge'=>''],
    ['key'=>'leave',         'icon'=>'⏰', 'label'=>'Leave',           'desc'=>'Staff leave requests',        'badge'=> $pendingLeave . ' pending'],
    ['key'=>'communication', 'icon'=>'💬', 'label'=>'Comms Hub',       'desc'=>'Complaints & notices',        'badge'=> $pendingComps . ' pending'],
    ['key'=>'messages',      'icon'=>'✉',  'label'=>'Messages',        'desc'=>'Direct in-app messaging',     'badge'=>''],
    ['key'=>'chatbot',       'icon'=>'🤖', 'label'=>'AI Chatbot',      'desc'=>'Multilingual assistant',      'badge'=>''],
    ['key'=>'remarks',       'icon'=>'📝', 'label'=>'Remarks',         'desc'=>'Teacher remarks',             'badge'=>''],
    ['key'=>'classes',       'icon'=>'🏫', 'label'=>'Classes',         'desc'=>'Class & section setup',       'badge'=>''],
    ['key'=>'users',         'icon'=>'👤', 'label'=>'Users',           'desc'=>'All user accounts',           'badge'=> ($totalStaff+$totalParents) . ' accounts'],
    ['key'=>'salary-setup',  'icon'=>'💳', 'label'=>'Salary Setup',    'desc'=>'Pay structures',              'badge'=>''],
    ['key'=>'staff-attendance','icon'=>'✅','label'=>'Staff Attend.',   'desc'=>'Staff daily presence',        'badge'=>''],
    ['key'=>'archive',       'icon'=>'🗂', 'label'=>'Archive',         'desc'=>'Archived records',            'badge'=>''],
    ['key'=>'export',        'icon'=>'📊', 'label'=>'Export Data',     'desc'=>'CSV exports',                 'badge'=>''],
    ['key'=>'audit',         'icon'=>'🔍', 'label'=>'Audit Log',       'desc'=>'System activity trail',       'badge'=>''],
];
?>
<style>
.sa-hero { background: linear-gradient(135deg, #1a1f35 0%, var(--accent-hover) 100%); border-radius: 16px; padding: 32px 36px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.sa-hero-title { font-size: 26px; font-weight: 800; color: #fff; }
.sa-hero-sub { color: #94a3b8; font-size: 14px; margin-top: 4px; }
.sa-hero-badge { background: rgba(99,102,241,.25); border: 1px solid rgba(99,102,241,.5); color: #a5b4fc; padding: 8px 18px; border-radius: 999px; font-size: 13px; font-weight: 600; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 16px; margin-bottom: 28px; }
.kpi-card { background: var(--surface-container-lowest); border: 1px solid rgba(172, 179, 180, 0.15); border-radius: 14px; padding: 22px 20px; position: relative; overflow: hidden; }
.kpi-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; background: var(--kpi-color, #6366f1); border-radius:4px 0 0 4px; }
.kpi-value { font-size: 32px; font-weight: 800; color: var(--ink); }
.kpi-label { font-size: 12px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }
.kpi-sub { font-size: 11px; color: var(--ink-3); margin-top: 6px; }
.section-heading { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-3); margin: 0 0 16px; }
.module-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; margin-bottom: 32px; }
.module-tile { background: var(--surface-container-lowest); border: 1px solid rgba(172, 179, 180, 0.15); border-radius: 14px; padding: 20px 18px; cursor: pointer; text-decoration: none; color: inherit; display: block; transition: transform .15s, box-shadow .15s, border-color .15s; position: relative; }
.module-tile:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.25); border-color: var(--accent); }
.module-tile-icon { font-size: 30px; margin-bottom: 10px; line-height: 1; }
.module-tile-label { font-size: 14px; font-weight: 700; color: var(--ink); }
.module-tile-desc { font-size: 11px; color: var(--ink-3); margin-top: 3px; line-height: 1.4; }
.module-tile-badge { position: absolute; top: 12px; right: 12px; background: rgba(99,102,241,.15); color: var(--accent); font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; }
.bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media(max-width:768px) { .bottom-grid { grid-template-columns: 1fr; } .module-grid { grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); } }
.audit-item { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid rgba(172, 179, 180, 0.15); font-size: 13px; }
.audit-item:last-child { border-bottom: none; }
.audit-badge { padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; white-space: nowrap; }
.audit-CREATE { background: rgba(52,211,153,.15); color: #34d399; }
.audit-UPDATE { background: rgba(251,191,36,.15); color: #fbbf24; }
.audit-DELETE { background: rgba(239,68,68,.15); color: #ef4444; }
.chart-wrap { height: 180px; }
.quick-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.qa-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid rgba(172, 179, 180, 0.15); background: var(--surface-container-lowest); color: var(--ink); transition: background .15s, border-color .15s; }
.qa-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
</style>

<!-- Hero -->
<div class="sa-hero">
    <div>
        <div class="sa-hero-title">⚡ SuperAdmin Control Centre</div>
        <div class="sa-hero-sub">Full visibility across all school operations — <?= date('l, F j, Y') ?></div>
    </div>
    <div class="sa-hero-badge">School ERP v3.0</div>
</div>

<!-- KPI Strip -->
<div class="kpi-grid" style="margin-bottom:28px">
    <div class="kpi-card" style="--kpi-color:#6366f1">
        <div class="kpi-value"><?= number_format($totalStudents) ?></div>
        <div class="kpi-label">Active Students</div>
        <div class="kpi-sub"><?= $todayAttendance ?> present today</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#10b981">
        <div class="kpi-value">₹<?= number_format($monthRevenue, 0) ?></div>
        <div class="kpi-label">Revenue This Month</div>
        <div class="kpi-sub">₹<?= number_format($pendingFees, 0) ?> outstanding</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#f59e0b">
        <div class="kpi-value"><?= number_format($totalStaff) ?></div>
        <div class="kpi-label">Active Staff</div>
        <div class="kpi-sub"><?= $pendingLeave ?> leave requests pending</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#ef4444">
        <div class="kpi-value"><?= number_format($pendingComps) ?></div>
        <div class="kpi-label">Pending Complaints</div>
        <div class="kpi-sub"><a href="<?= BASE_URL ?>/communication.php" style="color:var(--accent)">View all →</a></div>
    </div>
    <div class="kpi-card" style="--kpi-color:#3b82f6">
        <div class="kpi-value"><?= number_format($totalParents) ?></div>
        <div class="kpi-label">Parent Accounts</div>
        <div class="kpi-sub">Linked to portal</div>
    </div>
</div>

<!-- Quick Actions -->
<div style="margin-bottom:24px">
    <div class="section-heading">Quick Actions</div>
    <div class="quick-actions">
        <a class="qa-btn" href="<?= BASE_URL ?>/students.php?action=add">👥 Add Student</a>
        <a class="qa-btn" href="<?= BASE_URL ?>/users.php?action=add">👤 Add User</a>
        <a class="qa-btn" href="<?= BASE_URL ?>/fee.php">💰 Collect Fee</a>
        <a class="qa-btn" href="<?= BASE_URL ?>/notices.php">📢 Post Notice</a>
        <a class="qa-btn" href="<?= BASE_URL ?>/messages.php">✉ Send Message</a>
        <a class="qa-btn" href="<?= BASE_URL ?>/attendance.php">📋 Mark Attendance</a>
    </div>
</div>

<!-- Module Launcher -->
<div class="section-heading">All Modules</div>
<div class="module-grid">
<?php foreach ($modules as $m): ?>
    <a class="module-tile" href="<?= BASE_URL ?>/<?= htmlspecialchars($m['key']) ?>.php">
        <div class="module-tile-icon"><?= $m['icon'] ?></div>
        <div class="module-tile-label"><?= htmlspecialchars($m['label']) ?></div>
        <div class="module-tile-desc"><?= htmlspecialchars($m['desc']) ?></div>
        <?php if (!empty($m['badge'])): ?>
        <div class="module-tile-badge"><?= htmlspecialchars($m['badge']) ?></div>
        <?php endif; ?>
    </a>
<?php endforeach; ?>
</div>

<!-- Bottom: Chart + Audit Log -->
<div class="bottom-grid">
    <div class="card" style="padding:20px">
        <div class="section-heading">Attendance — Last 7 Days</div>
        <div class="chart-wrap">
            <canvas id="saAttChart"></canvas>
        </div>
    </div>
    <div class="card" style="padding:20px">
        <div class="section-heading">Recent Audit Activity</div>
        <?php if (empty($recentAudit)): ?>
            <div style="color:var(--ink-3);font-size:13px">No audit records yet.</div>
        <?php else: ?>
            <?php foreach ($recentAudit as $log): ?>
            <div class="audit-item">
                <span class="audit-badge audit-<?= htmlspecialchars($log['action'] ?? 'UPDATE') ?>"><?= htmlspecialchars($log['action'] ?? '—') ?></span>
                <div>
                    <div style="font-weight:600"><?= htmlspecialchars($log['table_name'] ?? '') ?> #<?= (int)($log['record_id'] ?? 0) ?></div>
                    <div style="color:var(--ink-3);font-size:11px"><?= htmlspecialchars($log['created_at'] ?? '') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const saCtx = document.getElementById('saAttChart')?.getContext('2d');
if (saCtx) {
    new Chart(saCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartDates) ?>,
            datasets: [{ label: 'Present', data: <?= json_encode($chartData) ?>, backgroundColor: 'rgba(99,102,241,0.7)', borderRadius: 6, borderSkipped: false }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
}
</script>
