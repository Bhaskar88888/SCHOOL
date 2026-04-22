<?php
/**
 * Librarian Panel
 */
// Role guard
if (!role_matches(get_current_role(), ['librarian'])) {
    header('Location: ' . BASE_URL . '/dashboard.php'); exit;
}
$totalBooks    = db_table_exists('library_books')  ? db_count("SELECT COUNT(*) FROM library_books") : 0;
$totalIssued   = db_table_exists('library_issues') ? db_count("SELECT COUNT(*) FROM library_issues WHERE return_date IS NULL") : 0;
$overdueCount  = db_table_exists('library_issues') ? db_count("SELECT COUNT(*) FROM library_issues WHERE return_date IS NULL AND due_date < CURDATE()") : 0;
$issuedToday   = db_table_exists('library_issues') ? db_count("SELECT COUNT(*) FROM library_issues WHERE DATE(issue_date)=CURDATE()") : 0;
$overdueList   = db_table_exists('library_issues') && db_table_exists('library_books')
    ? db_fetchAll("SELECT li.*, lb.title AS book_title, s.name AS student_name, DATEDIFF(CURDATE(), li.due_date) AS days_overdue FROM library_issues li LEFT JOIN library_books lb ON li.book_id=lb.id LEFT JOIN students s ON li.student_id=s.id WHERE li.return_date IS NULL AND li.due_date < CURDATE() ORDER BY days_overdue DESC LIMIT 8")
    : [];
$recentIssues  = db_table_exists('library_issues') && db_table_exists('library_books')
    ? db_fetchAll("SELECT li.*, lb.title AS book_title, s.name AS student_name FROM library_issues li LEFT JOIN library_books lb ON li.book_id=lb.id LEFT JOIN students s ON li.student_id=s.id ORDER BY li.issue_date DESC LIMIT 8")
    : [];
?>
<style>
.lib-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
.lib-kpi-c { background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:12px; padding:16px; border-left:3px solid var(--c,#6366f1); }
.lib-kpi-v { font-size:24px; font-weight:800; }
.lib-kpi-l { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.06em; margin-top:3px; }
.lib-cols { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media(max-width:640px){ .lib-cols{grid-template-columns:1fr;} }
.lib-sh { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-bottom:12px; }
.lib-row { display:flex; align-items:flex-start; padding:9px 0; border-bottom:1px solid rgba(172, 179, 180, 0.15); font-size:13px; gap:10px; }
.lib-row:last-child { border-bottom:none; }
.overdue-badge { padding:2px 7px; border-radius:6px; background:rgba(239,68,68,.15); color:#ef4444; font-size:10px; font-weight:700; white-space:nowrap; }
.qa { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.qa a { padding:8px 14px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid rgba(172, 179, 180, 0.15); background:var(--surface-container-lowest); text-decoration:none; color:var(--ink); transition:background .15s; }
.qa a:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
</style>

<div style="font-size:20px;font-weight:800;margin-bottom:20px">📚 Library Management</div>

<div class="lib-kpi">
    <div class="lib-kpi-c" style="--c:#6366f1">
        <div class="lib-kpi-v"><?= $totalBooks ?></div>
        <div class="lib-kpi-l">Total Books</div>
    </div>
    <div class="lib-kpi-c" style="--c:#f59e0b">
        <div class="lib-kpi-v"><?= $totalIssued ?></div>
        <div class="lib-kpi-l">Currently Issued</div>
    </div>
    <div class="lib-kpi-c" style="--c:#ef4444">
        <div class="lib-kpi-v" style="color:<?= $overdueCount > 0 ? '#ef4444' : 'inherit' ?>"><?= $overdueCount ?></div>
        <div class="lib-kpi-l">Overdue Returns</div>
    </div>
    <div class="lib-kpi-c" style="--c:#10b981">
        <div class="lib-kpi-v"><?= $issuedToday ?></div>
        <div class="lib-kpi-l">Issued Today</div>
    </div>
</div>

<div class="qa">
    <a href="<?= BASE_URL ?>/library.php">📚 Open Library</a>
</div>

<div class="lib-cols">
    <div class="card" style="padding:20px">
        <div class="lib-sh">⚠ Overdue Returns</div>
        <?php if (empty($overdueList)): ?>
            <div style="font-size:13px;color:#10b981;font-weight:600">✅ No overdue books!</div>
        <?php else: foreach ($overdueList as $o): ?>
        <div class="lib-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($o['student_name'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($o['book_title'] ?? 'Unknown') ?></div>
            </div>
            <span class="overdue-badge"><?= (int)$o['days_overdue'] ?>d late</span>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card" style="padding:20px">
        <div class="lib-sh">Recent Issues</div>
        <?php if (empty($recentIssues)): ?>
            <div style="font-size:13px;color:var(--ink-3)">No recent issues.</div>
        <?php else: foreach ($recentIssues as $i): ?>
        <div class="lib-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($i['book_title'] ?? 'Unknown') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($i['student_name'] ?? '-') ?> · <?= htmlspecialchars($i['issue_date'] ?? '') ?></div>
            </div>
            <?php if ($i['return_date']): ?>
            <span style="font-size:10px;color:#10b981;font-weight:700">RETURNED</span>
            <?php else: ?>
            <span style="font-size:10px;color:#f59e0b;font-weight:700">OUT</span>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
