<?php
/**
 * Student Panel
 */
$me = get_authenticated_user();
$myUserId = get_current_user_id();

// Find student record linked to this user
$myStudent = db_fetch("SELECT s.*, c.name AS class_name, COALESCE(c.section,'') AS section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.user_id=? AND s.is_active=1 LIMIT 1", [$myUserId]);
$studentId = (int)($myStudent['id'] ?? 0);
$classId   = (int)($myStudent['class_id'] ?? 0);

// Attendance
$attTotal   = $studentId ? db_count("SELECT COUNT(*) FROM attendance WHERE student_id=?", [$studentId]) : 0;
$attPresent = $studentId ? db_count("SELECT COUNT(*) FROM attendance WHERE student_id=? AND status='present'", [$studentId]) : 0;
$attPct     = $attTotal > 0 ? round(($attPresent/$attTotal)*100) : 0;

// Pending fees
$pendingFee = $studentId ? (float)(db_fetch("SELECT COALESCE(SUM(balance_amount),0) AS t FROM fees WHERE student_id=? AND balance_amount>0", [$studentId])['t'] ?? 0) : 0;

// Today's timetable
$today = strtolower(date('l'));
$todaySchedule = ($classId && db_table_exists('routine'))
    ? db_fetchAll("SELECT * FROM routine WHERE class_id=? AND LOWER(day)=? ORDER BY start_time", [$classId, $today])
    : [];

// Homework due
$homework = ($classId && db_table_exists('homework'))
    ? db_fetchAll("SELECT * FROM homework WHERE class_id=? AND (due_date >= CURDATE() OR due_date IS NULL) ORDER BY due_date ASC LIMIT 6", [$classId])
    : [];

// Recent exam results
$results = ($studentId && db_table_exists('exam_results') && db_table_exists('exams'))
    ? db_fetchAll("SELECT er.*, e.name AS exam_name, e.exam_date FROM exam_results er LEFT JOIN exams e ON er.exam_id=e.id WHERE er.student_id=? ORDER BY e.exam_date DESC LIMIT 5", [$studentId])
    : [];

// Library
$issuedBooks = ($studentId && db_table_exists('library_issues'))
    ? db_fetchAll("SELECT li.*, lb.title FROM library_issues li LEFT JOIN library_books lb ON li.book_id=lb.id WHERE li.student_id=? AND li.return_date IS NULL ORDER BY li.issue_date DESC LIMIT 3", [$studentId])
    : [];

// Notices
$notices = db_table_exists('notices')
    ? db_fetchAll("SELECT title, content, created_at FROM notices WHERE is_active=1 ORDER BY created_at DESC LIMIT 4")
    : [];

