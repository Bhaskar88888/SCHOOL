<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'AI Chatbot Assistant';
$jsBaseUrl = rtrim(BASE_URL, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .chat-container {
            display: flex; flex-direction: column; height: calc(100vh - 160px);
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-lg);
        }
        .chat-header {
            padding: 15px 20px; background: var(--bg-secondary);
            border-bottom: 1px solid var(--border); display: flex;
            align-items: center; justify-content: space-between; gap: 15px;
        }
        .chat-header-left { display: flex; align-items: center; gap: 15px; }
        .chat-header-right { display: flex; align-items: center; gap: 10px; }
        .chat-header-icon {
            font-size: 28px; background: var(--accent-glow); width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center; border-radius: 12px;
        }
        
        .chat-Controls { display: flex; gap: 10px; flex-wrap: wrap; }
        .lang-switcher, .personality-toggle { display: flex; gap: 5px; background: var(--surface); padding: 4px; border-radius: 8px; border: 1px solid var(--border); }
        .btn-toggle {
            background: transparent; border: none; padding: 4px 10px; border-radius: 6px;
            font-size: 11px; font-weight: 500; color: var(--ink-3); cursor: pointer; transition: all 0.2s;
        }
        .btn-toggle.active { background: var(--white); color: var(--accent); box-shadow: var(--shadow-xs); font-weight: 600; }
        .btn-icon-clear, .btn-icon-export {
            background: var(--surface); border: 1px solid var(--border); color: var(--ink-3);
            width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .btn-icon-clear:hover, .btn-icon-export:hover { background: var(--off-white); color: var(--ink); }

        .chat-messages {
            flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: var(--white);
        }
        .message-wrapper { display: flex; flex-direction: column; gap: 5px; max-width: 80%; }
        .message-wrapper.user { align-self: flex-end; align-items: flex-end; }
        .message-wrapper.bot { align-self: flex-start; align-items: flex-start; }
        
        .message { padding: 12px 16px; border-radius: 15px; font-size: 14px; line-height: 1.5; }
        .message.bot { background: var(--surface); border: 1px solid #eee; border-bottom-left-radius: 4px; color: var(--ink); }
        .message.user { background: var(--accent); color: white; border-bottom-right-radius: 4px; }
        .message-time { font-size: 10px; color: var(--ink-4); }
        
        .message strong { font-weight: 600; }
        .message br { margin-bottom: 4px; display: block; content: ""; }

        /* Typing indicator */
        .typing-indicator { display: none; padding: 12px 16px; background: var(--surface); border-radius: 15px; border-bottom-left-radius: 4px; align-self: flex-start; }
        .typing-indicator span { display: inline-block; width: 6px; height: 6px; background: var(--ink-4); border-radius: 50%; margin: 0 2px; animation: bounce 1.4s infinite ease-in-out both; }
        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

        /* Suggestions & Actions */
        .chip-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px; }
        .chip { background: var(--white); border: 1px solid var(--accent); color: var(--accent); padding: 5px 12px; border-radius: 16px; font-size: 11px; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .chip:hover { background: var(--accent); color: var(--white); }
        .pill-container { padding: 12px 20px; background: var(--surface); border-top: 1px solid var(--border); display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; }
        .pill-container::-webkit-scrollbar { display: none; }
        .pill { background: var(--white); border: 1px solid #ddd; color: var(--ink-2); padding: 6px 14px; border-radius: 20px; font-size: 12px; cursor: pointer; transition: all 0.2s; white-space: nowrap; font-weight: 500; }
        .pill:hover { background: var(--off-white); border-color: var(--ink-3); color: var(--ink); }

        .chat-input-area { padding: 15px 20px; background: var(--white); border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; }
        .chat-input-wrapper { display: flex; gap: 10px; }
        .chat-input { flex: 1; background: var(--surface); border: 1px solid #ddd; border-radius: var(--radius-sm); padding: 12px 15px; color: var(--ink); transition: var(--transition); font-size: 14px; }
        .chat-input:focus { border-color: var(--accent); outline: none; background: var(--white); box-shadow: 0 0 0 3px var(--accent-glow); }
        .btn-send { padding: 0 20px; background: var(--accent); color: white; border: none; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; transition: var(--transition); }
        .btn-send:hover { opacity: 0.9; }
        
        .chat-footer-hints { display: flex; justify-content: space-between; font-size: 11px; color: var(--ink-4); padding: 0 5px; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="page-toolbar">
            <div style="font-size:18px;font-weight:700">🤖 AI Chatbot Assistant</div>
        </div>

        <div class="chat-container">
            <div class="chat-header">
                <div class="chat-header-left">
                    <div class="chat-header-icon" id="headerIcon">🤖</div>
                    <div>
                        <div style="font-weight:700;font-size:16px">ERP Assistant</div>
                        <div style="font-size:12px;color:var(--text-muted)">Online • Multilingual & Role-Aware</div>
                    </div>
                </div>
                <div class="chat-header-right">
                    <div class="lang-switcher" id="langSwitcher">
                        <!-- Populated by init() -->
                    </div>
                    <div class="personality-toggle" id="personalityToggle">
                        <!-- Populated by init() -->
                    </div>
                    <button class="btn-icon-export" onclick="chatbot.exportConversation()" title="Export Chat">💾</button>
                    <button class="btn-icon-clear" onclick="chatbot.clearHistory()" title="Clear Chat">🗑️</button>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <!-- Messages populated by JS -->
                <div class="typing-indicator" id="typingIndicator">
                    <span></span><span></span><span></span>
                </div>
            </div>

            <div class="pill-container" id="quickActionsBar">
                <!-- Populated by init() -->
            </div>

            <div class="chat-input-area">
                <form class="chat-input-wrapper" id="chatForm" onsubmit="chatbot.submitForm(event)">
                    <input type="text" id="userInput" class="chat-input" placeholder="Type your message or /help..." maxlength="500" autocomplete="off">
                    <button type="submit" class="btn-send">Send ➤</button>
                </form>
                <div class="chat-footer-hints">
                    <span>Type <strong>/help</strong> for shortcuts</span>
                    <span id="charCount">0/500</span>
                </div>
            </div>
        </div>
    </div>
</div>

</script>
<script>
const CHAT_BASE_URL = '<?= $jsBaseUrl ?>';
class SchoolERPChatbot {
    constructor() {
        this.lang = localStorage.getItem('chatbotLang') || 'en';
        this.personality = localStorage.getItem('chatbotPersonality') || 'friendly';
        this.history = [];
        this.shortcuts = {};
        
        // UI Elements
        this.messagesDiv = document.getElementById('chatMessages');
        this.inputEl = document.getElementById('userInput');
        this.typingIndicator = document.getElementById('typingIndicator');
        this.charCountEl = document.getElementById('charCount');
        
        // Event Listeners
        this.inputEl.addEventListener('input', () => {
        this.charCountEl.innerText = `${this.inputEl.value.length}/500`;
        });
    }

    async init() {
        try {
            // Load Bootstrap Data
            const res = await fetch(`${CHAT_BASE_URL}/api/chatbot/bootstrap.php?lang=${this.lang}`);
            const data = await res.json();
            
            this.shortcuts = data.shortcuts || {};
            this.renderLanguages(data.languages);
            this.renderPersonalities(data.personalityModes);
            this.renderQuickActions(data.quickActions);
            
            // Load History
            const histRes = await fetch(`${CHAT_BASE_URL}/api/chatbot/history.php?lang=${this.lang}&limit=50`);
            const histData = await histRes.json();
            
            if (histData.history && histData.history.length > 0) {
                // Render from bottom to top since it comes ordered DESC from API, we reversed it in API
                histData.history.forEach(log => {
                    this.renderMessage('user', log.message, log.created_at);
                    this.renderMessage('bot', log.response, log.created_at, log.intent);
                });
                this.scrollToBottom();
            } else {
                this.renderMessage('bot', data.welcome, new Date().toISOString(), 'welcome');
                if (data.suggestions) this.renderSuggestions(data.suggestions);
            }
        } catch (e) {
            console.error("Chatbot init failed", e);
            this.renderMessage('bot', "System error: Could not initialize chatbot.");
        }
    }

    renderLanguages(langs) {
        const container = document.getElementById('langSwitcher');
        container.innerHTML = '';
        Object.entries(langs).forEach(([code, label]) => {
            const btn = document.createElement('button');
            btn.className = `btn-toggle ${this.lang === code ? 'active' : ''}`;
            btn.innerText = code.toUpperCase();
            btn.onclick = () => this.setLanguage(code);
            container.appendChild(btn);
        });
    }

    renderPersonalities(modes) {
        const container = document.getElementById('personalityToggle');
        container.innerHTML = '';
        modes.forEach(mode => {
            const btn = document.createElement('button');
            btn.className = `btn-toggle ${this.personality === mode.id ? 'active' : ''}`;
            btn.innerText = mode.emoji;
            btn.title = mode.label;
            btn.onclick = () => this.setPersonality(mode.id, mode.emoji);
            container.appendChild(btn);
            if (this.personality === mode.id) {
                document.getElementById('headerIcon').innerText = mode.emoji;
            }
        });
    }

    renderQuickActions(actions) {
        const container = document.getElementById('quickActionsBar');
        container.innerHTML = '';
        if (!actions) return;
        Object.values(actions).forEach(text => {
            const btn = document.createElement('button');
            btn.className = 'pill';
            btn.innerText = text;
            btn.onclick = () => this.sendMessage(text);
            container.appendChild(btn);
        });
    }

    formatMarkdown(text) {
        if (!text) return '';
        let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formatted = formatted.replace(/\n-/g, '<br>•');
        formatted = formatted.replace(/\n/g, '<br>');
        return formatted;
    }

    formatTime(dateString) {
        const d = dateString ? new Date(dateString) : new Date();
        return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    renderMessage(role, text, time, intent = null) {
        const wrapper = document.createElement('div');
        wrapper.className = `message-wrapper ${role}`;
        
        const bubble = document.createElement('div');
        bubble.className = `message ${role}`;
        bubble.innerHTML = role === 'bot' ? this.formatMarkdown(text) : text;
        
        const timeEl = document.createElement('div');
        timeEl.className = 'message-time';
        timeEl.innerText = this.formatTime(time);
        
        wrapper.appendChild(bubble);
        wrapper.appendChild(timeEl);
        
        // Insert before typing indicator
        this.messagesDiv.insertBefore(wrapper, this.typingIndicator);
        this.scrollToBottom();
    }

    renderSuggestions(suggestions) {
        if (!suggestions || suggestions.length === 0) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'message-wrapper bot';
        
        const chipContainer = document.createElement('div');
        chipContainer.className = 'chip-container';
        
        suggestions.forEach(text => {
            const chip = document.createElement('button');
            chip.className = 'chip';
            chip.innerText = text;
            chip.onclick = () => this.sendMessage(text);
            chipContainer.appendChild(chip);
        });
        
        wrapper.appendChild(chipContainer);
        this.messagesDiv.insertBefore(wrapper, this.typingIndicator);
        this.scrollToBottom();
    }

    scrollToBottom() {
        this.messagesDiv.scrollTop = this.messagesDiv.scrollHeight;
    }

    async submitForm(e) {
        e.preventDefault();
        const msg = this.inputEl.value.trim();
        if (!msg) return;
        this.inputEl.value = '';
        this.charCountEl.innerText = '0/200';
        await this.sendMessage(msg);
    }

    async sendMessage(msg) {
        this.renderMessage('user', msg);
        this.history.push({role: 'user', message: msg, timestamp: new Date().toISOString()});
        
        this.typingIndicator.style.display = 'flex';
        this.scrollToBottom();

        try {
            const res = await fetch(`${CHAT_BASE_URL}/api/chatbot/chat.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: msg,
                    language: this.lang,
                    personality: this.personality
                })
            });
            const data = await res.json();
            this.typingIndicator.style.display = 'none';
            
            // Check if language was switched via shortcut
            if (data.newLanguage) {
                this.setLanguage(data.newLanguage, true); // reload UI to update language buttons and quick actions
            }
            
            this.renderMessage('bot', data.reply, data.timestamp, data.intent);
            if (data.suggestions) this.renderSuggestions(data.suggestions);
            
            this.history.push({role: 'bot', message: data.reply, intent: data.intent, timestamp: data.timestamp});
        } catch (e) {
            this.typingIndicator.style.display = 'none';
            this.renderMessage('bot', "Connection error. Please try again.");
        }
    }

    setLanguage(lang, reload = true) {
        this.lang = lang;
        localStorage.setItem('chatbotLang', lang);
        if (reload) {
            // Remove all messages to prevent mixing langs
            while (this.messagesDiv.firstChild && this.messagesDiv.firstChild !== this.typingIndicator) {
                this.messagesDiv.removeChild(this.messagesDiv.firstChild);
            }
            this.init();
        }
    }

    async setPersonality(modeId, emoji) {
        this.personality = modeId;
        localStorage.setItem('chatbotPersonality', modeId);
        document.getElementById('headerIcon').innerText = emoji;
        // Update personality button states without wiping chat history
        document.querySelectorAll('#personalityToggle .btn-toggle').forEach(btn => {
            btn.classList.toggle('active', btn.title === emoji || btn.innerText === emoji);
        });
        // Re-fetch quick actions for the new personality (no history wipe)
        try {
            const res = await fetch(`${CHAT_BASE_URL}/api/chatbot/bootstrap.php?lang=${this.lang}`);
            const data = await res.json();
            this.renderQuickActions(data.quickActions);
        } catch(e) {}
    }

    async clearHistory() {
        if (!confirm("Clear your chat history?")) return;
        try {
            await fetch(`${CHAT_BASE_URL}/api/chatbot/history.php`, { method: 'DELETE' });
            while (this.messagesDiv.firstChild && this.messagesDiv.firstChild !== this.typingIndicator) {
                this.messagesDiv.removeChild(this.messagesDiv.firstChild);
            }
            this.history = [];
            this.init();
        } catch (e) {
            console.error(e);
        }
    }

    async exportConversation() {
        try {
            const res = await fetch(`${CHAT_BASE_URL}/api/chatbot/history.php?export=1`);
            const data = await res.json();
            
            let text = `School ERP Chatbot Log - ${data.timestamp}\n`;
            text += `Language: ${data.language} | Role: ${data.role}\n`;
            text += `===============================================\n\n`;
            
            data.messages.forEach(m => {
                const intentStr = m.intent ? ` [Intent: ${m.intent}]` : '';
                text += `[${this.formatTime(m.timestamp)}] ${m.role.toUpperCase()}: ${m.message}${intentStr}\n\n`;
            });
            
            const blob = new Blob([text], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `chat_export_${new Date().getTime()}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
        } catch (e) {
            alert("Failed to export conversation.");
        }
    }
}

// Initialize on load
const chatbot = new SchoolERPChatbot();
window.addEventListener('DOMContentLoaded', () => {
    chatbot.init();
});
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
