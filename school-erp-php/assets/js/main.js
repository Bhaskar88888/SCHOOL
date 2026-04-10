// =============================================
// School ERP PHP - Global JavaScript
// =============================================

// ---- Sidebar Toggle ----
function toggleSidebar() {
    document.getElementById('sidebar')?.classList.toggle('open');
}

// ---- User Dropdown ----
function toggleUserMenu() {
    document.getElementById('userMenu')?.classList.toggle('open');
}

document.addEventListener('click', function (event) {
    const menu = document.getElementById('userMenu');
    if (menu && !event.target.closest('.user-dropdown')) {
        menu.classList.remove('open');
    }
});

// ---- Notifications Panel ----
function toggleNotifications() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('notifOverlay');
    panel?.classList.toggle('open');
    overlay?.classList.toggle('open');
    if (panel?.classList.contains('open')) {
        loadNotifications();
    }
}

function loadNotifications() {
    const list = document.getElementById('notifList');
    fetch('/api/notifications/list.php')
        .then((response) => response.json())
        .then((data) => {
            if (!list) {
                return;
            }
            if (!Array.isArray(data) || data.length === 0) {
                list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">No notifications</div>';
                return;
            }
            list.innerHTML = data.map((notification) => `
                <div class="notif-item">
                    <div class="notif-item-title">${escHtml(notification.title)}</div>
                    <div class="notif-item-time">${timeAgo(notification.created_at)}</div>
                </div>
            `).join('');
        })
        .catch(() => {
            if (list) {
                list.innerHTML = '<div style="padding:16px">Failed to load</div>';
            }
        });
}

// ---- AJAX Helpers ----
async function apiGet(url) {
    const response = await fetch(url);
    return response.json();
}

async function apiPost(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return response.json();
}

async function apiDelete(url) {
    const response = await fetch(url, { method: 'DELETE' });
    return response.json();
}

// ---- Modal Helpers ----
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach((modal) => modal.classList.remove('open'));
}

// ---- Toast Notifications ----
function showToast(message, type = 'success') {
    const normalizedType = type === 'error' ? 'danger' : type;
    const colors = {
        success: '#3fb950',
        danger: '#f85149',
        warning: '#d29922',
        info: '#58a6ff'
    };
    const icons = {
        success: 'OK',
        danger: 'X',
        warning: '!',
        info: 'i'
    };

    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 100px;
        right: 28px;
        z-index: 9999;
        background: var(--bg-card);
        border: 1px solid ${colors[normalizedType] || colors.success};
        color: #e6edf3;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-family: Inter, sans-serif;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        max-width: 320px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideUp 0.3s ease;
    `;
    toast.innerHTML = `<span>${icons[normalizedType] || 'OK'}</span><span>${escHtml(message)}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ---- Confirm Dialog ----
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// ---- Format Helpers ----
function escHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
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
    return 'Rs ' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function roleLabel(role) {
    const labels = {
        superadmin: 'Super Admin',
        admin: 'Admin',
        teacher: 'Teacher',
        student: 'Student',
        parent: 'Parent',
        staff: 'Staff',
        hr: 'HR',
        accounts: 'Accounts',
        accountant: 'Accounts',
        librarian: 'Librarian',
        canteen: 'Canteen',
        conductor: 'Conductor',
        driver: 'Driver'
    };
    const key = String(role || '').trim().toLowerCase();
    return labels[key] || (key ? key.charAt(0).toUpperCase() + key.slice(1) : '-');
}

function downloadCsv(filename, rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
        showToast('No records available for export', 'warning');
        return;
    }

    const headers = Object.keys(rows[0]);
    const csv = [
        headers.join(','),
        ...rows.map((row) => headers.map((header) => {
            const value = row[header] == null ? '' : String(row[header]);
            const safeValue = value.replace(/"/g, '""');
            return /[",\n]/.test(safeValue) ? `"${safeValue}"` : safeValue;
        }).join(','))
    ].join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}

window.roleLabel = roleLabel;
window.role_label = roleLabel;
window.downloadCsv = downloadCsv;

// ---- Chatbot ----
let chatbotOpen = false;

function toggleChatbot() {
    chatbotOpen = !chatbotOpen;
    const windowEl = document.getElementById('chatbotWindow');
    if (!windowEl) {
        return;
    }

    if (chatbotOpen) {
        windowEl.classList.add('open');
        document.getElementById('chatInput')?.focus();
        if (document.querySelectorAll('.chat-msg').length === 0) {
            addBotMessage("Hello. I am your School ERP assistant.\n\nYou can ask me about students, fees, attendance, exams, leave, and other school records.");
        }
    } else {
        windowEl.classList.remove('open');
    }
}

function addBotMessage(text) {
    const body = document.getElementById('chatBody');
    if (!body) {
        return;
    }
    const message = document.createElement('div');
    message.className = 'chat-msg bot';
    message.innerHTML = `
        <div class="chat-avatar">AI</div>
        <div class="chat-bubble">${escHtml(text).replace(/\n/g, '<br>')}</div>
    `;
    body.appendChild(message);
    body.scrollTop = body.scrollHeight;
}

function addUserMessage(text) {
    const body = document.getElementById('chatBody');
    if (!body) {
        return;
    }
    const message = document.createElement('div');
    message.className = 'chat-msg user';
    message.innerHTML = `<div class="chat-bubble">${escHtml(text)}</div>`;
    body.appendChild(message);
    body.scrollTop = body.scrollHeight;
}

function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const text = input?.value.trim();
    if (!text) {
        return;
    }

    input.value = '';
    addUserMessage(text);

    const body = document.getElementById('chatBody');
    if (!body) {
        return;
    }

    const typing = document.createElement('div');
    typing.className = 'chat-msg bot';
    typing.id = 'typingIndicator';
    typing.innerHTML = '<div class="chat-avatar">AI</div><div class="chat-bubble" style="opacity:0.6">Thinking...</div>';
    body.appendChild(typing);
    body.scrollTop = body.scrollHeight;

    fetch('/api/chatbot/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
    })
        .then((response) => response.json())
        .then((data) => {
            document.getElementById('typingIndicator')?.remove();
            addBotMessage(data.reply || 'Sorry, I could not process that.');
        })
        .catch(() => {
            document.getElementById('typingIndicator')?.remove();
            addBotMessage('Sorry, there was an error. Please try again.');
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.addEventListener('keypress', function (event) {
            if (event.key === 'Enter') {
                sendChatMessage();
            }
        });
    }
});

// ---- Table Search ----
function filterTable(inputId, tableId) {
    const query = document.getElementById(inputId)?.value.toLowerCase() || '';
    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
    rows.forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
    });
}
