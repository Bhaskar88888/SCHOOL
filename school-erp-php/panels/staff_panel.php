<?php
/**
 * Staff Panel
 * Role: staff
 */
if (!role_matches(get_current_role(), ['staff'])) {
    header('Location: ' . BASE_URL . '/dashboard.php'); exit;
}
$me       = get_authenticated_user();
$myUserId = get_current_user_id();

// Today's staff attendance status
$staffAttToday = db_table_exists('staff_attendance')
    ? db_fetch("SELECT status FROM staff_attendance WHERE user_id=? AND date=CURDATE()", [$myUserId])
    : null;
$staffStatus   = $staffAttToday['status'] ?? 'not-marked';

// Leave balance
$leaveBalance = db_table_exists('leave_balances')
    ? db_fetch("SELECT * FROM leave_balances WHERE user_id=? AND year=YEAR(NOW())", [$myUserId])
    : null;

// My leave requests
$myLeaveRequests = db_table_exists('leave_applications')
    ? db_fetchAll("SELECT * FROM leave_applications WHERE applicant_id=? ORDER BY created_at DESC LIMIT 5", [$myUserId])
    : [];

// Pending leave count
$pendingLeaveCount = db_table_exists('leave_applications')
    ? db_count("SELECT COUNT(*) FROM leave_applications WHERE applicant_id=? AND status='pending'", [$myUserId])
    : 0;

// Unread messages
$unreadMessages = db_table_exists('thread_participants')
    ? db_count("SELECT COUNT(*) FROM thread_participants tp JOIN messages m ON m.thread_id=tp.thread_id WHERE tp.user_id=? AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at) AND m.sender_id != ?", [$myUserId, $myUserId])
    : 0;

// School notices
$notices = db_table_exists('notices')
    ? db_fetchAll("SELECT title, created_at FROM notices WHERE is_active=1 ORDER BY created_at DESC LIMIT 5")
    : [];

// Payslip / salary info (current month)
$salaryInfo = db_table_exists('payroll')
    ? db_fetch("SELECT net_salary, basic_salary, status FROM payroll WHERE user_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) LIMIT 1", [$myUserId])
    : null;

// Attendance stats (current month)
$attMonth = db_table_exists('staff_attendance')
    ? db_fetch("SELECT COUNT(*) AS total, SUM(status='present') AS present FROM staff_attendance WHERE user_id=? AND MONTH(date)=MONTH(NOW()) AND YEAR(date)=YEAR(NOW())", [$myUserId])
    : null;
$attTotal   = (int)($attMonth['total'] ?? 0);
$attPresent = (int)($attMonth['present'] ?? 0);
$attPct     = $attTotal > 0 ? round(($attPresent / $attTotal) * 100) : 0;

