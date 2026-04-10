<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Dashboard';

// Fetch quick stats
$totalStudents  = db_count("SELECT COUNT(*) FROM students WHERE is_active = 1");
$totalStaff     = db_count("SELECT COUNT(*) FROM users WHERE role IN ('teacher','admin','accountant','librarian') AND is_active = 1");
$totalFeeThisMonth = db_count("SELECT COALESCE(SUM(amount_paid),0) FROM fees WHERE MONTH(paid_date)=MONTH(NOW()) AND YEAR(paid_date)=YEAR(NOW())");
$pendingFee     = db_count("SELECT COALESCE(SUM(balance_amount),0) FROM fees WHERE balance_amount > 0");
$todayAttendance = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'");
$pendingComplaints = db_count("SELECT COUNT(*) FROM complaints WHERE status = 'pending'");
$totalBooks     = db_count("SELECT COUNT(*) FROM library_books");
$activeNotices  = db_count("SELECT COUNT(*) FROM notices WHERE is_active = 1");

// Recent students
$recentStudents = db_fetchAll("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.created_at DESC LIMIT 5");

// Recent fees
$recentFees = db_fetchAll("SELECT f.*, s.name as student_name FROM fees f LEFT JOIN students s ON f.student_id = s.id ORDER BY f.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" style="--stat-color:#4f8ef7">
                <div class="stat-icon" style="--stat-color:#4f8ef7">👨‍🎓</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($totalStudents) ?></div>
                    <div class="stat-label">Total Students</div>
                    <div class="stat-change up">↑ Active enrollments</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#3fb950">
                <div class="stat-icon" style="--stat-color:#3fb950">💰</div>
                <div class="stat-info">
                    <div class="stat-value">₹<?= number_format($totalFeeThisMonth) ?></div>
                    <div class="stat-label">Fee Collected (This Month)</div>
                    <div class="stat-change up">↑ Monthly revenue</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#f85149">
                <div class="stat-icon" style="--stat-color:#f85149">⚠️</div>
                <div class="stat-info">
                    <div class="stat-value">₹<?= number_format($pendingFee) ?></div>
                    <div class="stat-label">Pending Fees</div>
                    <div class="stat-change down">↓ Needs collection</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#58a6ff">
                <div class="stat-icon" style="--stat-color:#58a6ff">✅</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($todayAttendance) ?></div>
                    <div class="stat-label">Present Today</div>
                    <div class="stat-change up">↑ Today's count</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#d29922">
                <div class="stat-icon" style="--stat-color:#d29922">👔</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($totalStaff) ?></div>
                    <div class="stat-label">Staff Members</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#8957e5">
                <div class="stat-icon" style="--stat-color:#8957e5">📚</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($totalBooks) ?></div>
                    <div class="stat-label">Library Books</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#f85149">
                <div class="stat-icon" style="--stat-color:#f85149">📣</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($pendingComplaints) ?></div>
                    <div class="stat-label">Pending Complaints</div>
                </div>
            </div>
            <div class="stat-card" style="--stat-color:#3fb950">
                <div class="stat-icon" style="--stat-color:#3fb950">📌</div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($activeNotices) ?></div>
                    <div class="stat-label">Active Notices</div>
                </div>
            </div>
        </div>

        <!-- Bottom Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

            <!-- Recent Students -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">👨‍🎓 Recent Students</div>
                    <a href="/students.php" class="btn btn-secondary btn-sm">View All</a>
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
                            <tr><td colspan="3"><div class="empty-state"><div class="empty-state-icon">👨‍🎓</div><div class="empty-state-text">No students yet</div></div></td></tr>
                            <?php else: ?>
                            <?php foreach ($recentStudents as $s): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div class="user-avatar" style="width:28px;height:28px;font-size:11px"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                                        <?= htmlspecialchars($s['name']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($s['class_name'] ?? '-') ?></td>
                                <td><span class="badge badge-success">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Fee Payments -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">💰 Recent Payments</div>
                    <a href="/fee.php" class="btn btn-secondary btn-sm">View All</a>
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
                            <tr><td colspan="3"><div class="empty-state"><div class="empty-state-icon">💰</div><div class="empty-state-text">No payments yet</div></div></td></tr>
                            <?php else: ?>
                            <?php foreach ($recentFees as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['student_name'] ?? 'Unknown') ?></td>
                                <td>₹<?= number_format($f['amount_paid']) ?></td>
                                <td>
                                    <?php if ($f['balance_amount'] > 0): ?>
                                    <span class="badge badge-warning">Partial</span>
                                    <?php else: ?>
                                    <span class="badge badge-success">Paid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chatbot -->
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
        <input type="text" id="chatInput" placeholder="Ask me anything..." />
        <button class="chatbot-send" onclick="sendChatMessage()">➤</button>
    </div>
</div>

<script src="/assets/js/main.js"></script>
</body>
</html>
