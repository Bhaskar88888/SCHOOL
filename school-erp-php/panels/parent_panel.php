<?php
/**
 * Parent Panel
 */
$me = get_authenticated_user();
$myUserId = get_current_user_id();

// Children
function parent_get_children($userId) {
    $children = db_fetchAll(
        "SELECT s.*, c.name AS class_name, COALESCE(c.section,'') AS section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.is_active=1 AND s.parent_user_id=? ORDER BY s.name",
        [$userId]
    );
    if (!empty($children)) return $children;
    // fallback: match by phone
    if (db_column_exists('students','parent_phone')) {
        $user = db_fetch("SELECT phone FROM users WHERE id=?", [$userId]);
        if (!empty($user['phone'])) {
            return db_fetchAll(
                "SELECT s.*, c.name AS class_name, COALESCE(c.section,'') AS section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.is_active=1 AND s.parent_phone=?",
                [$user['phone']]
            );
        }
    }
    return [];
}

$children = parent_get_children($myUserId);

// Enrich each child
foreach ($children as &$child) {
    $sid = (int)$child['id'];
    $cid = (int)$child['class_id'];
    $att = db_fetch("SELECT COUNT(*) AS total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) AS present FROM attendance WHERE student_id=?", [$sid]);
    $total = (int)($att['total']??0);
    $present = (int)($att['present']??0);
    $child['att_pct'] = $total > 0 ? round(($present/$total)*100) : 0;
    $child['pending_fee'] = (float)(db_fetch("SELECT COALESCE(SUM(balance_amount),0) AS t FROM fees WHERE student_id=? AND balance_amount>0", [$sid])['t'] ?? 0);
    $child['homework'] = ($cid && db_table_exists('homework'))
        ? db_fetchAll("SELECT * FROM homework WHERE class_id=? AND (due_date >= CURDATE() OR due_date IS NULL) ORDER BY due_date ASC LIMIT 3", [$cid])
        : [];
    $child['results'] = ($sid && db_table_exists('exam_results') && db_table_exists('exams'))
        ? db_fetchAll("SELECT er.marks_obtained, er.total_marks, er.grade, e.name AS exam_name, e.exam_date FROM exam_results er LEFT JOIN exams e ON er.exam_id=e.id WHERE er.student_id=? ORDER BY e.exam_date DESC LIMIT 3", [$sid])
        : [];
}
unset($child);

// Messages
$unreadMessages = db_table_exists('thread_participants')
    ? db_count("SELECT COUNT(*) FROM thread_participants tp JOIN messages m ON m.thread_id=tp.thread_id WHERE tp.user_id=? AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at) AND m.sender_id != ?", [$myUserId, $myUserId])
    : 0;

// My complaints
$myComplaints = db_table_exists('complaints')
    ? db_fetchAll("SELECT title, status, created_at FROM complaints WHERE submitted_by=? ORDER BY created_at DESC LIMIT 4", [$myUserId])
    : [];

// School Notices
$notices = db_table_exists('notices')
    ? db_fetchAll("SELECT title, created_at FROM notices WHERE is_active=1 ORDER BY created_at DESC LIMIT 4")
    : [];