// Employee info
$empRow = db_fetch("SELECT employee_id, phone, department_id FROM users WHERE id=?", [$myUserId]);
?>
<style>
.sf-hero { background:linear-gradient(135deg,#7c3aed,#4f46e5); border-radius:16px; padding:24px 28px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; }
.sf-hero-name { font-size:21px; font-weight:800; color:#fff; }
.sf-hero-sub  { color:#c4b5fd; font-size:13px; margin-top:4px; }
.sf-status { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:999px; font-size:12px; font-weight:700; }
.sf-status.present  { background:rgba(16,185,129,.25); color:#34d399; }
.sf-status.absent   { background:rgba(239,68,68,.25); color:#f87171; }
.sf-status.late     { background:rgba(251,191,36,.25); color:#fbbf24; }
.sf-status.not-marked{ background:rgba(100,116,139,.25); color:#94a3b8; }
.sf-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:24px; }
.sf-kpi-c { background:var(--surface-container-lowest); border:1px solid rgba(172,179,180,.15); border-radius:12px; padding:18px; border-left:3px solid var(--c,#7c3aed); }
.sf-kpi-v { font-size:26px; font-weight:800; }
.sf-kpi-l { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.06em; margin-top:3px; }
.sf-cols { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media(max-width:640px){ .sf-cols{grid-template-columns:1fr;} }
.sf-sh { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-bottom:12px; }
.sf-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid rgba(172,179,180,.15); font-size:13px; }
.sf-row:last-child { border-bottom:none; }
.sf-notice { background:rgba(124,58,237,.08); border-left:3px solid #7c3aed; border-radius:0 8px 8px 0; padding:9px 12px; margin-bottom:8px; font-size:13px; }
.sf-quick { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:22px; }
.sf-quick a { padding:8px 15px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid rgba(172,179,180,.15); background:var(--surface-container-lowest); text-decoration:none; color:var(--ink); transition:background .15s; }
.sf-quick a:hover { background:#7c3aed; color:#fff; border-color:#7c3aed; }
.lv-pill { padding:2px 8px; border-radius:6px; font-size:10px; font-weight:700; }
.lv-pending  { background:rgba(251,191,36,.15); color:#fbbf24; }
.lv-approved { background:rgba(16,185,129,.15); color:#10b981; }
.lv-rejected { background:rgba(239,68,68,.15); color:#ef4444; }
</style>

<!-- Hero -->
<div class="sf-hero">
    <div>
        <div class="sf-hero-name">Hello, <?= htmlspecialchars(explode(' ',$me['name'])[0]) ?> 👋</div>
        <div class="sf-hero-sub">
            Staff Portal — <?= date('l, F j, Y') ?>
            <?php if (!empty($empRow['employee_id'])): ?>
             · EMP ID: <strong><?= htmlspecialchars($empRow['employee_id']) ?></strong>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php
        $statusClass = in_array($staffStatus, ['present','absent','late']) ? $staffStatus : 'not-marked';
        $statusLabel = ['present'=>'✅ Present','absent'=>'❌ Absent','late'=>'⏰ Late','not-marked'=>'⬜ Not Marked'][$statusClass];
        ?>
        <span class="sf-status <?= $statusClass ?>"><?= $statusLabel ?></span>
        <a href="<?= BASE_URL ?>/messages.php" style="background:rgba(124,58,237,.25);color:#c4b5fd;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none">
            ✉ Messages<?php if($unreadMessages>0): ?> <span style="background:#ef4444;color:#fff;border-radius:999px;padding:0 6px;font-size:10px"><?= $unreadMessages ?></span><?php endif; ?>
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="sf-kpi">
    <div class="sf-kpi-c" style="--c:#7c3aed">
        <div class="sf-kpi-v"><?= $attPct ?>%</div>
        <div class="sf-kpi-l">Attendance (Month)</div>
    </div>
    <div class="sf-kpi-c" style="--c:#10b981">
        <div class="sf-kpi-v" style="color:<?= $pendingLeaveCount > 0 ? '#f59e0b' : '#10b981' ?>"><?= $pendingLeaveCount ?></div>
        <div class="sf-kpi-l">Leave Pending</div>
    </div>
    <div class="sf-kpi-c" style="--c:#3b82f6">
        <div class="sf-kpi-v"><?= $unreadMessages ?></div>
        <div class="sf-kpi-l">Unread Messages</div>
    </div>
    <?php if ($salaryInfo): ?>
    <div class="sf-kpi-c" style="--c:#f59e0b">
        <div class="sf-kpi-v" style="color:<?= ($salaryInfo['status']??'pending')==='paid' ? '#10b981' : '#f59e0b' ?>">₹<?= number_format((float)($salaryInfo['net_salary']??0),0) ?></div>
        <div class="sf-kpi-l">Salary This Month</div>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="sf-quick">
    <a href="<?= BASE_URL ?>/leave.php" id="sf-apply-leave">⏰ Apply Leave</a>
    <a href="<?= BASE_URL ?>/messages.php" id="sf-messages">✉ Message Admin</a>
    <a href="<?= BASE_URL ?>/notices.php" id="sf-notices">📢 View Notices</a>
    <a href="<?= BASE_URL ?>/profile.php" id="sf-profile">👤 My Profile</a>
    <a href="<?= BASE_URL ?>/profile.php#change-password" id="sf-change-pass">🔐 Change Password</a>
    <?php if ($salaryInfo): ?>
    <a href="<?= BASE_URL ?>/payroll.php" id="sf-payslip">💳 View Payslip</a>
    <?php endif; ?>
</div>

<!-- Main Grid -->
<div class="sf-cols">
    <!-- Leave Requests -->
    <div class="card" style="padding:20px">
        <div class="sf-sh">My Leave Requests</div>
        <?php if (empty($myLeaveRequests)): ?>
            <div style="font-size:13px;color:var(--ink-3)">No leave requests on record.</div>
            <div style="margin-top:12px"><a href="<?= BASE_URL ?>/leave.php" style="font-size:12px;color:#7c3aed">Apply for Leave →</a></div>
        <?php else: foreach ($myLeaveRequests as $lr):
            $ls = $lr['status'] ?? 'pending';
            $lc = ['approved'=>'lv-approved','rejected'=>'lv-rejected']['pending'=>'lv-pending'][$ls] ?? 'lv-pending';
        ?>
        <div class="sf-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($lr['leave_type'] ?? 'Leave') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($lr['from_date'] ?? '') ?> → <?= htmlspecialchars($lr['to_date'] ?? '') ?></div>
                <?php if (!empty($lr['reason'])): ?>
                <div style="font-size:11px;color:var(--ink-3);margin-top:2px"><?= htmlspecialchars($lr['reason']) ?></div>
                <?php endif; ?>
            </div>
            <?php
            $lc2 = $ls==='approved' ? 'lv-approved' : ($ls==='rejected' ? 'lv-rejected' : 'lv-pending');
            ?>
            <span class="lv-pill <?= $lc2 ?>"><?= strtoupper($ls) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:12px"><a href="<?= BASE_URL ?>/leave.php" style="font-size:12px;color:#7c3aed">All leave requests →</a></div>
        <?php endif; ?>
    </div>

    <!-- Notices -->
    <div class="card" style="padding:20px">
        <div class="sf-sh">School Notices</div>
        <?php if (empty($notices)): ?>
            <div style="font-size:13px;color:var(--ink-3)">No recent notices.</div>
        <?php else: foreach ($notices as $n): ?>
        <div class="sf-notice">
            <div style="font-weight:600"><?= htmlspecialchars($n['title']) ?></div>
            <div style="font-size:11px;color:var(--ink-3);margin-top:2px"><?= htmlspecialchars($n['created_at']) ?></div>
        </div>
        <?php endforeach; endif; ?>
        <div style="margin-top:12px"><a href="<?= BASE_URL ?>/notices.php" style="font-size:12px;color:#7c3aed">View all notices →</a></div>
    </div>
</div>

<!-- Monthly Attendance Bar -->
<?php if ($attTotal > 0): ?>
<div class="card" style="padding:20px;margin-top:18px">
    <div class="sf-sh" style="margin-bottom:10px">This Month's Attendance</div>
    <div style="display:flex;align-items:center;gap:14px">
        <div style="flex:1;background:rgba(172,179,180,.15);border-radius:999px;height:10px;overflow:hidden">
            <div style="height:100%;width:<?= $attPct ?>%;background:<?= $attPct>=75?'#10b981':($attPct>=50?'#f59e0b':'#ef4444') ?>;border-radius:999px;transition:width .5s"></div>
        </div>
        <div style="font-size:13px;font-weight:700;white-space:nowrap"><?= $attPresent ?>/<?= $attTotal ?> days &nbsp;<span style="color:<?= $attPct>=75?'#10b981':($attPct>=50?'#f59e0b':'#ef4444') ?>">(<?= $attPct ?>%)</span></div>
    </div>
</div>
<?php endif; ?>

<?php if (file_exists(__DIR__ . '/../includes/widgets/inbox_widget.php')): ?>
<?php include __DIR__ . '/../includes/widgets/inbox_widget.php'; ?>
<?php endif; ?>
