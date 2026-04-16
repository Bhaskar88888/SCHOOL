<?php
/**
 * Teacher Panel
 */
$me = get_authenticated_user();
$myUserId = get_current_user_id();

// My classes
$classTeacherCol = db_column_exists('classes','teacher_id') ? 'teacher_id' : (db_column_exists('classes','class_teacher_id') ? 'class_teacher_id' : null);
$myClasses = $classTeacherCol
    ? db_fetchAll("SELECT c.*, (SELECT COUNT(*) FROM students WHERE class_id=c.id AND is_active=1) AS student_count FROM classes c WHERE c.$classTeacherCol=? ORDER BY c.name", [$myUserId])
    : [];

// Today's timetable
$today = strtolower(date('l'));
$todayRoutine = (db_table_exists('routine') && $classTeacherCol)
    ? db_fetchAll("SELECT r.*, c.name AS class_name FROM routine r LEFT JOIN classes c ON r.class_id=c.id WHERE LOWER(r.day)=? AND c.{$classTeacherCol}=? ORDER BY r.start_time LIMIT 8", [$today, $myUserId])
    : [];

// Pending homework
$pendingHomework = db_table_exists('homework')
    ? db_fetchAll("SELECT h.*, c.name AS class_name FROM homework h LEFT JOIN classes c ON h.class_id=c.id WHERE h.created_by=? AND (h.due_date >= CURDATE() OR h.due_date IS NULL) ORDER BY h.due_date ASC LIMIT 5", [$myUserId])
    : [];

// My leave balance
$leaveBalance = db_table_exists('leave_balances')
    ? db_fetch("SELECT * FROM leave_balances WHERE user_id=? AND year=YEAR(NOW())", [$myUserId])
    : null;

// Pending leave request
$myLeaveRequests = db_table_exists('leave_applications')
    ? db_fetchAll("SELECT * FROM leave_applications WHERE applicant_id=? ORDER BY created_at DESC LIMIT 3", [$myUserId])
    : [];

// Unread messages
$unreadMessages = db_table_exists('thread_participants')
    ? db_count("SELECT COUNT(*) FROM thread_participants tp JOIN messages m ON m.thread_id=tp.thread_id WHERE tp.user_id=? AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at) AND m.sender_id != ?", [$myUserId, $myUserId])
    : 0;

// Recent remarks I posted
$recentRemarks = db_table_exists('remarks')
    ? db_fetchAll("SELECT r.*, s.name AS student_name FROM remarks r LEFT JOIN students s ON r.student_id=s.id WHERE r.teacher_id=? ORDER BY r.created_at DESC LIMIT 4", [$myUserId])
    : [];
