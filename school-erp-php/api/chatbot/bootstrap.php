<?php
/**
 * Chatbot Bootstrap API - Role-based welcome messages and quick actions
 * School ERP PHP v4.0 - Full Node.js Parity
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/chatbot_engine.php';

require_auth();

// Defensive: ensure session is active before reading/writing $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId   = get_current_user_id();
$role     = get_current_role() ?? 'guest';
$language = $_GET['lang'] ?? 'en';

// Language options (plain object so JS Object.entries() works correctly)
$languages = [
    'en' => 'English',
    'hi' => 'Hindi',
    'as' => 'Assamese',
];

// Get welcome message, actions, suggestions, and proactive alerts from engine
$roleWelcome   = chatbot_get_role_welcome($role, $language);
$actions       = chatbot_get_role_quick_actions($role, $language);
$suggestions   = chatbot_get_role_suggestions($role, $language);
$alerts        = chatbot_get_proactive_alerts($userId, $role);

// Prepend alerts to welcome message if any exist
if ($alerts) {
    if ($language === 'hi') {
        $roleWelcome = "महत्वपूर्ण सूचना:\n" . $alerts . "\n" . $roleWelcome;
    } elseif ($language === 'as') {
        $roleWelcome = "গুৰুত্বপূৰ্ণ জাননী:\n" . $alerts . "\n" . $roleWelcome;
    } else {
        $roleWelcome = "IMPORTANT ALERTS:\n" . $alerts . "\n" . $roleWelcome;
    }
}

// Available personality modes (matches Node.js personalityModes)
$personalityModes = [
    ['id' => 'friendly', 'label' => 'Friendly', 'emoji' => '😊', 'greeting' => 'Hello! 👋 What can I help you with today?'],
    ['id' => 'formal',   'label' => 'Formal',   'emoji' => '🧑‍💼', 'greeting' => 'Good day. How may I assist you with the School ERP system?'],
    ['id' => 'funny',    'label' => 'Funny',    'emoji' => '😄', 'greeting' => '🤖 School bot online! What do you need?'],
];

// Keyboard shortcut map (matches Node.js shortcuts)
$shortcuts = [
    '/help'       => 'Show all commands and topics',
    '/start'      => 'Restart conversation with greeting',
    '/clear'      => 'Clear chat history',
    '/lang'       => 'Cycle through languages (EN → HI → AS)',
    '/admit'      => 'Student admission help',
    '/attendance' => 'Attendance module help',
    '/fee'        => 'Fee collection help',
    '/exam'       => 'Exam scheduling help',
    '/library'    => 'Library module help',
    '/hostel'     => 'Hostel module help',
    '/transport'  => 'Transport module help',
];

// Log chatbot bootstrap access (resets context)
if (db_table_exists('chatbot_logs')) {
    db_query(
        "INSERT INTO chatbot_logs (user_id, user_role, message, language, intent, response, session_id)
         VALUES (?, ?, 'bootstrap', ?, 'bootstrap', 'welcome_message', ?)",
        [$userId, $role, $language, session_id()]
    );
}

// Clear context on session start
$_SESSION['chatbot_context'][$userId] = [
    'messages'   => [],
    'lastIntent' => null,
    'entities'   => [],
    'timestamp'  => time(),
];

json_response([
    'welcome'          => $roleWelcome,
    'quickActions'     => $actions,
    'suggestions'      => $suggestions,
    'languages'        => $languages,
    'personalityModes' => $personalityModes,
    'shortcuts'        => $shortcuts,
    'defaultLanguage'  => 'en',
    'currentLanguage'  => $language,
    'role'             => $role,
]);

