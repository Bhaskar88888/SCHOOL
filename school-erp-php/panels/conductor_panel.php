<?php
/**
 * Conductor Panel — Dashboard
 * Shows route info, transport attendance, and daily student manifest.
 */
$me       = get_authenticated_user();
$myUserId = get_current_user_id();

// Find route assigned to this conductor
$myRoute = db_table_exists('bus_routes')
    ? db_fetch("SELECT br.*, tv.registration_no, tv.model
                FROM bus_routes br
                LEFT JOIN transport_vehicles tv ON br.vehicle_id = tv.id
                WHERE br.conductor_id = ? AND br.is_active = 1
                LIMIT 1", [$myUserId])
    : null;

$routeId = (int)($myRoute['id'] ?? 0);

// Students on this route
$studentCount = $routeId
    ? db_count("SELECT COUNT(*) FROM transport_allocations WHERE route_id = ? AND is_active = 1", [$routeId])
    : 0;

// Today's transport attendance (if table exists)
$presentToday = ($routeId && db_table_exists('transport_attendance'))
    ? db_count("SELECT COUNT(*) FROM transport_attendance WHERE route_id = ? AND date = CURDATE() AND status = 'present'", [$routeId])
    : 0;

// Student manifest for this route
$studentManifest = ($routeId && db_table_exists('transport_allocations'))
    ? db_fetchAll("SELECT s.name, s.phone, ta.pickup_stop, ta.drop_stop
                   FROM transport_allocations ta
                   JOIN students s ON ta.student_id = s.id
                   WHERE ta.route_id = ? AND ta.is_active = 1
                   ORDER BY s.name
                   LIMIT 20", [$routeId])
    : [];

// Recent Notices
$notices = db_table_exists('notices')
    ? db_fetchAll("SELECT title, created_at FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 4")
    : [];
?>
<style>
.con-hero { background: linear-gradient(135deg, var(--accent), var(--accent-hover)); border-radius: 14px; padding: 24px 28px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.con-hero-name { font-size: 20px; font-weight: 800; color: #fff; }
.con-hero-sub  { color: #94a3b8; font-size: 13px; margin-top: 4px; }
.con-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 24px; }
.con-kpi-c { background: var(--surface-container-lowest); border: 1px solid rgba(172,179,180,.15); border-radius: 12px; padding: 18px; border-left: 3px solid var(--c, #6366f1); }
.con-kpi-v { font-size: 26px; font-weight: 800; }
.con-kpi-l { font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em; margin-top: 3px; }
.con-cols  { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media(max-width:640px) { .con-cols { grid-template-columns: 1fr; } }
.con-sh  { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-3); margin-bottom: 12px; }
.con-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(172,179,180,.15); font-size: 13px; }
.con-row:last-child { border-bottom: none; }
.route-card { background: var(--surface-container-lowest); border: 1px solid rgba(172,179,180,.15); border-radius: 14px; padding: 18px 22px; margin-bottom: 20px; display: flex; align-items: center; gap: 18px; }
.route-icon { width: 52px; height: 52px; background: rgba(99,102,241,.15); color: var(--accent); border-radius: 12px; display: grid; place-items: center; font-size: 24px; flex-shrink: 0; }
.notice-item { background: rgba(99,102,241,.06); border-left: 3px solid var(--accent); border-radius: 0 8px 8px 0; padding: 10px 12px; margin-bottom: 8px; font-size: 13px; }
</style>

<!-- Hero -->
<div class="con-hero">
    <div>
        <div class="con-hero-name">Hello, <?= htmlspecialchars(explode(' ', $me['name'])[0]) ?> 👋</div>
        <div class="con-hero-sub">Conductor — <?= date('l, F j, Y') ?></div>
    </div>
    <a href="<?= BASE_URL ?>/transport.php" style="background:rgba(99,102,241,.2);color:#a5b4fc;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none">🚌 Transport Module</a>
</div>

<!-- Route Card -->
<?php if ($myRoute): ?>
<div class="route-card">
    <div class="route-icon">🚌</div>
    <div>
        <div style="font-size:17px;font-weight:800"><?= htmlspecialchars($myRoute['route_name'] ?? 'My Route') ?></div>
        <div style="font-size:12px;color:var(--ink-3);margin-top:4px">
            Vehicle: <?= htmlspecialchars($myRoute['registration_no'] ?? 'N/A') ?>
            <?php if (!empty($myRoute['model'])): ?> · <?= htmlspecialchars($myRoute['model']) ?><?php endif; ?>
        </div>
        <?php if (!empty($myRoute['start_point']) && !empty($myRoute['end_point'])): ?>
        <div style="font-size:12px;color:var(--ink-3);margin-top:2px">📍 <?= htmlspecialchars($myRoute['start_point']) ?> → <?= htmlspecialchars($myRoute['end_point']) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding:30px;text-align:center;margin-bottom:20px">
    <div style="font-size:36px;margin-bottom:10px">🚌</div>
    <div style="font-weight:700;font-size:15px">No route assigned yet</div>
    <div style="color:var(--ink-3);font-size:13px;margin-top:4px">Contact the Transport Admin to get your route assigned.</div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="con-kpi">
    <div class="con-kpi-c" style="--c:#6366f1">
        <div class="con-kpi-v"><?= $studentCount ?></div>
        <div class="con-kpi-l">Students on Route</div>
    </div>
    <div class="con-kpi-c" style="--c:#10b981">
        <div class="con-kpi-v"><?= $presentToday ?></div>
        <div class="con-kpi-l">Present Today</div>
    </div>
    <div class="con-kpi-c" style="--c:#f59e0b">
        <div class="con-kpi-v"><?= max(0, $studentCount - $presentToday) ?></div>
        <div class="con-kpi-l">Absent Today</div>
    </div>
</div>

<!-- Student Manifest + Notices -->
<div class="con-cols">
    <div>
        <div class="con-sh">Student Manifest</div>
        <?php if (empty($studentManifest)): ?>
            <div style="color:var(--ink-3);font-size:13px">No students on this route yet.</div>
        <?php else: foreach ($studentManifest as $s): ?>
        <div class="con-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($s['pickup_stop'] ?? 'N/A') ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div>
        <div class="con-sh">School Notices</div>
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