?>
<style>
.t-hero { background: linear-gradient(135deg,var(--accent),var(--accent-hover)); border-radius:14px; padding:24px 28px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.t-hero-name { font-size:20px; font-weight:800; color:#fff; }
.t-hero-role { color:#94a3b8; font-size:13px; margin-top:4px; }
.t-kpi { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
.t-kpi-c { background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:12px; padding:16px; border-left:3px solid var(--c,#6366f1); }
.t-kpi-v { font-size:24px; font-weight:800; }
.t-kpi-l { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.06em; margin-top:3px; }
.t-cols { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media(max-width:640px){ .t-cols{grid-template-columns:1fr;} }
.t-sh { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-bottom:12px; }
.t-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid rgba(172, 179, 180, 0.15); font-size:13px; }
.t-row:last-child { border-bottom:none; }
.t-class-card { background:var(--surface-container-lowest); border:1px solid rgba(172, 179, 180, 0.15); border-radius:12px; padding:14px; margin-bottom:12px; display:flex; align-items:center; gap:12px; }
.t-class-icon { width:40px; height:40px; border-radius:10px; background:rgba(99,102,241,.15); color:var(--accent); display:grid; place-items:center; font-size:18px; }
.t-class-name { font-weight:700; font-size:14px; }
.t-class-count { font-size:12px; color:var(--ink-3); }
.t-quick { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:22px; }
.t-quick a { padding:8px 14px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid rgba(172, 179, 180, 0.15); background:var(--surface-container-lowest); text-decoration:none; color:var(--ink); transition:background .15s; }
.t-quick a:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
.msg-badge { background:#ef4444; color:#fff; border-radius:999px; padding:1px 7px; font-size:10px; font-weight:700; margin-left:6px; }
</style>

<!-- Hero -->
<div class="t-hero">
    <div>
        <div class="t-hero-name">Hello, <?= htmlspecialchars(explode(' ',$me['name'])[0]) ?> 👋</div>
        <div class="t-hero-role">Teacher — <?= date('l, F j') ?></div>
    </div>
    <div style="display:flex;gap:10px">
        <a href="<?= BASE_URL ?>/messages.php" style="background:rgba(99,102,241,.2);color:#a5b4fc;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none">
            ✉ Messages <?php if ($unreadMessages > 0): ?><span class="msg-badge"><?= $unreadMessages ?></span><?php endif; ?>
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="t-kpi">
    <div class="t-kpi-c" style="--c:#6366f1">
        <div class="t-kpi-v"><?= count($myClasses) ?></div>
        <div class="t-kpi-l">My Classes</div>
    </div>
    <div class="t-kpi-c" style="--c:#10b981">
        <div class="t-kpi-v"><?= array_sum(array_column($myClasses,'student_count')) ?></div>
        <div class="t-kpi-l">Total Students</div>
    </div>
    <div class="t-kpi-c" style="--c:#f59e0b">
        <div class="t-kpi-v"><?= count($pendingHomework) ?></div>
        <div class="t-kpi-l">Active Homework</div>
    </div>
    <div class="t-kpi-c" style="--c:#3b82f6">
        <div class="t-kpi-v"><?= $unreadMessages ?></div>
        <div class="t-kpi-l">Unread Messages</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="t-quick">
    <a href="<?= BASE_URL ?>/attendance.php">📋 Mark Attendance</a>
    <a href="<?= BASE_URL ?>/homework.php">📖 Assign Homework</a>
    <a href="<?= BASE_URL ?>/exams.php">📝 Enter Results</a>
    <a href="<?= BASE_URL ?>/messages.php">✉ Message Parent</a>
    <a href="<?= BASE_URL ?>/remarks.php">📝 Add Remark</a>
    <a href="<?= BASE_URL ?>/leave.php">⏰ Apply Leave</a>
</div>

<div class="t-cols">
    <!-- My Classes -->
    <div>
        <div class="t-sh">My Classes</div>
        <?php if (empty($myClasses)): ?>
            <div style="color:var(--ink-3);font-size:13px">No classes assigned yet. Contact admin.</div>
        <?php else: foreach ($myClasses as $cls): ?>
        <div class="t-class-card">
            <div class="t-class-icon">🏫</div>
            <div>
                <div class="t-class-name"><?= htmlspecialchars($cls['name']) ?></div>
                <div class="t-class-count"><?= (int)$cls['student_count'] ?> students · <a href="<?= BASE_URL ?>/attendance.php?class_id=<?= $cls['id'] ?>" style="color:var(--accent)">Mark Attendance →</a></div>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <div class="t-sh" style="margin-top:20px">Today's Timetable</div>
        <?php if (empty($todayRoutine)): ?>
            <div style="color:var(--ink-3);font-size:13px">No timetable entries today.</div>
        <?php else: foreach ($todayRoutine as $r): ?>
        <div class="t-row">
            <div style="min-width:55px;color:var(--accent);font-weight:700;font-size:12px"><?= htmlspecialchars($r['start_time'] ?? '--:--') ?></div>
            <div>
                <div style="font-weight:600"><?= htmlspecialchars($r['subject'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($r['class_name'] ?? '') ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Homework + Leave requests -->
    <div>
        <div class="t-sh">Pending Homework</div>
        <?php if (empty($pendingHomework)): ?>
            <div style="color:var(--ink-3);font-size:13px">No active homework assignments.</div>
        <?php else: foreach ($pendingHomework as $hw): ?>
        <div class="t-row">
            <div>
                <div style="font-weight:600"><?= htmlspecialchars($hw['title'] ?? '-') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($hw['class_name'] ?? '') ?> · Due: <?= htmlspecialchars($hw['due_date'] ?? 'N/A') ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <div class="t-sh" style="margin-top:20px">My Leave Requests</div>
        <?php if (empty($myLeaveRequests)): ?>
            <div style="color:var(--ink-3);font-size:13px">No leave requests on record.</div>
        <?php else: foreach ($myLeaveRequests as $lr): ?>
        <div class="t-row">
            <div style="flex:1">
                <div style="font-weight:600"><?= htmlspecialchars($lr['leave_type'] ?? 'Leave') ?></div>
                <div style="font-size:11px;color:var(--ink-3)"><?= htmlspecialchars($lr['from_date'] ?? '') ?> → <?= htmlspecialchars($lr['to_date'] ?? '') ?></div>
            </div>
            <?php
            $ls = $lr['status'] ?? 'pending';
            $lc = $ls==='approved' ? '#10b981' : ($ls==='rejected' ? '#ef4444' : '#f59e0b');
            ?>
            <span style="padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;background:<?= $lc ?>22;color:<?= $lc ?>"><?= strtoupper($ls) ?></span>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/widgets/inbox_widget.php'; ?>
