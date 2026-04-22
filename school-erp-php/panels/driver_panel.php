<?php
/**
 * Driver Panel — Dashboard
 * Shows assigned vehicle, route, and daily notices.
 */
// Role guard
if (!role_matches(get_current_role(), ['driver'])) {
    header('Location: ' . BASE_URL . '/dashboard.php'); exit;
}
$me       = get_authenticated_user();
$myUserId = get_current_user_id();

// Find route assigned to this driver
$myRoute = db_table_exists('bus_routes')
    ? db_fetch("SELECT br.*, tv.registration_no, tv.model, tv.capacity
                FROM bus_routes br
                LEFT JOIN transport_vehicles tv ON br.vehicle_id = tv.id
                WHERE br.driver_id = ? AND br.is_active = 1
                LIMIT 1", [$myUserId])
    : null;

$routeId = (int)($myRoute['id'] ?? 0);

// Number of students on the route
$studentCount = $routeId
    ? db_count("SELECT COUNT(*) FROM transport_allocations WHERE route_id = ? AND is_active = 1", [$routeId])
    : 0;

// Recent Notices
$notices = db_table_exists('notices')
    ? db_fetchAll("SELECT title, created_at FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5")
    : [];

// Bus stops on this route
$stops = ($routeId && db_table_exists('bus_stops'))
    ? db_fetchAll("SELECT stop_name, stop_order, pickup_time, drop_time
                   FROM bus_stops WHERE route_id = ? ORDER BY stop_order ASC", [$routeId])
    : [];
?>
<style>
.drv-hero { background: linear-gradient(135deg, var(--accent), var(--accent-hover)); border-radius: 14px; padding: 24px 28px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.drv-hero-name { font-size: 20px; font-weight: 800; color: #fff; }
.drv-hero-sub  { color: #94a3b8; font-size: 13px; margin-top: 4px; }
.drv-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 24px; }
.drv-kpi-c { background: var(--surface-container-lowest); border: 1px solid rgba(172,179,180,.15); border-radius: 12px; padding: 18px; border-left: 3px solid var(--c, #6366f1); }
.drv-kpi-v { font-size: 26px; font-weight: 800; }
.drv-kpi-l { font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em; margin-top: 3px; }
.drv-cols  { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media(max-width:640px) { .drv-cols { grid-template-columns: 1fr; } }
.drv-sh  { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-3); margin-bottom: 12px; }
.drv-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid rgba(172,179,180,.15); font-size: 13px; }
.drv-row:last-child { border-bottom: none; }
.vehicle-card { background: var(--surface-container-lowest); border: 1px solid rgba(172,179,180,.15); border-radius: 14px; padding: 20px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 18px; }
.vehicle-icon { width: 56px; height: 56px; background: rgba(99,102,241,.15); color: var(--accent); border-radius: 14px; display: grid; place-items: center; font-size: 26px; flex-shrink: 0; }
.notice-item { background: rgba(99,102,241,.06); border-left: 3px solid var(--accent); border-radius: 0 8px 8px 0; padding: 10px 12px; margin-bottom: 8px; font-size: 13px; }
.stop-badge { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: var(--accent); color: #fff; border-radius: 50%; font-size: 10px; font-weight: 700; flex-shrink: 0; }
</style>

<!-- Hero -->
<div class="drv-hero">
    <div>
        <div class="drv-hero-name">Hello, <?= htmlspecialchars(explode(' ', $me['name'])[0]) ?> 👋</div>
        <div class="drv-hero-sub">Driver — <?= date('l, F j, Y') ?></div>
    </div>
    <a href="<?= BASE_URL ?>/transport.php" style="background:rgba(99,102,241,.2);color:#a5b4fc;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none">🚌 Transport Module</a>
</div>

<!-- Vehicle / Route Card -->
<?php if ($myRoute): ?>
<div class="vehicle-card">
    <div class="vehicle-icon">🚌</div>
    <div style="flex:1">
        <div style="font-size:18px;font-weight:800"><?= htmlspecialchars($myRoute['registration_no'] ?? 'My Vehicle') ?></div>
        <?php if (!empty($myRoute['model'])): ?>
        <div style="font-size:13px;color:var(--ink-3);margin-top:2px"><?= htmlspecialchars($myRoute['model']) ?></div>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--ink-3);margin-top:4px">
            Route: <strong><?= htmlspecialchars($myRoute['route_name'] ?? 'N/A') ?></strong>
            <?php if (!empty($myRoute['start_point']) && !empty($myRoute['end_point'])): ?>
             · <?= htmlspecialchars($myRoute['start_point']) ?> → <?= htmlspecialchars($myRoute['end_point']) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($myRoute['capacity'])): ?>
    <div style="text-align:right">
        <div style="font-size:22px;font-weight:800"><?= (int)$myRoute['capacity'] ?></div>
        <div style="font-size:10px;color:var(--ink-3);text-transform:uppercase">Capacity</div>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card" style="padding:30px;text-align:center;margin-bottom:20px">
    <div style="font-size:36px;margin-bottom:10px">🚌</div>
    <div style="font-weight:700;font-size:15px">No vehicle assigned yet</div>
    <div style="color:var(--ink-3);font-size:13px;margin-top:4px">Contact the Transport Admin to get your vehicle assigned.</div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="drv-kpi">
    <div class="drv-kpi-c" style="--c:#6366f1">
        <div class="drv-kpi-v"><?= $studentCount ?></div>
        <div class="drv-kpi-l">Students on Board</div>
    </div>
    <div class="drv-kpi-c" style="--c:#10b981">
        <div class="drv-kpi-v"><?= count($stops) ?></div>
        <div class="drv-kpi-l">Route Stops</div>
    </div>
    <div class="drv-kpi-c" style="--c:#f59e0b">
        <div class="drv-kpi-v"><?= date('H:i') ?></div>
        <div class="drv-kpi-l">Current Time</div>
    </div>
</div>

<!-- Stops + Notices -->
<div class="drv-cols">
    <div>
        <div class="drv-sh">Route Stops</div>
        <?php if (empty($stops)): ?>
            <div style="color:var(--ink-3);font-size:13px">No stops defined for this route.</div>
        <?php else: foreach ($stops as $idx => $stop): ?>
        <div class="drv-row">
            <span class="stop-badge"><?= ($idx + 1) ?></span>
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($stop['stop_name']) ?></div>
                <?php if (!empty($stop['pickup_time'])): ?>
                <div style="font-size:11px;color:var(--ink-3)">
                    ↑ <?= htmlspecialchars($stop['pickup_time']) ?>
                    <?php if (!empty($stop['drop_time'])): ?> · ↓ <?= htmlspecialchars($stop['drop_time']) ?><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div>
        <div class="drv-sh">School Notices</div>
        <?php if (empty($notices)): ?>
            <div style="color:var(--ink-3);font-size:13px">No recent notices.</div>
        <?php else: foreach ($notices as $n): ?>
        <div class="notice-item">
            <div style="font-weight:600"><?= htmlspecialchars($n['title']) ?></div>
            <div style="font-size:11px;color:var(--ink-3);margin-top:2px"><?= htmlspecialchars($n['created_at']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
