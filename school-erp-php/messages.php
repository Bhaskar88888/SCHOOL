<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Messages';
$myUserId  = get_current_user_id();
$openThread = (int)($_GET['thread'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
    /* ── Messaging Layout ────────────────────────────────────────── */
    .msg-layout { display:grid; grid-template-columns:320px 1fr; height:calc(100vh - 64px); overflow:hidden; gap:0; }
    @media(max-width:768px){
        .msg-layout{grid-template-columns:1fr; }
        .msg-panel-right{display:none;}
        .msg-panel-right.active{display:flex;}
        .msg-panel-left.hidden{display:none;}
    }

    /* ── Thread List (Left Panel) ────────────────────────────────── */
    .msg-panel-left { border-right:1px solid var(--border); display:flex; flex-direction:column; background:var(--bg-card); overflow:hidden; }
    .msg-left-head { padding:16px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:10px; flex-shrink:0; }
    .msg-left-head-title { font-size:16px; font-weight:800; }
    .msg-search { padding:10px 14px; border-bottom:1px solid var(--border); flex-shrink:0; }
    .msg-search input { width:100%; background:var(--bg-secondary,rgba(255,255,255,.05)); border:1px solid var(--border); border-radius:8px; padding:8px 12px; font-size:13px; color:var(--text-primary); outline:none; }
    .msg-thread-list { overflow-y:auto; flex:1; }
    .msg-thread-item { padding:14px 16px; cursor:pointer; border-bottom:1px solid var(--border); transition:background .12s; position:relative; }
    .msg-thread-item:hover { background:rgba(99,102,241,.07); }
    .msg-thread-item.active { background:rgba(99,102,241,.12); border-left:3px solid var(--accent); }
    .msg-thread-item.unread .msg-thread-name { font-weight:800; }
    .msg-thread-item.unread::after { content:''; position:absolute; top:16px; right:14px; width:8px; height:8px; border-radius:50%; background:var(--accent); }
    .msg-thread-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .msg-thread-subject { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
    .msg-thread-preview { font-size:11px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:4px; }
    .msg-thread-time { font-size:10px; color:var(--text-muted); position:absolute; top:14px; right:20px; }
    .msg-empty-list { padding:40px 20px; text-align:center; color:var(--text-muted); font-size:13px; }

    /* ── Message View (Right Panel) ─────────────────────────────── */
    .msg-panel-right { flex-direction:column; display:flex; overflow:hidden; background:var(--bg-secondary,var(--bg-card)); }
    .msg-right-head { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; background:var(--bg-card); flex-shrink:0; }
    .msg-right-title { font-size:15px; font-weight:700; flex:1; }
    .msg-right-participants { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .msg-feed { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:14px; }
    .msg-bubble-row { display:flex; gap:10px; align-items:flex-end; }
    .msg-bubble-row.mine { flex-direction:row-reverse; }
    .msg-bubble-avatar { width:32px; height:32px; border-radius:50%; background:rgba(99,102,241,.2); color:var(--accent); font-weight:800; font-size:13px; display:grid; place-items:center; flex-shrink:0; }
    .msg-bubble { max-width:68%; padding:10px 14px; border-radius:14px; font-size:13px; line-height:1.55; }
    .msg-bubble.mine { background:var(--accent); color:#fff; border-bottom-right-radius:4px; }
    .msg-bubble.theirs { background:var(--bg-card); border:1px solid var(--border); border-bottom-left-radius:4px; }
    .msg-bubble-meta { font-size:10px; opacity:.7; margin-top:4px; }
    .msg-compose { padding:14px 18px; border-top:1px solid var(--border); background:var(--bg-card); flex-shrink:0; display:flex; gap:10px; align-items:flex-end; }
    .msg-compose-input { flex:1; background:var(--bg-secondary,rgba(255,255,255,.05)); border:1px solid var(--border); border-radius:10px; padding:10px 14px; font-size:13px; color:var(--text-primary); outline:none; resize:none; min-height:44px; max-height:120px; font-family:inherit; transition:border-color .15s; }
    .msg-compose-input:focus { border-color:var(--accent); }
    .msg-send-btn { background:var(--accent); color:#fff; border:none; border-radius:10px; padding:10px 18px; font-weight:700; font-size:13px; cursor:pointer; flex-shrink:0; transition:opacity .15s; }
    .msg-send-btn:hover { opacity:.85; }
    .msg-no-thread { display:flex; flex:1; flex-direction:column; align-items:center; justify-content:center; color:var(--text-muted); }
    .msg-no-thread-icon { font-size:56px; margin-bottom:16px; }

    /* ── Compose Modal ───────────────────────────────────────────── */
    .compose-btn { background:var(--accent); color:#fff; border:none; border-radius:8px; padding:7px 14px; font-weight:700; font-size:12px; cursor:pointer; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content" style="overflow:hidden">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="msg-layout">
            <!-- LEFT: Thread list -->
            <div class="msg-panel-left" id="msgPanelLeft">
                <div class="msg-left-head">
                    <span class="msg-left-head-title">✉ Messages</span>
                    <button class="compose-btn" onclick="openComposeModal()">+ Compose</button>
                </div>
                <div class="msg-search">
                    <input type="text" id="threadSearch" placeholder="Search conversations…" oninput="filterThreads()">
                </div>
                <div class="msg-thread-list" id="threadList">
                    <div class="msg-empty-list">Loading…</div>
                </div>
            </div>

            <!-- RIGHT: Message view -->
            <div class="msg-panel-right" id="msgPanelRight">
                <div class="msg-no-thread" id="noThreadView">
                    <div class="msg-no-thread-icon">✉</div>
                    <div style="font-size:16px;font-weight:700;margin-bottom:8px">Select a conversation</div>
                    <div style="font-size:13px">Or compose a new message to get started.</div>
                    <button class="compose-btn" style="margin-top:20px" onclick="openComposeModal()">+ New Message</button>
                </div>
                <div id="threadView" style="display:none;flex-direction:column;flex:1;overflow:hidden">
                    <div class="msg-right-head">
                        <div style="width:38px;height:38px;border-radius:50%;background:rgba(99,102,241,.2);color:var(--accent);display:grid;place-items:center;font-size:16px;font-weight:800;flex-shrink:0" id="tvAvatar">?</div>
                        <div style="flex:1">
                            <div class="msg-right-title" id="tvSubject">—</div>
                            <div class="msg-right-participants" id="tvParticipants">—</div>
                        </div>
                    </div>
                    <div class="msg-feed" id="msgFeed"></div>
                    <div class="msg-compose">
                        <textarea class="msg-compose-input" id="replyInput" placeholder="Write a reply… (Enter to send)" rows="1" onkeydown="handleReplyKey(event)"></textarea>
                        <button class="msg-send-btn" onclick="sendReply()">Send ↑</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal -->
<div class="modal-overlay" id="composeModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <div class="modal-title">✉ New Message</div>
            <button class="modal-close" type="button" onclick="closeModal('composeModal')">✕</button>
        </div>
        <form id="composeForm" onsubmit="submitCompose(event)">
            <div class="form-group">
                <label class="form-label">To *</label>
                <select class="form-control" name="recipient_id" id="recipientSelect" required>
                    <option value="">Loading recipients…</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Subject</label>
                <input type="text" class="form-control" name="subject" placeholder="What's this about?" value="">
            </div>
            <div class="form-group">
                <label class="form-label">Message *</label>
                <textarea class="form-control" name="body" rows="4" required placeholder="Write your message…" style="resize:vertical"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('composeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const MY_USER_ID = <?= $myUserId ?>;
let allThreads = [];
let activeThreadId = null;
let pollTimer = null;

// ── Load thread list ──────────────────────────────────────────────────────────
async function loadThreads() {
    const data = await apiGet('/api/messages/index.php?threads=1').catch(() => []);
    allThreads = Array.isArray(data) ? data : [];
    renderThreadList(allThreads);
    if (<?= $openThread ?> > 0 && !activeThreadId) {
        openThread(<?= $openThread ?>);
    }
}

function renderThreadList(threads) {
    const el = document.getElementById('threadList');
    if (!threads.length) {
        el.innerHTML = '<div class="msg-empty-list">No conversations yet.<br>Compose a message to start.</div>';
        return;
    }
    el.innerHTML = threads.map(t => {
        const unread = parseInt(t.unread_count || 0);
        const lastMsg = t.last_message ? t.last_message.substring(0, 60) + (t.last_message.length > 60 ? '…' : '') : '(No messages yet)';
        const timeLabel = t.last_message_at ? relTime(t.last_message_at) : '';
        const isActive = activeThreadId == t.id ? ' active' : '';
        const isUnread = unread > 0 ? ' unread' : '';
        return `<div class="msg-thread-item${isActive}${isUnread}" onclick="openThread(${t.id})" id="ti-${t.id}">
            <div class="msg-thread-name">${escHtml(t.subject || 'No Subject')}</div>
            <div class="msg-thread-subject" style="font-size:11px;color:var(--text-muted)">From: ${escHtml(t.last_sender || '—')}</div>
            <div class="msg-thread-preview">${escHtml(lastMsg)}</div>
            <div class="msg-thread-time">${timeLabel}</div>
        </div>`;
    }).join('');
}

function filterThreads() {
    const q = document.getElementById('threadSearch').value.toLowerCase();
    const filtered = allThreads.filter(t => (t.subject||'').toLowerCase().includes(q) || (t.last_message||'').toLowerCase().includes(q) || (t.last_sender||'').toLowerCase().includes(q));
    renderThreadList(filtered);
}

// ── Open a thread ─────────────────────────────────────────────────────────────
async function openThread(threadId) {
    activeThreadId = threadId;
    document.querySelectorAll('.msg-thread-item').forEach(el => el.classList.remove('active'));
    const ti = document.getElementById('ti-' + threadId);
    if (ti) { ti.classList.add('active'); ti.classList.remove('unread'); }

    document.getElementById('noThreadView').style.display = 'none';
    const tv = document.getElementById('threadView');
    tv.style.display = 'flex';

    document.getElementById('msgFeed').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted)">Loading…</div>';

    const d = await apiGet('/api/messages/index.php?thread_id=' + threadId).catch(() => null);
    if (!d || d.error) { showToast(d?.error || 'Failed to load thread', 'danger'); return; }

    const thread = d.thread;
    const msgs   = Array.isArray(d.messages) ? d.messages : [];
    const parts  = Array.isArray(d.participants) ? d.participants : [];

    document.getElementById('tvSubject').textContent = thread.subject || 'No Subject';
    document.getElementById('tvAvatar').textContent  = (thread.subject||'M').charAt(0).toUpperCase();
    document.getElementById('tvParticipants').textContent = 'With: ' + parts.filter(p => p.id != MY_USER_ID).map(p => p.name).join(', ');

    const feed = document.getElementById('msgFeed');
    if (!msgs.length) {
        feed.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px;font-size:13px">No messages yet. Say hello!</div>';
    } else {
        feed.innerHTML = msgs.map(m => {
            const mine = parseInt(m.sender_id) === MY_USER_ID;
            return `<div class="msg-bubble-row${mine ? ' mine' : ''}">
                <div class="msg-bubble-avatar">${escHtml((m.sender_name||'?').charAt(0).toUpperCase())}</div>
                <div>
                    ${!mine ? `<div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;${mine?'text-align:right':''}">${escHtml(m.sender_name||'')}</div>` : ''}
                    <div class="msg-bubble${mine ? ' mine' : ' theirs'}">
                        ${escHtml(m.body)}
                        <div class="msg-bubble-meta">${relTime(m.created_at)}</div>
                    </div>
                </div>
            </div>`;
        }).join('');
        feed.scrollTop = feed.scrollHeight;
    }

    // Auto-poll every 8s when viewing a thread
    clearInterval(pollTimer);
    pollTimer = setInterval(() => openThread(activeThreadId), 8000);

    history.replaceState(null, '', '/messages.php?thread=' + threadId);
}

// ── Send reply ────────────────────────────────────────────────────────────────
async function sendReply() {
    if (!activeThreadId) return;
    const input = document.getElementById('replyInput');
    const body  = input.value.trim();
    if (!body) return;
    input.value = '';
    input.style.height = '';

    const res = await apiPost('/api/messages/index.php', { thread_id: activeThreadId, body });
    if (res.success) {
        openThread(activeThreadId);
        loadThreads();
    } else {
        showToast(res.error || 'Send failed', 'danger');
        input.value = body;
    }
}

function handleReplyKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
    // auto-grow
    const t = e.target;
    t.style.height = 'auto';
    t.style.height = Math.min(t.scrollHeight, 120) + 'px';
}

// ── Compose ───────────────────────────────────────────────────────────────────
let recipientsLoaded = false;
async function openComposeModal() {
    if (!recipientsLoaded) {
        const data = await apiGet('/api/messages/index.php?recipients=1').catch(() => []);
        const sel = document.getElementById('recipientSelect');
        sel.innerHTML = '<option value="">— Select recipient —</option>' +
            (Array.isArray(data) ? data : []).map(u =>
                `<option value="${u.id}">${escHtml(u.name)} (${escHtml(u.role)})</option>`
            ).join('');
        recipientsLoaded = true;
    }
    openModal('composeModal');
}

async function submitCompose(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res  = await apiPost('/api/messages/index.php', data);
    if (res.success) {
        showToast('Message sent!');
        closeModal('composeModal');
        e.target.reset();
        recipientsLoaded = false;
        await loadThreads();
        openThread(res.thread_id);
    } else {
        showToast(res.error || 'Failed to send', 'danger');
    }
}

// ── Utils ─────────────────────────────────────────────────────────────────────
function relTime(dt) {
    if (!dt) return '';
    const diff = Date.now() - new Date(dt).getTime();
    const m = Math.floor(diff/60000);
    if (m < 1)  return 'just now';
    if (m < 60) return m + 'm ago';
    const h = Math.floor(m/60);
    if (h < 24) return h + 'h ago';
    return Math.floor(h/24) + 'd ago';
}

// ── Init ──────────────────────────────────────────────────────────────────────
loadThreads();
</script>
</body>
</html>
