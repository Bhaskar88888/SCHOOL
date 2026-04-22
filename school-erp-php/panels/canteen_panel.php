<?php
/**
 * Canteen Panel
 */
// Role guard — only canteen (admin/superadmin bypass via role_matches)
if (!role_matches(get_current_role(), ['canteen'])) {
    header('Location: ' . BASE_URL . '/dashboard.php'); exit;
}
$todayRevenue = 0; $topItems = []; $lowStock = [];
if (db_table_exists('canteen_sales') || db_table_exists('canteen_orders')) {
    $t = db_table_exists('canteen_sales') ? 'canteen_sales' : 'canteen_orders';
    $amtCol = db_column_exists($t,'total_price') ? 'total_price' : 'total_amount';
    $dateCol = db_column_exists($t,'sale_date') ? 'sale_date' : (db_column_exists($t,'order_date') ? 'order_date' : 'created_at');
    $todayRevenue = (float)(db_fetch("SELECT COALESCE(SUM($amtCol),0) AS t FROM $t WHERE DATE($dateCol)=CURDATE()")['t'] ?? 0);
    $monthRevenue = (float)(db_fetch("SELECT COALESCE(SUM($amtCol),0) AS t FROM $t WHERE MONTH($dateCol)=MONTH(NOW()) AND YEAR($dateCol)=YEAR(NOW())")['t'] ?? 0);
    $todayOrders  = db_count("SELECT COUNT(*) FROM $t WHERE DATE($dateCol)=CURDATE()");
} else {
    $monthRevenue = 0; $todayOrders = 0;
}
$totalItems = db_table_exists('canteen_items') ? db_count("SELECT COUNT(*) FROM canteen_items WHERE is_available=1") : 0;
$lowStock   = db_table_exists('canteen_items') ? db_fetchAll("SELECT * FROM canteen_items WHERE available_qty < 10 AND is_available=1 ORDER BY available_qty ASC LIMIT 6") : [];
$recentSales = [];
if (db_table_exists('canteen_sales') || db_table_exists('canteen_orders')) {
    $t2 = db_table_exists('canteen_sales') ? 'canteen_sales' : 'canteen_orders';
    $amtC = db_column_exists($t2,'total_price') ? 'total_price' : 'total_amount';
    $dateC = db_column_exists($t2,'sale_date') ? 'sale_date' : (db_column_exists($t2,'order_date') ? 'order_date' : 'created_at');
    $recentSales = db_fetchAll("SELECT s.$amtC AS total, s.$dateC AS sale_dt, u.name AS buyer FROM $t2 s LEFT JOIN users u ON s.ordered_by=u.id ORDER BY s.$dateC DESC LIMIT 6");
}
?>
<style>
.can-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
.can-kpi-c { background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:12px; padding:16px; border-top:3px solid var(--c,#6366f1); }
.can-kpi-v { font-size:24px; font-weight:800; }
.can-kpi-l { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.06em; margin-top:3px; }
.can-cols { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media(max-width:640px){ .can-cols{grid-template-columns:1fr;} }
.can-sh { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-bottom:12px; }
.can-row { display:flex; align-items:center; padding:9px 0; border-bottom:1px solid rgba(172, 179, 180, 0.15); font-size:13px; gap:10px; }
.can-row:last-child { border-bottom:none; }
.low-badge { padding:2px 7px; border-radius:6px; background:rgba(239,68,68,.15); color:#ef4444; font-size:10px; font-weight:700; }
.qa { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.qa a { padding:8px 14px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid rgba(172, 179, 180, 0.15); background:var(--surface-container-lowest); text-decoration:none; color:var(--ink); transition:background .15s; }
.qa a:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
</style>

<div style="font-size:20px;font-weight:800;margin-bottom:20px">🍽 Canteen Dashboard</div>

<div class="can-kpi">
    <div class="can-kpi-c" style="--c:#10b981">
        <div class="can-kpi-v" style="color:#10b981">₹<?= number_format($todayRevenue,0) ?></div>
        <div class="can-kpi-l">Revenue Today</div>
    </div>
    <div class="can-kpi-c" style="--c:#6366f1">
        <div class="can-kpi-v">₹<?= number_format($monthRevenue,0) ?></div>
        <div class="can-kpi-l">This Month</div>
    </div>
    <div class="can-kpi-c" style="--c:#f59e0b">
        <div class="can-kpi-v"><?= $todayOrders ?></div>
        <div class="can-kpi-l">Orders Today</div>
    </div>
    <div class="can-kpi-c" style="--c:#3b82f6">
        <div class="can-kpi-v"><?= $totalItems ?></div>
        <div class="can-kpi-l">Active Items</div>
    </div>
</div>

<div class="qa">
    <a href="<?= BASE_URL ?>/canteen.php">🍔 Manage Menu</a>
</div>

<div class="can-cols">
    <div class="card" style="padding:20px">
        <div class="can-sh">Recent Sales</div>
        <?php if (empty($recentSales)): ?>
            <div style="font-size:13px;color:var(--ink-3)">No sales recorded today.</div>
        <?php else: foreach ($recentSales as $s): ?>
        <div class="can-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($s['buyer'] ?? 'Walk-in') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars(substr($s['sale_dt'] ?? '',0,16)) ?></div>
            </div>
            <div style="font-weight:700;color:#10b981">₹<?= number_format((float)$s['total'],0) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card" style="padding:20px">
        <div class="can-sh">⚠ Low Stock Items</div>
        <?php if (empty($lowStock)): ?>
            <div style="font-size:13px;color:#10b981;font-weight:600">✅ All items well-stocked!</div>
        <?php else: foreach ($lowStock as $item): ?>
        <div class="can-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($item['name']) ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($item['category'] ?? '') ?></div>
            </div>
            <span class="low-badge"><?= (int)$item['available_qty'] ?> left</span>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
