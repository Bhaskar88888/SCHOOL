<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Fee Management';
$classes   = db_fetchAll("SELECT id, name FROM classes ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Summary Cards -->
        <div class="stats-grid" id="feeStats">
            <div class="stat-card" style="--stat-color:#3fb950">
                <div class="stat-icon" style="--stat-color:#3fb950">💰</div>
                <div class="stat-info"><div class="stat-value" id="statMonthly">...</div><div class="stat-label">Collected This Month</div></div>
            </div>
            <div class="stat-card" style="--stat-color:#f85149">
                <div class="stat-icon" style="--stat-color:#f85149">⚠️</div>
                <div class="stat-info"><div class="stat-value" id="statPending">...</div><div class="stat-label">Total Pending</div></div>
            </div>
            <div class="stat-card" style="--stat-color:#4f8ef7">
                <div class="stat-icon" style="--stat-color:#4f8ef7">📋</div>
                <div class="stat-info"><div class="stat-value" id="statCount">...</div><div class="stat-label">Pending Students</div></div>
            </div>
        </div>

        <div class="page-toolbar">
            <div class="toolbar-left">
                <input type="text" class="form-control" id="searchInput" placeholder="🔍 Search student or receipt..." style="width:260px" oninput="loadFees()">
                <select class="form-control" id="statusFilter" onchange="loadFees()" style="width:160px">
                    <option value="">All Status</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Collect Fee</button>
            </div>
        </div>

        <div class="card">
            <div id="tableLoading" style="text-align:center;padding:40px"><div class="spinner"></div></div>
            <div class="table-wrap" id="tableWrap" style="display:none">
                <table>
                    <thead><tr><th>Student</th><th>Fee Type</th><th>Total</th><th>Paid</th><th>Balance</th><th>Date</th><th>Receipt</th><th>Status</th></tr></thead>
                    <tbody id="feeBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Collect Fee Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">💰 Collect Fee</div><button class="modal-close" onclick="closeModal('addModal')">✕</button></div>
        <form id="addForm" onsubmit="submitFee(event)">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Student *</label>
                    <select class="form-control" name="student_id" id="studentSel" required><option value="">Select Student...</option></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fee Type</label>
                    <select class="form-control" name="fee_type">
                        <option>Tuition Fee</option><option>Exam Fee</option><option>Transport Fee</option><option>Hostel Fee</option><option>Library Fee</option><option>Miscellaneous</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Total Amount *</label><input type="number" class="form-control" name="total_amount" step="0.01" required></div>
                <div class="form-group"><label class="form-label">Amount Paid *</label><input type="number" class="form-control" name="amount_paid" step="0.01" required></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select class="form-control" name="payment_method"><option value="cash">Cash</option><option value="bank">Bank Transfer</option><option value="online">Online</option><option value="cheque">Cheque</option></select>
                </div>
                <div class="form-group"><label class="form-label">Month</label><input type="month" class="form-control" name="month_year" value="<?= date('Y-m') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks" rows="2"></textarea></div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Collect Payment</button>
            </div>
        </form>
    </div>
</div>

<button class="chatbot-btn" onclick="toggleChatbot()" title="AI Assistant">🤖</button>
<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-head"><span class="chatbot-head-icon">🤖</span><div><div class="chatbot-head-title">ERP Assistant</div><div class="chatbot-head-sub">Ask about fees</div></div><button class="chatbot-head-close" onclick="toggleChatbot()">✕</button></div>
    <div class="chatbot-body" id="chatBody"></div>
    <div class="chatbot-footer"><input type="text" id="chatInput" placeholder="Ask about fee status..."/><button class="chatbot-send" onclick="sendChatMessage()">➤</button></div>
</div>

<script src="/assets/js/main.js"></script>
<script>
async function loadFees() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    document.getElementById('tableLoading').style.display = 'block';
    document.getElementById('tableWrap').style.display = 'none';

    const data = await apiGet(`/api/fee/index.php?search=${encodeURIComponent(search)}&status=${status}`);

    document.getElementById('feeBody').innerHTML = (data.data || []).map(f => `
        <tr>
            <td><strong>${escHtml(f.student_name||'-')}</strong><div style="font-size:11px;color:var(--text-muted)">${escHtml(f.class_name||'')}</div></td>
            <td>${escHtml(f.fee_type||'-')}</td>
            <td>₹${parseFloat(f.total_amount||0).toLocaleString('en-IN')}</td>
            <td>₹${parseFloat(f.amount_paid||0).toLocaleString('en-IN')}</td>
            <td style="color:${f.balance_amount>0?'var(--danger)':'var(--success)'}">₹${parseFloat(f.balance_amount||0).toLocaleString('en-IN')}</td>
            <td>${f.paid_date ? new Date(f.paid_date).toLocaleDateString('en-IN') : '-'}</td>
            <td><span style="font-size:11px;font-family:monospace">${escHtml(f.receipt_no||'-')}</span></td>
            <td><span class="badge ${f.balance_status==='paid'?'badge-success':'badge-warning'}">${f.balance_status}</span></td>
        </tr>
    `).join('') || '<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">💰</div><div class="empty-state-text">No fee records</div></div></td></tr>';

    document.getElementById('tableLoading').style.display = 'none';
    document.getElementById('tableWrap').style.display = 'block';
}

async function loadStudents() {
    const data = await apiGet('/api/students/index.php?page=1&search=');
    const sel = document.getElementById('studentSel');
    (data.data||[]).forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id; opt.textContent = `${s.name} (${s.class_name||'No Class'})`;
        sel.appendChild(opt);
    });
}

async function loadStats() {
    const data = await apiGet('/api/dashboard/stats.php');
    document.getElementById('statMonthly').textContent = '₹'+data.fee_this_month.toLocaleString('en-IN');
    document.getElementById('statPending').textContent = '₹'+data.pending_fee.toLocaleString('en-IN');
    document.getElementById('statCount').textContent = '-';
}

async function submitFee(e) {
    e.preventDefault();
    const form = document.getElementById('addForm');
    const data = Object.fromEntries(new FormData(form));
    if (data.month_year) { const [y,m] = data.month_year.split('-'); data.year = +y; data.month = new Date(y,m-1).toLocaleString('en-US',{month:'long'}); }
    const res = await apiPost('/api/fee/index.php', data);
    if (res.success) {
        showToast(`Payment recorded! Receipt: ${res.receipt_no}`);
        closeModal('addModal'); form.reset(); loadFees(); loadStats();
    } else showToast(res.error || 'Failed', 'danger');
}

loadFees(); loadStudents(); loadStats();
</script>
</body>
</html>