?>
<style>
.p-hero { background:linear-gradient(135deg,#1e293b,#0f172a); border-radius:14px; padding:22px 26px; margin-bottom:22px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; }
.p-hero-name { font-size:19px; font-weight:800; color:#fff; }
.p-hero-sub { color:#94a3b8; font-size:13px; margin-top:3px; }
.p-actions { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:22px; }
.p-actions a { padding:8px 14px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid var(--border); background:var(--bg-card); text-decoration:none; color:var(--text-primary); transition:background .15s; }
.p-actions a:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
.child-card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:20px 22px; margin-bottom:18px; }
.child-header { display:flex; align-items:center; gap:14px; margin-bottom:16px; }
.child-avatar { width:48px; height:48px; border-radius:50%; background:rgba(99,102,241,.2); color:var(--accent); display:grid; place-items:center; font-size:20px; font-weight:800; flex-shrink:0; }
.child-name { font-size:16px; font-weight:800; }
.child-meta { font-size:12px; color:var(--text-muted); margin-top:2px; }
.child-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px; }
.child-kpi { background:var(--bg-secondary,rgba(255,255,255,.04)); border-radius:10px; padding:12px; text-align:center; }
.child-kpi-v { font-size:20px; font-weight:800; }
.child-kpi-l { font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; margin-top:2px; }
.child-mini-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media(max-width:480px){ .child-mini-grid{grid-template-columns:1fr;} .child-kpis{grid-template-columns:1fr 1fr;} }
.mini-sh { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:8px; }
.mini-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid var(--border); font-size:12px; }
.mini-row:last-child { border-bottom:none; }
.status-pill { padding:1px 7px; border-radius:6px; font-size:10px; font-weight:700; }
.stat-pend { background:rgba(251,191,36,.15); color:#fbbf24; }
.stat-reso { background:rgba(52,211,153,.15); color:#34d399; }
.stat-rej  { background:rgba(239,68,68,.15); color:#ef4444; }
.notice-item { background:rgba(99,102,241,.06); border-left:3px solid var(--accent); border-radius:0 8px 8px 0; padding:9px 12px; margin-bottom:8px; font-size:13px; }
.p-bottom { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:22px; }
@media(max-width:640px){ .p-bottom{grid-template-columns:1fr;} }
</style>

<!-- Hero -->
<div class="p-hero">
    <div>
        <div class="p-hero-name">Hello, <?= htmlspecialchars(explode(' ',$me['name'])[0]) ?> 👋</div>
        <div class="p-hero-sub">Parent Portal — <?= date('l, F j, Y') ?></div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <a href="<?= BASE_URL ?>/messages.php" style="background:rgba(99,102,241,.2);color:#a5b4fc;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none">
            ✉ Messages<?php if ($unreadMessages > 0): ?> <span style="background:#ef4444;color:#fff;border-radius:999px;padding:0 6px;font-size:10px"><?= $unreadMessages ?></span><?php endif; ?>
        </a>
    </div>
</div>

<!-- Quick Actions -->
<div class="p-actions">
    <a href="<?= BASE_URL ?>/communication.php">💬 File Complaint</a>
    <a href="<?= BASE_URL ?>/messages.php">✉ Message Teacher</a>
    <a href="<?= BASE_URL ?>/notices.php">📢 View Notices</a>
    <a href="<?= BASE_URL ?>/canteen.php">🍽 Canteen Menu</a>
</div>

<?php if (empty($children)): ?>
<div class="card" style="padding:36px;text-align:center">
    <div style="font-size:40px;margin-bottom:12px">👶</div>
    <div style="font-size:16px;font-weight:700;margin-bottom:6px">No children linked yet</div>
    <div style="color:var(--text-muted);font-size:13px">Please ask the school admin to link your child's record to this parent account.</div>
</div>
<?php else: ?>

<!-- One card per child -->
<?php foreach ($children as $child):
    $attColor = $child['att_pct'] >= 75 ? '#10b981' : ($child['att_pct'] >= 50 ? '#f59e0b' : '#ef4444');
?>
<div class="child-card">
    <div class="child-header">
        <div class="child-avatar"><?= strtoupper(substr($child['name'],0,1)) ?></div>
        <div>
            <div class="child-name"><?= htmlspecialchars($child['name']) ?></div>
            <div class="child-meta"><?= htmlspecialchars($child['class_name'] ?? '-') ?> <?= !empty($child['section']) ? '— Sec. '.htmlspecialchars($child['section']) : '' ?> · Admission #<?= htmlspecialchars($child['admission_no'] ?? 'N/A') ?></div>
        </div>
    </div>

    <div class="child-kpis">
        <div class="child-kpi">
            <div class="child-kpi-v" style="color:<?= $attColor ?>"><?= $child['att_pct'] ?>%</div>
            <div class="child-kpi-l">Attendance</div>
        </div>
        <div class="child-kpi">
            <div class="child-kpi-v" style="color:<?= $child['pending_fee'] > 0 ? '#ef4444' : '#10b981' ?>">₹<?= number_format($child['pending_fee'],0) ?></div>
            <div class="child-kpi-l">Pending Fee</div>
        </div>
        <div class="child-kpi">
            <div class="child-kpi-v"><?= count($child['homework']) ?></div>
            <div class="child-kpi-l">Homework Due</div>
        </div>
    </div>

    <div class="child-mini-grid">
        <div>
            <div class="mini-sh">Due Homework</div>
            <?php if (empty($child['homework'])): ?>
                <div style="font-size:12px;color:var(--text-muted)">All clear 🎉</div>
            <?php else: foreach ($child['homework'] as $hw): ?>
            <div class="mini-row">
                <span style="font-weight:600"><?= htmlspecialchars($hw['title'] ?? '-') ?></span>
                <span style="color:var(--text-muted)"><?= htmlspecialchars($hw['due_date'] ?? '') ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <div class="mini-sh">Recent Results</div>
            <?php if (empty($child['results'])): ?>
                <div style="font-size:12px;color:var(--text-muted)">No results yet.</div>
            <?php else: foreach ($child['results'] as $r): ?>
            <div class="mini-row">
                <span><?= htmlspecialchars($r['exam_name'] ?? '-') ?></span>
                <span style="font-weight:700;color:var(--accent)"><?= htmlspecialchars($r['grade'] ?? '') ?> (<?= htmlspecialchars($r['marks_obtained'] ?? '') ?>)</span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Bottom: Complaints + Notices -->
<div class="p-bottom">
    <div class="card" style="padding:18px">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:12px">My Complaints</div>
        <?php if (empty($myComplaints)): ?>
            <div style="font-size:13px;color:var(--text-muted)">No complaints filed.</div>
        <?php else: foreach ($myComplaints as $c):
            $s = $c['status'] ?? 'pending';
            $sc = $s==='resolved'?'stat-reso':($s==='rejected'?'stat-rej':'stat-pend');
        ?>
        <div class="mini-row">
            <span style="font-weight:600"><?= htmlspecialchars($c['title'] ?? '') ?></span>
            <span class="status-pill <?= $sc ?>"><?= strtoupper($s) ?></span>
        </div>
        <?php endforeach; endif; ?>
        <div style="margin-top:12px"><a href="<?= BASE_URL ?>/communication.php" style="font-size:12px;color:var(--accent)">View all complaints →</a></div>
    </div>
    <div class="card" style="padding:18px">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:12px">School Notices</div>
        <?php if (empty($notices)): ?>
            <div style="font-size:13px;color:var(--text-muted)">No recent notices.</div>
        <?php else: foreach ($notices as $n): ?>
        <div class="notice-item">
            <div style="font-weight:600"><?= htmlspecialchars($n['title']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($n['created_at']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/widgets/inbox_widget.php'; ?>
