<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Dashboard';

// ── Quick stats ────────────────────────────────────────
$totalStudents     = db_count("SELECT COUNT(*) FROM students WHERE is_active = 1");
$totalStaff        = db_count("SELECT COUNT(*) FROM users WHERE role IN ('teacher','admin','accountant','accounts','librarian','hr','canteen','conductor','driver','staff') AND is_active = 1");
$totalFeeThisMonth = db_count("SELECT COALESCE(SUM(amount_paid),0) FROM fees WHERE MONTH(paid_date)=MONTH(NOW()) AND YEAR(paid_date)=YEAR(NOW())");
$pendingFee        = db_count("SELECT COALESCE(SUM(balance_amount),0) FROM fees WHERE balance_amount > 0");
$todayAttendance   = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'");
$pendingComplaints = db_count("SELECT COUNT(*) FROM complaints WHERE status = 'pending'");
$totalBooks        = db_count("SELECT COUNT(*) FROM library_books");
$activeNotices     = db_count("SELECT COUNT(*) FROM notices WHERE is_active = 1");

// ── Recent data ────────────────────────────────────────
$recentStudents = db_fetchAll("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.created_at DESC LIMIT 6");
$recentFees     = db_fetchAll("SELECT f.*, s.name as student_name FROM fees f LEFT JOIN students s ON f.student_id = s.id ORDER BY f.created_at DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Page Hero -->
        <div class="page-hero">
            <div class="page-hero-title">
                Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 17) ? 'afternoon' : 'evening') ?>,
                <?= htmlspecialchars(explode(' ', get_authenticated_user()['name'])[0]) ?> 👋
            </div>
            <div class="page-hero-sub"><?= date('l, F j, Y') ?> · Here's what's happening today.</div>
        </div>

        <!-- ── Stats row ───────────────────────────────── -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-value"><?= number_format($totalStudents) ?></div>
                <div class="stat-label">Total Students</div>
                <div class="stat-change up">Active enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₹<?= number_format($totalFeeThisMonth) ?></div>
                <div class="stat-label">Fee Collected This Month</div>
                <div class="stat-change up">Monthly revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-value">₹<?= number_format($pendingFee) ?></div>
                <div class="stat-label">Pending Fees</div>
                <div class="stat-change down">Needs collection</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= number_format($todayAttendance) ?></div>
                <div class="stat-label">Present Today</div>
                <div class="stat-change up">Today's count</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👔</div>
                <div class="stat-value"><?= number_format($totalStaff) ?></div>
                <div class="stat-label">Staff Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?= number_format($totalBooks) ?></div>
                <div class="stat-label">Library Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📣</div>
                <div class="stat-value"><?= number_format($pendingComplaints) ?></div>
                <div class="stat-label">Pending Complaints</div>
                <?php if ($pendingComplaints > 0): ?>
                <div class="stat-change down">Requires attention</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📌</div>
                <div class="stat-value"><?= number_format($activeNotices) ?></div>
                <div class="stat-label">Active Notices</div>
            </div>
        </div>

        <!-- ── Quick actions ───────────────────────────── -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <div class="card-title">Quick Actions</div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/students.php?action=add" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Student
                </a>
                <a href="<?= BASE_URL ?>/fee.php" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Collect Fee
                </a>
                <a href="<?= BASE_URL ?>/attendance.php" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Mark Attendance
                </a>
                <a href="<?= BASE_URL ?>/notices.php" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Post Notice
                </a>
                <a href="<?= BASE_URL ?>/export.php" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Export Data
                </a>
            </div>
        </div>

        <!-- ── Two column split ────────────────────────── -->
        <div class="grid-2">

            <!-- Recent Students -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Recent Students</div>
                        <div class="card-sub">Latest admissions</div>
                    </div>
                    <a href="<?= BASE_URL ?>/students.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentStudents)): ?>
                            <tr><td colspan="3">
                                <div class="empty-state">
                                    <div class="empty-state-icon">👨‍🎓</div>
                                    <div class="empty-state-title">No students yet</div>
                                    <div class="empty-state-text">Add your first student to get started</div>
                                </div>
                            </td></tr>
                            <?php else: foreach ($recentStudents as $s): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div class="user-avatar" style="width:28px;height:28px;font-size:11px;flex-shrink:0">
                                            <?= strtoupper(substr($s['name'], 0, 1)) ?>
                                        </div>
                                        <span style="font-weight:500"><?= htmlspecialchars($s['name']) ?></span>
                                    </div>
                                </td>
                                <td style="color:var(--ink-3)"><?= htmlspecialchars($s['class_name'] ?? '—') ?></td>
                                <td><span class="badge badge-success">Active</span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Fee Payments -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Recent Payments</div>
                        <div class="card-sub">Latest fee transactions</div>
                    </div>
                    <a href="<?= BASE_URL ?>/fee.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentFees)): ?>
                            <tr><td colspan="3">
                                <div class="empty-state">
                                    <div class="empty-state-icon">💰</div>
                                    <div class="empty-state-title">No payments yet</div>
                                    <div class="empty-state-text">Fee payments will appear here</div>
                                </div>
                            </td></tr>
                            <?php else: foreach ($recentFees as $f): ?>
                            <tr>
                                <td style="font-weight:500"><?= htmlspecialchars($f['student_name'] ?? 'Unknown') ?></td>
                                <td>₹<?= number_format($f['amount_paid']) ?></td>
                                <td>
                                    <?php if ($f['balance_amount'] > 0): ?>
                                    <span class="badge badge-warning">Partial</span>
                                    <?php else: ?>
                                    <span class="badge badge-success">Paid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /grid-2 -->

    </div><!-- /main-content -->
</div><!-- /app-layout -->

<!-- AI Chatbot -->
<button class="chatbot-btn" onclick="toggleChatbot()" title="AI Assistant">🤖</button>
<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-head">
        <span class="chatbot-head-icon">🤖</span>
        <div>
            <div class="chatbot-head-title">ERP Assistant</div>
            <div class="chatbot-head-sub">AI-powered school helper</div>
        </div>
        <button class="chatbot-head-close" onclick="toggleChatbot()">✕</button>
    </div>
    <div class="chatbot-body" id="chatBody"></div>
    <div class="chatbot-footer">
        <input type="text" id="chatInput" placeholder="Ask me anything…" autocomplete="off"/>
        <button class="chatbot-send" onclick="sendChatMessage()">➤</button>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