$dashColor = '#6366f1';
?>
<style>
.stu-hero { background:linear-gradient(135deg,var(--accent),var(--accent-hover)); border-radius:14px; padding:24px 28px; margin-bottom:24px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
.stu-avatar { width:56px; height:56px; border-radius:50%; background:rgba(99,102,241,.3); color:#a5b4fc; display:grid; place-items:center; font-size:24px; font-weight:800; flex-shrink:0; }
.stu-name { font-size:20px; font-weight:800; color:#fff; }
.stu-meta { color:#94a3b8; font-size:13px; margin-top:3px; }
.ring-wrap { display:flex; align-items:center; gap:20px; background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:14px; padding:20px 24px; }
.ring { width:80px; height:80px; border-radius:50%; background:conic-gradient(<?= $dashColor ?> <?= $attPct ?>%, rgba(255,255,255,.07) 0); display:grid; place-items:center; flex-shrink:0; }
.ring-inner { width:56px; height:56px; border-radius:50%; background:var(--surface-container-lowest); display:grid; place-items:center; font-weight:800; font-size:14px; }
.s-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:14px; margin-bottom:24px; }
.s-kpi-c { background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:12px; padding:16px; border-left:3px solid var(--c,#6366f1); }
.s-kpi-v { font-size:22px; font-weight:800; }
.s-kpi-l { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.06em; margin-top:3px; }
.s-cols { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media(max-width:640px){ .s-cols{grid-template-columns:1fr} }
.s-sh { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-bottom:12px; }
.s-row { display:flex; align-items:center; padding:9px 0; border-bottom:1px solid rgba(172, 179, 180, 0.15); font-size:13px; gap:10px; }
.s-row:last-child { border-bottom:none; }
.notice-item { background:rgba(99,102,241,.06); border-left:3px solid var(--accent); border-radius:0 8px 8px 0; padding:10px 12px; margin-bottom:8px; }
.notice-title { font-weight:600; font-size:13px; }
.notice-time { font-size:11px; color:var(--ink-3); margin-top:2px; }
</style>

<!-- Hero -->
<div class="stu-hero">
    <div class="stu-avatar"><?= strtoupper(substr($me['name'],0,1)) ?></div>
    <div>
        <div class="stu-name"><?= htmlspecialchars($me['name']) ?></div>
        <div class="stu-meta">
            <?= htmlspecialchars($myStudent['class_name'] ?? 'Class not assigned') ?>
            <?php if (!empty($myStudent['section'])): ?> — Section <?= htmlspecialchars($myStudent['section']) ?><?php endif; ?>
            <?php if (!empty($myStudent['admission_no'])): ?> · Admission #<?= htmlspecialchars($myStudent['admission_no']) ?><?php endif; ?>
        </div>
    </div>
</div>

<!-- KPI Row -->
<div class="s-kpi">
    <div class="s-kpi-c" style="--c:#6366f1">
        <div class="s-kpi-v"><?= $attPct ?>%</div>
        <div class="s-kpi-l">Attendance</div>
    </div>
    <div class="s-kpi-c" style="--c:<?= $pendingFee > 0 ? '#ef4444' : '#10b981' ?>">
        <div class="s-kpi-v" style="color:<?= $pendingFee > 0 ? '#ef4444' : '#10b981' ?>">₹<?= number_format($pendingFee,0) ?></div>
        <div class="s-kpi-l">Pending Fee</div>
    </div>
    <div class="s-kpi-c" style="--c:#f59e0b">
        <div class="s-kpi-v"><?= count($homework) ?></div>
        <div class="s-kpi-l">Due Homework</div>
    </div>
    <div class="s-kpi-c" style="--c:#3b82f6">
        <div class="s-kpi-v"><?= count($issuedBooks) ?></div>
        <div class="s-kpi-l">Books Issued</div>
    </div>
</div>

<div class="s-cols">
    <!-- Left -->
    <div>
        <div class="s-sh">Today's Schedule — <?= date('l') ?></div>
        <?php if (empty($todaySchedule)): ?>
            <div style="color:var(--ink-3);font-size:13px">No classes scheduled today.</div>
        <?php else: foreach ($todaySchedule as $r): ?>
        <div class="s-row">
            <div style="min-width:50px;color:var(--accent);font-weight:700;font-size:12px"><?= htmlspecialchars($r['start_time'] ?? '--') ?>–<?= htmlspecialchars($r['end_time'] ?? '--') ?></div>
            <div style="font-weight:600"><?= htmlspecialchars($r['subject'] ?? '-') ?></div>
        </div>
        <?php endforeach; endif; ?>

        <div class="s-sh" style="margin-top:22px">Due Homework</div>
        <?php if (empty($homework)): ?>
            <div style="color:var(--ink-3);font-size:13px">No pending homework. 🎉</div>
        <?php else: foreach ($homework as $hw): ?>
        <div class="s-row">
            <div>
                <div style="font-weight:600"><?= htmlspecialchars($hw['title'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($hw['subject'] ?? '') ?> · Due <?= htmlspecialchars($hw['due_date'] ?? 'N/A') ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Right -->
    <div>
        <div class="s-sh">Recent Exam Results</div>
        <?php if (empty($results)): ?>
            <div style="color:var(--ink-3);font-size:13px">No results entered yet.</div>
        <?php else: foreach ($results as $r): ?>
        <div class="s-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($r['exam_name'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($r['exam_date'] ?? '') ?></div>
            </div>
            <div style="text-align:right">
                <div style="font-weight:800"><?= htmlspecialchars($r['marks_obtained'] ?? '-') ?>/<?= htmlspecialchars($r['total_marks'] ?? '-') ?></div>
                <div style="font-size:11px;font-weight:700;color:var(--accent)"><?= htmlspecialchars($r['grade'] ?? '') ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <div class="s-sh" style="margin-top:22px">School Notices</div>
        <?php if (empty($notices)): ?>
            <div style="color:var(--ink-3);font-size:13px">No recent notices.</div>
        <?php else: foreach ($notices as $n): ?>
        <div class="notice-item">
            <div class="notice-title"><?= htmlspecialchars($n['title']) ?></div>
            <div class="notice-time"><?= htmlspecialchars($n['created_at']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
