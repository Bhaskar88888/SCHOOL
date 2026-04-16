<?php
/**
 * Accounts Panel
 */
$todayRevenue  = (float)(db_fetch("SELECT COALESCE(SUM(amount_paid),0) AS t FROM fees WHERE DATE(paid_date)=CURDATE()")['t'] ?? 0);
$monthRevenue  = (float)(db_fetch("SELECT COALESCE(SUM(amount_paid),0) AS t FROM fees WHERE YEAR(paid_date)=YEAR(NOW()) AND MONTH(paid_date)=MONTH(NOW())")['t'] ?? 0);
$totalDues     = (float)(db_fetch("SELECT COALESCE(SUM(balance_amount),0) AS t FROM fees WHERE balance_amount>0")['t'] ?? 0);
$totalReceipts = db_count("SELECT COUNT(*) FROM fees WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$recentReceipts = db_fetchAll("SELECT f.receipt_no, f.amount_paid, f.fee_type, f.paid_date, s.name AS student_name FROM fees f LEFT JOIN students s ON f.student_id=s.id ORDER BY f.created_at DESC LIMIT 8");
$topDues = db_fetchAll("SELECT s.name, s.admission_no, c.name AS class_name, COALESCE(SUM(f.balance_amount),0) AS total_due FROM fees f JOIN students s ON f.student_id=s.id LEFT JOIN classes c ON s.class_id=c.id WHERE f.balance_amount>0 GROUP BY s.id,s.name,s.admission_no,c.name ORDER BY total_due DESC LIMIT 8");
$payrollStatus = db_table_exists('payroll')
    ? db_fetch("SELECT COUNT(*) AS cnt, COALESCE(SUM(net_salary),0) AS total FROM payroll WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")
    : null;
?>
<style>
.acc-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:26px; }
.acc-kpi-c { background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:14px; padding:20px 18px; border-top:3px solid var(--c,#6366f1); }
.acc-kpi-v { font-size:26px; font-weight:800; }
.acc-kpi-l { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.06em; margin-top:4px; }
.acc-cols { display:grid; grid-template-columns:1.2fr 1fr; gap:20px; }
@media(max-width:640px){ .acc-cols{grid-template-columns:1fr;} }
.acc-sh { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-bottom:12px; }
.acc-row { display:flex; align-items:center; padding:9px 0; border-bottom:1px solid rgba(172, 179, 180, 0.15); font-size:13px; gap:10px; }
.acc-row:last-child { border-bottom:none; }
.qa { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:22px; }
.qa a { padding:8px 14px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid rgba(172, 179, 180, 0.15); background:var(--surface-container-lowest); text-decoration:none; color:var(--ink); transition:background .15s; }
.qa a:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
</style>

<div style="font-size:20px;font-weight:800;margin-bottom:20px">💰 Accounts Dashboard</div>

<div class="acc-kpi">
    <div class="acc-kpi-c" style="--c:#10b981">
        <div class="acc-kpi-v" style="color:#10b981">₹<?= number_format($todayRevenue,0) ?></div>
        <div class="acc-kpi-l">Collected Today</div>
    </div>
    <div class="acc-kpi-c" style="--c:#6366f1">
        <div class="acc-kpi-v">₹<?= number_format($monthRevenue,0) ?></div>
        <div class="acc-kpi-l">This Month</div>
    </div>
    <div class="acc-kpi-c" style="--c:#ef4444">
        <div class="acc-kpi-v" style="color:#ef4444">₹<?= number_format($totalDues,0) ?></div>
        <div class="acc-kpi-l">Total Outstanding</div>
    </div>
    <div class="acc-kpi-c" style="--c:#f59e0b">
        <div class="acc-kpi-v"><?= $totalReceipts ?></div>
        <div class="acc-kpi-l">Receipts This Month</div>
    </div>
    <?php if ($payrollStatus): ?>
    <div class="acc-kpi-c" style="--c:#3b82f6">
        <div class="acc-kpi-v">₹<?= number_format((float)$payrollStatus['total'],0) ?></div>
        <div class="acc-kpi-l">Payroll This Month</div>
    </div>
    <?php endif; ?>
</div>

<div class="qa">
    <a href="<?= BASE_URL ?>/fee.php">💰 Collect Fee</a>
    <a href="<?= BASE_URL ?>/payroll.php">💵 Payroll</a>
    <a href="<?= BASE_URL ?>/export.php">📊 Export Report</a>
</div>

<div class="acc-cols">
    <div class="card" style="padding:20px">
        <div class="acc-sh">Recent Receipts</div>
        <?php if (empty($recentReceipts)): ?>
            <div style="font-size:13px;color:var(--ink-3)">No receipts yet.</div>
        <?php else: foreach ($recentReceipts as $r): ?>
        <div class="acc-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($r['student_name'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($r['fee_type'] ?? '') ?> · <?= htmlspecialchars($r['receipt_no'] ?? '') ?></div>
            </div>
            <div style="font-weight:700;color:#10b981">₹<?= number_format((float)$r['amount_paid'],0) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card" style="padding:20px">
        <div class="acc-sh">Top Outstanding Dues</div>
        <?php if (empty($topDues)): ?>
            <div style="font-size:13px;color:#10b981;font-weight:600">✅ No pending dues!</div>
        <?php else: foreach ($topDues as $d): ?>
        <div class="acc-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($d['name']) ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($d['class_name'] ?? '-') ?></div>
            </div>
            <div style="font-weight:700;color:#ef4444">₹<?= number_format((float)$d['total_due'],0) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
