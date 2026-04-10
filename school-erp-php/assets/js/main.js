// =============================================
// School ERP PHP - Global JavaScript
// =============================================

// ---- Sidebar Toggle ----
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// ---- User Dropdown ----
function toggleUserMenu() {
    document.getElementById('userMenu').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-dropdown')) {
        menu.classList.remove('open');
    }
});

// ---- Notifications Panel ----
function toggleNotifications() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('notifOverlay');
    panel.classList.toggle('open');
    overlay.classList.toggle('open');
    if (panel.classList.contains('open')) loadNotifications();
}

function loadNotifications() {
    fetch('/api/notifications/list.php')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notifList');
            if (!data.length) {
                list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">No notifications</div>';
                return;
            }
            list.innerHTML = data.map(n => `
                <div class="notif-item">
                    <div class="notif-item-title">${escHtml(n.title)}</div>
                    <div class="notif-item-time">${timeAgo(n.created_at)}</div>
                </div>
            `).join('');
        })
        .catch(() => {
            document.getElementById('notifList').innerHTML = '<div style="padding:16px">Failed to load</div>';
        });
}

// ---- AJAX Helpers ----
async function apiGet(url) {
    const res = await fetch(url);
    return res.json();
}

async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return res.json();
}

async function apiDelete(url) {
    const res = await fetch(url, { method: 'DELETE' });
    return res.json();
}

// ---- Modal Helpers ----
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
}

// ---- Toast Notifications ----
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    const colors = { success: '#3fb950', danger: '#f85149', warning: '#d29922', info: '#58a6ff' };
    toast.style.cssText = `
        position: fixed; bottom: 100px; right: 28px; z-index: 9999;
        background: var(--bg-card); border: 1px solid ${colors[type] || colors.success};
        color: #e6edf3; padding: 12px 20px; border-radius: 8px;
        font-size: 13px; font-family: Inter, sans-serif;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4); max-width: 320px;
        display: flex; align-items: center; gap: 10px;
        animation: slideUp 0.3s ease;
    `;
    const icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
    toast.innerHTML = `<span>${icons[type] || '✅'}</span><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ---- Confirm Dialog ----
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}

// ---- Format Helpers ----
function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function timeAgo(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
    return Math.floor(diff / 86400) + ' days ago';
}

function formatCurrency(amount) {
    return '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ---- Chatbot ----
let chatbotOpen = false;

function toggleChatbot() {
    chatbotOpen = !chatbotOpen;
    const win = document.getElementById('chatbotWindow');
    if (chatbotOpen) {
        win.classList.add('open');
        document.getElementById('chatInput').focus();
        if (document.querySelectorAll('.chat-msg').length === 0) {
            addBotMessage("Hello! 👋 I'm your School ERP assistant. How can I help you today?\n\nYou can ask me about:\n• Student information\n• Fee status\n• Attendance records\n• Exam results\n• Leave applications\n• And much more!");
        }
    } else {
        win.classList.remove('open');
    }
}

function addBotMessage(text) {
    const body = document.getElementById('chatBody');
    const msg = document.createElement('div');
    msg.className = 'chat-msg bot';
    msg.innerHTML = `
        <div class="chat-avatar">🤖</div>
        <div class="chat-bubble">${text.replace(/\n/g, '<br>')}</div>
    `;
    body.appendChild(msg);
    body.scrollTop = body.scrollHeight;
}

function addUserMessage(text) {
    const body = document.getElementById('chatBody');
    const msg = document.createElement('div');
    msg.className = 'chat-msg user';
    msg.innerHTML = `<div class="chat-bubble">${escHtml(text)}</div>`;
    body.appendChild(msg);
    body.scrollTop = body.scrollHeight;
}

function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    addUserMessage(text);

    // Show typing indicator
    const body = document.getElementById('chatBody');
    const typing = document.createElement('div');
    typing.className = 'chat-msg bot';
    typing.id = 'typingIndicator';
    typing.innerHTML = `<div class="chat-avatar">🤖</div><div class="chat-bubble" style="opacity:0.6">Thinking...</div>`;
    body.appendChild(typing);
    body.scrollTop = body.scrollHeight;

    fetch('/api/chatbot/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('typingIndicator')?.remove();
        addBotMessage(data.reply || 'Sorry, I could not process that.');
    })
    .catch(() => {
        document.getElementById('typingIndicator')?.remove();
        addBotMessage('Sorry, there was an error. Please try again.');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendChatMessage();
        });
    }
});

// ---- Table Search ----
function filterTable(inputId, tableId) {
    const query = document.getElementById(inputId).value.toLowerCase();
    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
    });
}
