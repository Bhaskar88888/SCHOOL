<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'AI Chatbot Assistant';
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
            display: flex;
            flex-direction: column;
            height: calc(100vh - 160px);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .chat-header {
            padding: 20px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .chat-header-icon {
            font-size: 32px;
            background: var(--accent-glow);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 15px;
            font-size: 14px;
            line-height: 1.5;
        }
        .message.bot {
            align-self: flex-start;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-bottom-left-radius: 2px;
        }
        .message.user {
            align-self: flex-end;
            background: var(--accent);
            color: white;
            border-bottom-right-radius: 2px;
        }
        .chat-input-area {
            padding: 20px;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }
        .chat-input {
            flex: 1;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 15px;
            color: var(--text-primary);
            transition: var(--transition);
        }
        .chat-input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .btn-send {
            padding: 0 20px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-send:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
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
                <div class="chat-header-icon">🤖</div>
                <div>
                    <div style="font-weight:700;font-size:16px">ERP Assistant</div>
                    <div style="font-size:12px;color:var(--text-muted)">Online • How can I help you today?</div>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="message bot">
                    Hello! I'm your School ERP Assistant. You can ask me about students, fees, attendance, or school policies.
                </div>
            </div>

            <form class="chat-input-area" id="chatForm" onsubmit="handleChat(event)">
                <input type="text" id="userInput" class="chat-input" placeholder="Type your message here..." autocomplete="off">
                <button type="submit" class="btn-send">Send ➤</button>
            </form>
        </div>
    </div>
</div>

<script>
async function handleChat(event) {
    event.preventDefault();
    const input = document.getElementById('userInput');
    const message = input.value.trim();
    if (!message) return;

    appendMessage('user', message);
    input.value = '';

    try {
        const response = await fetch('/api/chatbot/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message })
        });
        const data = await response.json();
        appendMessage('bot', data.reply || "I'm sorry, I couldn't process that.");
    } catch (error) {
        appendMessage('bot', "Error connecting to AI assistant.");
    }
}

function appendMessage(role, text) {
    const chatMessages = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = `message ${role}`;
    div.innerText = text;
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
