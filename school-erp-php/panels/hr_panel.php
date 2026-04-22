<?php
/**
 * HR Panel
 */
// Role guard — only hr (admin/superadmin bypass via role_matches)
if (!role_matches(get_current_role(), ['hr'])) {
    header('Location: ' . BASE_URL . '/dashboard.php'); exit;
}
$totalStaff    = db_count("SELECT COUNT(*) FROM users WHERE role NOT IN ('student','parent') AND is_active=1");
$pendingLeave  = db_table_exists('leave_applications') ? db_count("SELECT COUNT(*) FROM leave_applications WHERE status='pending'") : 0;
$approvedLeave = db_table_exists('leave_applications') ? db_count("SELECT COUNT(*) FROM leave_applications WHERE status='approved' AND MONTH(from_date)=MONTH(NOW()) AND YEAR(from_date)=YEAR(NOW())") : 0;
$staffToday    = db_table_exists('staff_attendance') ? db_count("SELECT COUNT(*) FROM staff_attendance WHERE date=CURDATE() AND status='present'") : 0;
$recentStaff   = db_fetchAll("SELECT id, name, role, employee_id, created_at FROM users WHERE role NOT IN ('student','parent') AND is_active=1 ORDER BY created_at DESC LIMIT 6");
$pendingLeaveList = db_table_exists('leave_applications')
    ? db_fetchAll("SELECT la.*, u.name AS user_name, u.role FROM leave_applications la LEFT JOIN users u ON la.applicant_id=u.id WHERE la.status='pending' ORDER BY la.created_at ASC LIMIT 8")
    : [];
?>
<style>
.hr-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
.hr-kpi-c { background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:12px; padding:16px; border-left:3px solid var(--c,#6366f1); }
.hr-kpi-v { font-size:24px; font-weight:800; }
.hr-kpi-l { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.06em; margin-top:3px; }
.hr-cols { display:grid; grid-template-columns:1.2fr 1fr; gap:18px; }
@media(max-width:640px){ .hr-cols{grid-template-columns:1fr;} }
.hr-sh { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-bottom:12px; }
.hr-row { display:flex; align-items:center; padding:9px 0; border-bottom:1px solid rgba(172, 179, 180, 0.15); font-size:13px; gap:10px; }
.hr-row:last-child { border-bottom:none; }
.role-chip { padding:2px 7px; border-radius:6px; background:rgba(99,102,241,.12); color:var(--accent); font-size:10px; font-weight:700; }
.qa { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.qa a { padding:8px 14px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid rgba(172, 179, 180, 0.15); background:var(--surface-container-lowest); text-decoration:none; color:var(--ink); transition:background .15s; }
.qa a:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
</style>

<div style="font-size:20px;font-weight:800;margin-bottom:20px">🏢 HR Dashboard</div>

<div class="hr-kpi">
    <div class="hr-kpi-c" style="--c:#6366f1">
        <div class="hr-kpi-v"><?= $totalStaff ?></div>
        <div class="hr-kpi-l">Active Staff</div>
    </div>
    <div class="hr-kpi-c" style="--c:#10b981">
        <div class="hr-kpi-v"><?= $staffToday ?></div>
        <div class="hr-kpi-l">Present Today</div>
    </div>
    <div class="hr-kpi-c" style="--c:<?= $pendingLeave > 0 ? '#f59e0b' : '#10b981' ?>">
        <div class="hr-kpi-v" style="color:<?= $pendingLeave > 0 ? '#f59e0b' : '#10b981' ?>"><?= $pendingLeave ?></div>
        <div class="hr-kpi-l">Leave Pending</div>
    </div>
    <div class="hr-kpi-c" style="--c:#3b82f6">
        <div class="hr-kpi-v"><?= $approvedLeave ?></div>
        <div class="hr-kpi-l">Approved This Month</div>
    </div>
</div>

<div class="qa">
    <a href="<?= BASE_URL ?>/users.php">👤 Manage Staff</a>
    <a href="<?= BASE_URL ?>/leave.php">⏰ Leave Requests</a>
    <a href="<?= BASE_URL ?>/staff-attendance.php">✅ Staff Attendance</a>
    <a href="<?= BASE_URL ?>/salary-setup.php">💳 Salary Setup</a>
    <a href="<?= BASE_URL ?>/payroll.php">💵 Payroll</a>
</div>

<div class="hr-cols">
    <div class="card" style="padding:20px">
        <div class="hr-sh">Pending Leave Requests</div>
        <?php if (empty($pendingLeaveList)): ?>
            <div style="font-size:13px;color:#10b981;font-weight:600">✅ No pending leave requests.</div>
        <?php else: foreach ($pendingLeaveList as $lr): ?>
        <div class="hr-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($lr['user_name'] ?? '-') ?> <span class="role-chip"><?= htmlspecialchars($lr['role'] ?? '') ?></span></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($lr['leave_type'] ?? 'Leave') ?> · <?= htmlspecialchars($lr['from_date'] ?? '') ?> → <?= htmlspecialchars($lr['to_date'] ?? '') ?></div>
            </div>
            <a href="<?= BASE_URL ?>/leave.php" style="font-size:11px;color:var(--accent);text-decoration:none;font-weight:600">Review →</a>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card" style="padding:20px">
        <div class="hr-sh">Recently Added Staff</div>
        <?php if (empty($recentStaff)): ?>
            <div style="font-size:13px;color:var(--ink-3)">No staff records found.</div>
        <?php else: foreach ($recentStaff as $s): ?>
        <div class="hr-row">
            <div style="width:34px;height:34px;border-radius:50%;background:rgba(99,102,241,.15);color:var(--accent);display:grid;place-items:center;font-weight:700;font-size:13px;flex-shrink:0"><?= strtoupper(substr($s['name'],0,1)) ?></div>
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($s['role'] ?? '') ?> · <?= htmlspecialchars($s['employee_id'] ?? 'No EMP ID') ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
