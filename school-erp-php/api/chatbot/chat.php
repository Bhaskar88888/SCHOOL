<?php
/**
 * Chatbot Chat API — v5.0
 * School ERP PHP — Full Node.js Parity (NO Gemini / NO AI API)
 *
 * FEATURES:
 *  - 51+ intents in correct priority order
 *  - Shortcut commands: /help /start /clear /lang /admit /fee etc.
 *  - Spell correction (24 typos) via chatbot_engine.php
 *  - Synonym expansion (16 groups, EN+HI+AS transliterations)
 *  - Multi-intent "Did You Mean?" detection
 *  - Follow-up context memory (session-based, 6 rules)
 *  - Proactive alerts: attendance <75%, overdue books
 *  - Role-based welcome messages (12 roles × 3 languages)
 *  - Role-based fallback suggestions
 *  - English KB: 50 entries | Hindi KB: 43 entries | Assamese KB: 43 entries
 *  - UTF-8 safe via /u regex modifier + mb_strtolower()
 *  - Conversation logging to chatbot_logs table
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/chatbot_engine.php';

require_auth();
header('Content-Type: application/json');

$userId = get_current_user_id();
$role = get_current_role() ?? 'guest';

// Role-based access
$allowedRoles = ['superadmin', 'admin', 'teacher', 'student', 'parent', 'accounts', 'hr', 'canteen', 'conductor', 'driver', 'librarian', 'guest'];
if (!in_array($role, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to use the chatbot.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Rate Limiting (15 messages per minute)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$now = time();
if (!isset($_SESSION['chatbot_req_time']) || ($now - $_SESSION['chatbot_req_time'] > 60)) {
    $_SESSION['chatbot_req_count'] = 1;
    $_SESSION['chatbot_req_time'] = $now;
} else {
    $_SESSION['chatbot_req_count']++;
    if ($_SESSION['chatbot_req_count'] > 15) {
        json_response(['reply' => 'You are sending messages too fast. Please wait a minute before trying again. For privacy concerns, see ' . BASE_URL . '/privacy-policy.php', 'language' => 'en'], 429);
    }
}

$data = get_post_json();
$rawMessage = trim($data['message'] ?? '');
// Message Length Validation: Truncate to 500 characters
$rawMessage = mb_substr($rawMessage, 0, 500, 'UTF-8');
$message = htmlspecialchars($rawMessage, ENT_QUOTES, 'UTF-8');

$language = in_array($data['language'] ?? 'en', ['en', 'hi', 'as']) ? ($data['language'] ?? 'en') : 'en';
$personality = in_array($data['personality'] ?? 'friendly', ['friendly', 'formal', 'funny']) ? ($data['personality'] ?? 'friendly') : 'friendly';
$startTime = microtime(true);

if (!$message) {
    json_response(['reply' => 'Please type a message. For privacy info, visit ' . BASE_URL . '/privacy-policy.php', 'language' => $language]);
}

// ─── SPELL CORRECTION + SYNONYM EXPANSION (from chatbot_engine.php) ──────────
$msg = mb_strtolower($message, 'UTF-8');
$msg = chatbot_correct_spelling($msg);
$msg = chatbot_expand_synonyms($msg);
$reply = '';
$intent = 'unknown';
$ctx = chatbot_get_context((string) $userId);

// ─── LOAD KNOWLEDGE BASES ─────────────────────────────────────────────────
$knowledgeBaseEn = require __DIR__ . '/../../includes/chatbot_knowledge_en.php';
$knowledgeBaseHi = require __DIR__ . '/../../includes/chatbot_knowledge_hi.php';
$knowledgeBaseAs = require __DIR__ . '/../../includes/chatbot_knowledge_as.php';

// ─── HELPER: DB SAFE QUERY ────────────────────────────────────────────────
function chatbot_query(callable $callback)
{
    try {
        return $callback();
    } catch (Exception $e) {
        error_log('Chatbot DB Error: ' . $e->getMessage());
        return null;
    }
}

// ─── HELPER: EXTENDED KB SEARCH (matches findHindiExtendedResponse / findAssameseExtendedResponse) ──
function searchExtendedKB(array $kb, string $msg): ?array
{
    $queryTokens = array_filter(preg_split('/\s+/u', $msg), fn($t) => mb_strlen($t, 'UTF-8') > 1);
    $bestScore = 0;
    $bestMatch = null;

    foreach ($kb as $entry) {
        $score = 0;
        $normalizedTitle = mb_strtolower($entry['title'], 'UTF-8');
        $normalizedContent = mb_strtolower($entry['content'], 'UTF-8');
        $normalizedTags = array_map(fn($t) => mb_strtolower($t, 'UTF-8'), $entry['tags']);
        $haystack = $normalizedTitle . ' ' . $normalizedContent . ' ' . implode(' ', $normalizedTags);

        foreach ($normalizedTags as $tag) {
            if (!$tag)
                continue;
            if (mb_strpos($msg, $tag, 0, 'UTF-8') !== false || mb_strpos($haystack, $msg, 0, 'UTF-8') !== false) {
                $score += 5;
            } else {
                $tagTokens = array_filter(preg_split('/\s+/u', $tag), fn($t) => mb_strlen($t, 'UTF-8') > 1);
                if (count($tagTokens) > 0 && count(array_filter($tagTokens, fn($t) => mb_strpos($msg, $t, 0, 'UTF-8') !== false)) === count($tagTokens)) {
                    $score += 3;
                }
            }
        }

        foreach ($queryTokens as $token) {
            if (mb_strpos($haystack, $token, 0, 'UTF-8') !== false)
                $score += 1;
        }

        if (mb_strpos($normalizedTitle, $msg, 0, 'UTF-8') !== false)
            $score += 3;
        if (mb_strpos($normalizedContent, $msg, 0, 'UTF-8') !== false)
            $score += 2;

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $entry;
        }
    }

    return ($bestMatch && $bestScore >= 2) ? $bestMatch : null;
}

// ─── HELPER: CONTEXT SUGGESTIONS (matches getSuggestions()) ───────────────
function getSuggestionsForIntent(string $intent, string $role): array
{
    $map = [
        'studentAdmission' => ['What documents are needed?', 'Can I edit after submission?'],
        'admission' => ['What documents are needed?', 'How long does it take?'],
        'attendance_today' => ['How do I view reports?', 'Show absent students'],
        'fee_pending' => ['How do I download a receipt?', 'Can I apply a discount?'],
        'fee_collection' => ['Show fee defaulters', 'How do I print receipt?'],
        'exams' => ['How do I enter marks?', 'How do I generate report cards?'],
        'library' => ['How do I issue a book?', 'How do I check overdue books?'],
        'transport' => ['How do I assign students?', 'How do I view routes?'],
        'hostel' => ['What are the room types?', 'How to allocate a room?'],
        'payroll' => ['How do I generate payslip?', 'What are deductions?'],
        'leave' => ['How many leaves remaining?', 'How do I apply for leave?'],
        'homework' => ['How to submit homework?', 'Check pending homework'],
        'notices' => ['Show latest notices', 'Who can post notices?'],
    ];
    return $map[$intent] ?? ['How to admit a student?', 'Mark attendance', 'Collect fees'];
}

// ─── HELPER: PERSONALITY GREETING ────────────────────────────────────────
function getPersonalityGreeting(string $personality, string $language): string
{
    $greetings = [
        'friendly' => ['en' => 'Hello! 👋 How can I help you today?', 'hi' => 'नमस्ते! 👋 मैं आपकी कैसे मदद कर सकता हूँ?', 'as' => 'নমস্কাৰ! 👋 মই আপোনাক কেনেকৈ সহায় কৰিব পাৰোঁ?'],
        'formal' => ['en' => 'Good day. How may I assist you with the School ERP system?', 'hi' => 'नमस्कार। मैं ERP प्रणाली के बारे में कैसे सहायता कर सकता हूँ?', 'as' => 'শুভদিন। মই ERP প্ৰণালীৰ বিষয়ে কেনেকৈ সহায় কৰিব পাৰোঁ?'],
        'funny' => ['en' => '🤖 School bot online! What chaos can I help you sort out today?', 'hi' => '🤖 स्कूल बॉट तैयार है! बताइए क्या गड़बड़ है?', 'as' => '🤖 স্কুল বট সক্ৰিয়! কি সমস্যা আছে আজি?'],
    ];
    return $greetings[$personality][$language] ?? $greetings['friendly']['en'];
}

// ═══════════════════════════════════════════════════════════════════════════
// SHORTCUT COMMAND HANDLER (matches chatbotEngine.js handleShortcut())
// ═══════════════════════════════════════════════════════════════════════════
if ($message[0] === '/') {
    $cmd = mb_strtolower(trim($message), 'UTF-8');

    if ($cmd === '/help') {
        $intent = 'help_shortcut';
        $help = [
            'en' => "🤖 **Chatbot Commands**\n\n**Shortcuts:**\n• /help — Show this help\n• /start — Restart with greeting\n• /clear — Clear chat history\n• /lang — Switch language (EN→HI→AS)\n\n**Topic Shortcuts:**\n• /admit — Admission help\n• /attendance — Attendance module\n• /fee — Fee collection\n• /exam — Exam scheduling\n• /library — Library module\n• /hostel — Hostel module\n• /transport — Transport module\n\n**Or just ask naturally!** Try:\n• \"How do I admit a student?\"\n• \"Show pending fees\"\n• \"Today's attendance\"",
            'hi' => "🤖 **चैटबॉट कमांड**\n\n**शॉर्टकट:**\n• /help — यह सहायता\n• /start — अभिवादन के साथ पुनरारंभ\n• /clear — इतिहास साफ\n• /lang — भाषा बदलें\n\n**विषय शॉर्टकट:**\n• /admit — प्रवेश सहायता\n• /attendance — उपस्थिति\n• /fee — फीस\n• /exam — परीक्षा\n• /library — पुस्तकालय\n• /hostel — छात्रावास\n• /transport — परिवहन",
            'as' => "🤖 **চৈটবট কমান্ড**\n\n**শ্বৰ্টকাট:**\n• /help — এই সহায়\n• /start — অভিনন্দনেৰে পুনৰ আৰম্ভ\n• /clear — ইতিহাস মচক\n• /lang — ভাষা সলনি কৰক",
        ];
        $reply = $help[$language] ?? $help['en'];
    } elseif ($cmd === '/start') {
        $intent = 'greeting';
        $reply = getPersonalityGreeting($personality, $language);
    } elseif ($cmd === '/clear') {
        $intent = 'clear';
        $clearMsg = ['en' => '🗑️ Conversation cleared. How can I help you?', 'hi' => '🗑️ वार्तालाप साफ़। मैं कैसे मदद करूं?', 'as' => '🗑️ কথোপকথন মচা হ\'ল। কেনেকৈ সহায় কৰিব পাৰোঁ?'];
        $reply = $clearMsg[$language] ?? $clearMsg['en'];
    } elseif ($cmd === '/lang') {
        $cycle = ['en' => 'hi', 'hi' => 'as', 'as' => 'en'];
        $next = $cycle[$language] ?? 'en';
        $labels = ['en' => 'English', 'hi' => 'Hindi', 'as' => 'Assamese'];
        $intent = 'lang_switch';
        $reply = "🌐 Language switched to **{$labels[$next]}**. Send your next message in that language.";
        json_response([
            'reply' => $reply,
            'intent' => $intent,
            'language' => $next,        // signal to JS to update its language state
            'newLanguage' => $next,
            'suggestions' => ['Hello', 'Help', 'Show dashboard'],
            'responseTime' => round((microtime(true) - $startTime) * 1000, 2),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        exit;
    } else {
        // Map /topic shortcuts to corresponding messages to reprocess
        $shortcutMap = [
            '/admit' => 'admission',
            '/attendance' => 'attendance',
            '/fee' => 'fees',
            '/exam' => 'exam',
            '/library' => 'library',
            '/hostel' => 'hostel',
            '/transport' => 'transport',
        ];
        if (isset($shortcutMap[$cmd])) {
            // Override message and run intent detection
            $msg = $shortcutMap[$cmd];
            $result = chatbot_detect_and_respond($msg, $language, $role, $personality, $userId, $startTime);
            json_response($result);
            exit;
        }
        $intent = 'unknown_shortcut';
        $reply = "❓ Unknown command. Type **/help** for available commands.";
    }

    // If shortcut produced a reply, send response immediately
    if ($reply) {
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        chatbot_update_context((string) $userId, $intent, $message);
        chatbot_add_assistant_message((string) $userId, $reply);
        $suggestions = getSuggestionsForIntent($intent, $role);
        try {
            if (db_table_exists('chatbot_logs')) {
                $sessionId = session_id() ?: bin2hex(random_bytes(16));
                db_query(
                    "INSERT INTO chatbot_logs (user_id, user_role, message, language, intent, response, response_time, session_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$userId, $role, $message, $language, $intent, $reply, $responseTime, $sessionId]
                );
            }
        } catch (Exception $e) {
            error_log("Chatbot logging failed: " . $e->getMessage());
        }
        json_response([
            'reply' => $reply,
            'intent' => $intent,
            'language' => $language,
            'suggestions' => $suggestions,
            'responseTime' => $responseTime,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// INTENT DETECTION — 51+ INTENTS (fixed priority order)
// All regex use /u modifier for UTF-8 safety; mb_strtolower for case-fold
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Helper: detect intent and send response (replaces goto)
 * Control flows naturally to the send_response section below.
 */
// (no function needed — code flow continues linearly below)
// ═══════════════════════════════════════════════════════════════════════════

// 1. GREETING
try {
if (preg_match('/\b(hi|hello|hey|good morning|good evening|howdy|namaste|नमस्ते|হ্যালো)\b/u', $msg)) {
    $intent = 'greeting';
    $reply = getPersonalityGreeting($personality, $language);
}

// 2. HELP
elseif (preg_match('/\b(help|what can you do|commands|options|capabilities)\b/u', $msg)) {
    $intent = 'help';
    $replies = [
        'en' => "🤖 **I can help with:**\n• Student info & count\n• Fee status & collection\n• Today's attendance\n• Staff information\n• Library status\n• Exams & results\n• Complaints summary\n• Hostel & Transport\n• Leave applications\n• Payroll info\n• Canteen menu\n• Homework & notices\n• School policies\n\nType **/help** for shortcuts! 😊",
        'hi' => "🤖 **मैं इनमें मदद कर सकता हूँ:**\n• छात्र जानकारी\n• फीस स्थिति\n• उपस्थिति\n• स्टाफ जानकारी\n• लाइब्रेरी\n• परीक्षा\n• शिकायतें\n• हॉस्टल और ट्रांसपोर्ट\n• छुट्टी\n• पेरोल\n• कैंटीन\n• होमवर्क और नोटिस\n• स्कूल नीतियां",
        'as' => "🤖 **মই সহায় কৰিব পাৰোঁ:**\n• ছাত্ৰৰ তথ্য\n• মাছুল অৱস্থা\n• উপস্থিতি\n• কৰ্মচাৰী তথ্য\n• লাইব্ৰেৰী\n• পৰীক্ষা\n• অভিযোগ\n• হোষ্টেল আৰু পৰিবহণ\n• ছুটি\n• পেৰ'ল\n• কেন্টিন\n• গৃহকাৰ্য আৰু নোটিছ\n• বিদ্যালয় নীতি",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 3. DASHBOARD
elseif (preg_match('/\b(dashboard|overview|summary|stats)\b/u', $msg)) {
    $intent = 'dashboard';
    $students = db_count("SELECT COUNT(*) FROM students WHERE is_active = 1");
    $staff = db_count("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role != 'student'");
    $classes = db_count("SELECT COUNT(*) FROM classes");
    $replies = [
        'en' => "📊 **Dashboard Summary**\n• Students: {$students}\n• Staff: {$staff}\n• Classes: {$classes}\n\nVisit the Dashboard page for detailed charts and trends.",
        'hi' => "📊 **डैशबोर्ड सारांश**\n• छात्र: {$students}\n• स्टाफ: {$staff}\n• कक्षाएँ: {$classes}",
        'as' => "📊 **ডেশ্বব'ৰ্ড সাৰাংশ**\n• ছাত্ৰ: {$students}\n• কৰ্মচাৰী: {$staff}\n• শ্ৰেণী: {$classes}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 4. STUDENT COUNT
elseif (preg_match('/\b(how many|total|count)\s*(students?|pupils)\b/u', $msg)) {
    $intent = 'student_count';
    $count = db_count("SELECT COUNT(*) FROM students WHERE is_active = 1");
    $replies = [
        'en' => "📊 There are currently **{$count}** active students enrolled in the school.",
        'hi' => "📊 वर्तमान में स्कूल में **{$count}** सक्रिय छात्र नामांकित हैं।",
        'as' => "📊 বৰ্তমান বিদ্যালয়ত **{$count}** জন সক্ৰিয় ছাত্ৰ নামভুক্ত কৰিছে।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 5. STUDENT LOOKUP (role-based access control)
elseif (preg_match('/\b(student.*named?|find student|search student|who is|tell me about)\b/u', $msg)) {
    $intent = 'student_lookup';
    $name = preg_replace('/.*(?:named?|student|who is|tell me about)\s+/iu', '', $message);

    // Only teachers, admins, and parents of the student can look up students
    if (!in_array($role, ['superadmin', 'admin', 'teacher', 'parent', 'student'])) {
        $replies = [
            'en' => "🔒 You don't have permission to look up student details. Contact an administrator.",
            'hi' => "🔒 आपको छात्र विवरण देखने की अनुमति नहीं है। व्यवस्थापक से संपर्क करें।",
            'as' => "🔒 আপোনাৰ ছাত্ৰৰ তথ্য চোৱাৰ অনুমতি নাই। প্ৰশাসকৰ সৈতে যোগাযোগ কৰক।",
        ];
        $reply = $replies[$language] ?? $replies['en'];
    } else {
        if ($role === 'parent') {
            $student = db_fetch("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.name LIKE ? AND s.is_active = 1 AND s.parent_user_id = ? LIMIT 1", ["%$name%", $userId]);
        } elseif ($role === 'student') {
            // Student can only look up themselves
            $student = db_fetch("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.name LIKE ? AND s.is_active = 1 AND s.user_id = ? LIMIT 1", ["%$name%", $userId]);
        } else {
            $student = db_fetch("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.name LIKE ? AND s.is_active = 1 LIMIT 1", ["%$name%"]);
        }
        
        if ($student) {
            $isOwnProfile = ($role === 'student' && $student['user_id'] == $userId) || ($role === 'parent' && $student['parent_user_id'] == $userId);
            $showContact = in_array($role, ['superadmin', 'admin', 'teacher']) || $isOwnProfile;
            
            $replies = [
                'en' => "👨‍🎓 **{$student['name']}**\n• Class: {$student['class_name']}\n• Admission No: {$student['admission_no']}" . ($showContact ? "\n• Parent: {$student['parent_name']}\n• Phone: {$student['phone']}" : ""),
                'hi' => "👨‍🎓 **{$student['name']}**\n• कक्षा: {$student['class_name']}\n• प्रवेश क्रमांक: {$student['admission_no']}" . ($showContact ? "\n• अभिभावक: {$student['parent_name']}\n• फ़ोन: {$student['phone']}" : ""),
                'as' => "👨‍🎓 **{$student['name']}**\n• શ্ৰেণী: {$student['class_name']}\n• প্ৰৱেশ নং: {$student['admission_no']}" . ($showContact ? "\n• সংৰক্ষক: {$student['parent_name']}\n• ফোন: {$student['phone']}" : ""),
            ];
        } else {
            $replies = [
                'en' => "❌ I couldn't find a student named '$name'. " . ($role === 'parent' ? "You can only search for your own linked children." : "Please check the spelling."),
                'hi' => "❌ मुझे '$name' नाम का छात्र नहीं मिला। " . ($role === 'parent' ? "आप केवल अपने बच्चों को खोज सकते हैं।" : "कृपया वर्तनी जांचें।"),
                'as' => "❌ মই '$name' নামৰ ছাত্ৰ বিচাৰি নাপালোঁ। " . ($role === 'parent' ? "আপুনি কেৱল আপোনাৰ লগত যুক্ত সন্তান বিচাৰিব পাৰিব।" : "অনুগ্ৰহ কৰি বানান পৰীক্ষা কৰক।"),
            ];
        }
        $reply = $replies[$language] ?? $replies['en'];
    }
}

// 6. WALLET BALANCE
elseif (preg_match('/\b(wallet|balance|recharge)\b/u', $msg)) {
    $intent = 'wallet';
    $replies = [
        'en' => "💳 **Wallet Info**\n• Recharge at canteen counter in multiples of ₹100\n• Min: ₹100, Max: ₹2000\n• Lost card replacement: ₹50\n\nVisit Canteen module for your balance.",
        'hi' => "💳 **वॉलेट जानकारी**\n• ₹100 के गुणकों में रिचार्ज करें\n• न्यूनतम: ₹100, अधिकतम: ₹2000",
        'as' => "💳 **ৱালেট তথ্য**\n• ₹100 গুণকত ৰিচাৰ্জ কৰক\n• ন্যূনতম: ₹100, সৰ্বাধিক: ₹2000",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 7. FEE PENDING (role-based)
elseif (preg_match('/\b(fee.*pending|pending.*fee|due|unpaid|defaulter)\b/u', $msg)) {
    $intent = 'fee_pending';
    if (in_array($role, ['student', 'parent'])) {
        if ($role === 'student') {
            $student = db_fetch("SELECT id FROM students WHERE user_id = ?", [$userId]);
            $studentId = $student['id'] ?? null;
        } else {
            $students = db_fetchAll("SELECT id FROM students WHERE parent_user_id = ?", [$userId]);
            $studentId = $students ? array_column($students, 'id') : null;
        }
        if ($studentId) {
            $placeholder = is_array($studentId) ? implode(',', array_fill(0, count($studentId), '?')) : '?';
            $params = is_array($studentId) ? $studentId : [$studentId];
            $count = db_count("SELECT COUNT(*) FROM fees WHERE balance_amount > 0 AND student_id IN ($placeholder)", $params);
            $amount = db_fetch("SELECT COALESCE(SUM(balance_amount),0) as total FROM fees WHERE balance_amount > 0 AND student_id IN ($placeholder)", $params)['total'];
        } else {
            $count = 0;
            $amount = 0;
        }
    } else {
        $count = db_count("SELECT COUNT(*) FROM fees WHERE balance_amount > 0");
        $amount = db_fetch("SELECT COALESCE(SUM(balance_amount),0) as total FROM fees WHERE balance_amount > 0")['total'];
    }
    $replies = [
        'en' => "💰 **Pending Fees**\n• {$count} students have pending dues\n• Total pending: ₹" . number_format($amount) . "\n\nVisit the Fee module for details.",
        'hi' => "💰 **बकाया फीस**\n• {$count} छात्रों के बकाया हैं\n• कुल बकाया: ₹" . number_format($amount),
        'as' => "💰 **বকেয়া মাছুল**\n• {$count} জন ছাত্ৰৰ বকেয়া আছে\n• মুঠ বকেয়া: ₹" . number_format($amount),
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 8. FEE COLLECTION
elseif (preg_match('/\b(fee.*collect|collection.*fee|fee.*today|fee.*month)\b/u', $msg)) {
    $intent = 'fee_collection';
    $today = db_fetch("SELECT COALESCE(SUM(amount_paid),0) as total FROM fees WHERE DATE(paid_date) = CURDATE()")['total'];
    $month = db_fetch("SELECT COALESCE(SUM(amount_paid),0) as total FROM fees WHERE MONTH(paid_date)=MONTH(NOW()) AND YEAR(paid_date)=YEAR(NOW())")['total'];
    $replies = [
        'en' => "💰 **Fee Collection**\n• Collected Today: ₹" . number_format($today) . "\n• Collected This Month: ₹" . number_format($month),
        'hi' => "💰 **फीस संग्रह**\n• आज संग्रहित: ₹" . number_format($today) . "\n• इस माह संग्रहित: ₹" . number_format($month),
        'as' => "💰 **মাছুল সংগ্ৰহ**\n• আজি সংগ্ৰহ: ₹" . number_format($today) . "\n• এই মাহত সংগ্ৰহ: ₹" . number_format($month),
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 9. ATTENDANCE TODAY
elseif (preg_match('/\b(attendance|present|absent)\s*today\b/u', $msg)) {
    $intent = 'attendance_today';
    $present = 0;
    $absent = 0;
    
    if (db_table_exists('attendance')) {
        $present = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'");
        $absent = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'absent'");
    } elseif (db_table_exists('student_attendance')) {
        $present = db_count("SELECT COUNT(*) FROM student_attendance WHERE date = CURDATE() AND status = 'present'");
        $absent = db_count("SELECT COUNT(*) FROM student_attendance WHERE date = CURDATE() AND status = 'absent'");
    } elseif (db_table_exists('daily_attendance')) {
        $present = db_count("SELECT COUNT(*) FROM daily_attendance WHERE date = CURDATE() AND status = 'present'");
        $absent = db_count("SELECT COUNT(*) FROM daily_attendance WHERE date = CURDATE() AND status = 'absent'");
    }

    $replies = [
        'en' => "✅ **Today's Attendance**\n• Present: {$present}\n• Absent: {$absent}\n\nFor detailed attendance, go to the Attendance module.",
        'hi' => "✅ **आज की उपस्थिति**\n• उपस्थित: {$present}\n• अनुपस्थित: {$absent}",
        'as' => "✅ **আজিৰ উপস্থিতি**\n• উপস্থিত: {$present}\n• অনুপস্থিত: {$absent}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 10. STAFF COUNT
elseif (preg_match('/\b(how many|total|count)\s*(staff|teachers?|employees?)\b/u', $msg)) {
    $intent = 'staff_count';
    $teachers = db_count("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1");
    $total = db_count("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role != 'student'");
    $replies = [
        'en' => "👔 **Staff Summary**\n• Teachers: {$teachers}\n• Total Staff: {$total}",
        'hi' => "👔 **स्टाफ सारांश**\n• शिक्षक: {$teachers}\n• कुल स्टाफ: {$total}",
        'as' => "👔 **কৰ্মচাৰী সাৰাংশ**\n• শিক্ষক: {$teachers}\n• মুঠ কৰ্মচাৰী: {$total}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 11. LIBRARY STATUS
elseif (preg_match('/\b(library|books?|borrow|return|overdue)\b/u', $msg)) {
    $intent = 'library';
    $total = db_count("SELECT COUNT(*) FROM library_books");
    $issued = db_count("SELECT COUNT(*) FROM library_issues WHERE is_returned = 0");
    $overdue = db_count("SELECT COUNT(*) FROM library_issues WHERE is_returned = 0 AND due_date < CURDATE()");
    $replies = [
        'en' => "📚 **Library Status**\n• Total Books: {$total}\n• Currently Issued: {$issued}\n• Overdue: {$overdue}\n\nFine: ₹2/day for overdue books.",
        'hi' => "📚 **लाइब्रेरी स्थिति**\n• कुल किताबें: {$total}\n• जारी: {$issued}\n• ओवरड्यू: {$overdue}\n\nजुर्माना: ₹2/दिन",
        'as' => "📚 **লাইব্ৰেৰী অৱস্থা**\n• মুঠ কিতাপ: {$total}\n• ইস্যু হোৱা: {$issued}\n• অৱকাশ: {$overdue}\n\nজৰিমনা: ₹2/দিন",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 12. COMPLAINTS (role-based)
elseif (preg_match('/\b(complaint|grievance)\b/u', $msg)) {
    $intent = 'complaints';
    if (in_array($role, ['student', 'parent'])) {
        $pending = db_count("SELECT COUNT(*) FROM complaints WHERE (user_id = ? OR student_id IN (SELECT id FROM students WHERE user_id = ? OR parent_user_id = ?)) AND status = 'pending'", [$userId, $userId, $userId]);
        $resolved = db_count("SELECT COUNT(*) FROM complaints WHERE (user_id = ? OR student_id IN (SELECT id FROM students WHERE user_id = ? OR parent_user_id = ?)) AND status = 'resolved'", [$userId, $userId, $userId]);
    } elseif ($role === 'teacher') {
        $pending = db_count("SELECT COUNT(*) FROM complaints WHERE assigned_to = ? AND status = 'pending'", [$userId]);
        $resolved = db_count("SELECT COUNT(*) FROM complaints WHERE assigned_to = ? AND status = 'resolved'", [$userId]);
    } else {
        $pending = db_count("SELECT COUNT(*) FROM complaints WHERE status = 'pending'");
        $resolved = db_count("SELECT COUNT(*) FROM complaints WHERE status = 'resolved'");
    }
    $replies = [
        'en' => "📣 **Complaints Summary**\n• Pending: {$pending}\n• Resolved: {$resolved}\n\nVisit the Complaints module to take action.",
        'hi' => "📣 **शिकायत सारांश**\n• लंबित: {$pending}\n• हल: {$resolved}",
        'as' => "📣 **অভিযোগ সাৰাংশ**\n• লম্বিত: {$pending}\n• সমাধান: {$resolved}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 13. CLASSES
elseif (preg_match('/\b(class|section|grade)\b/u', $msg)) {
    $intent = 'classes';
    $classes = db_fetchAll("SELECT c.name, COUNT(s.id) as student_count FROM classes c LEFT JOIN students s ON s.class_id = c.id AND s.is_active = 1 GROUP BY c.id, c.name ORDER BY c.name LIMIT 10");
    if ($classes) {
        $list = array_map(fn($c) => "• {$c['name']}: {$c['student_count']} students", $classes);
        $listHi = array_map(fn($c) => "• {$c['name']}: {$c['student_count']} छात्र", $classes);
        $listAs = array_map(fn($c) => "• {$c['name']}: {$c['student_count']} ছাত্ৰ", $classes);
        $replies = [
            'en' => "🏫 **Classes & Strength**\n" . implode("\n", $list),
            'hi' => "🏫 **कक्षाएँ और क्षमता**\n" . implode("\n", $listHi),
            'as' => "🏫 **শ্ৰেণী আৰু শক্তি**\n" . implode("\n", $listAs),
        ];
        $reply = $replies[$language] ?? $replies['en'];
    }
}

// 14. HOSTEL
elseif (preg_match('/\b(hostel|room|accommodation)\b/u', $msg)) {
    $intent = 'hostel';
    $total = db_count("SELECT COUNT(*) FROM hostel_rooms WHERE is_active=1");
    $occupied = db_count("SELECT COUNT(*) FROM hostel_allocations WHERE is_active=1");
    $replies = [
        'en' => "🏠 **Hostel Status**\n• Total Rooms: {$total}\n• Occupied Beds: {$occupied}",
        'hi' => "🏠 **हॉस्टल स्थिति**\n• कुल कमरे: {$total}\n• भरे बेड: {$occupied}",
        'as' => "🏠 **হোষ্টেল অৱস্থা**\n• মুঠ কোঠা: {$total}\n• ভৰ্তি বিছনা: {$occupied}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 15. TRANSPORT
elseif (preg_match('/\b(transport|bus|route|vehicle)\b/u', $msg)) {
    $intent = 'transport';
    $buses = db_count("SELECT COUNT(*) FROM transport_vehicles WHERE is_active = 1");
    $routes = db_count("SELECT COUNT(*) FROM bus_routes");
    $replies = [
        'en' => "🚌 **Transport Summary**\n• Total Buses: {$buses}\n• Active Routes: {$routes}\n\nVisit Transport module for route details.",
        'hi' => "🚌 **ट्रांसपोर्ट सारांश**\n• कुल बसें: {$buses}\n• सक्रिय रूट: {$routes}",
        'as' => "🚌 **পৰিবহণ সাৰাংশ**\n• মুঠ বাছ: {$buses}\n• সক্ৰিয় ৰুট: {$routes}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 16. EXAMS
elseif (preg_match('/\b(exam|result|marks|test)\b/u', $msg)) {
    $intent = 'exams';
    $upcoming = db_count("SELECT COUNT(*) FROM exams WHERE exam_date >= CURDATE()");
    $past = db_count("SELECT COUNT(*) FROM exams WHERE exam_date < CURDATE()");
    $replies = [
        'en' => "📝 **Exams Summary**\n• Upcoming: {$upcoming}\n• Completed: {$past}\n\nVisit Exams module for full details.",
        'hi' => "📝 **परीक्षा सारांश**\n• आगामी: {$upcoming}\n• पूर्ण: {$past}",
        'as' => "📝 **পৰীক্ষা সাৰাংশ**\n• আগত: {$upcoming}\n• সম্পন্ন: {$past}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 17. LEAVE (role-based)
elseif (preg_match('/\b(leave|absent request|leave application)\b/u', $msg)) {
    $intent = 'leave';
    if (in_array($role, ['student', 'parent'])) {
        $replies = [
            'en' => "📅 **Leave Applications**\n• Leave requests are for staff only.\n\nStudents should contact the school office for absence notifications.",
            'hi' => "📅 **छुट्टी आवेदन**\n• छुट्टी अनुरोध केवल कर्मचारियों के लिए हैं।",
            'as' => "📅 **ছুটিৰ আবেদন**\n• ছুটিৰ অনুৰোধ কেৱল কৰ্মচাৰীসকলৰ বাবে।",
        ];
    } else {
        $pending = in_array($role, ['admin', 'superadmin', 'hr'])
            ? db_count("SELECT COUNT(*) FROM leave_applications WHERE status = 'pending'")
            : db_count("SELECT COUNT(*) FROM leave_applications WHERE applicant_id = ? AND status = 'pending'", [$userId]);
        $replies = [
            'en' => "📅 **Leave Applications**\n• Pending Approvals: {$pending}\n\nVisit the Leave module to approve or reject.",
            'hi' => "📅 **छुट्टी आवेदन**\n• लंबित अनुमोदन: {$pending}",
            'as' => "📅 **ছুটিৰ আবেদন**\n• লম্বিত অনুমোদন: {$pending}",
        ];
    }
    $reply = $replies[$language] ?? $replies['en'];
}

// 18. HOMEWORK
elseif (preg_match('/\b(homework|assignment)\b/u', $msg)) {
    $intent = 'homework';
    // Fallback to assignments table if homework doesn't exist
    if (db_table_exists('homework')) {
        $pending = db_count("SELECT COUNT(*) FROM homework WHERE due_date >= CURDATE()");
    } elseif (db_table_exists('assignments')) {
        $pending = db_count("SELECT COUNT(*) FROM assignments WHERE due_date >= CURDATE()");
    } else {
        $pending = 0;
    }
    
    $replies = [
        'en' => "📚 **Homework Status**\n• Active Assignments: {$pending}\n\nSubmit by due date. Late submissions may receive reduced marks.",
        'hi' => "📚 **होमवर्क स्थिति**\n• सक्रिय असाइनमेंट: {$pending}",
        'as' => "📚 **গৃহকাৰ্য অৱস্থা**\n• সক্ৰিয় এসাইনমেণ্ট: {$pending}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 19. NOTICES
elseif (preg_match('/\b(notice|announcement|circular)\b/u', $msg)) {
    $intent = 'notices';
    $recent = db_fetchAll("SELECT title, priority, created_at FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
    if ($recent) {
        $list = array_map(fn($n) => "• {$n['title']} ({$n['priority']})", $recent);
        $replies = [
            'en' => "📢 **Recent Notices**\n" . implode("\n", $list) . "\n\nVisit Notices module for full details.",
            'hi' => "📢 **हाल के नोटिस**\n" . implode("\n", $list),
            'as' => "📢 **শেহতীয়া নোটিছ**\n" . implode("\n", $list),
        ];
    } else {
        $replies = [
            'en' => "📢 No active notices at the moment.",
            'hi' => "📢 वर्तमान में कोई सक्रिय नोटिस नहीं है।",
            'as' => "📢 বৰ্তমান কোনো সক্ৰিয় নোটিছ নাই।",
        ];
    }
    $reply = $replies[$language] ?? $replies['en'];
}

// 20. PAYROLL (role-based)
elseif (preg_match('/\b(payroll|salary|wages)\b/u', $msg)) {
    $intent = 'payroll';
    if (in_array($role, ['student', 'parent'])) {
        $replies = [
            'en' => "💼 **Payroll Information**\n• Payroll details are only available for staff members.\n\nPlease contact the accounts office for fee-related queries.",
            'hi' => "💼 **पेरोल जानकारी**\n• पेरोल विवरण केवल कर्मचारियों के लिए उपलब्ध हैं।",
            'as' => "💼 **পেৰ'ল তথ্য**\n• পেৰ'ল বিৱৰণ কেৱল কৰ্মচাৰীসকলৰ বাবে উপলব্ধ।",
        ];
    } else {
        $total = db_fetch("SELECT COALESCE(SUM(net_pay),0) as total FROM payroll WHERE month = MONTH(CURDATE()) AND year = YEAR(CURDATE())")['total'];
        $count = db_count("SELECT COUNT(*) FROM payroll WHERE month = MONTH(CURDATE()) AND year = YEAR(CURDATE())");
        $replies = [
            'en' => "💼 **Payroll Summary**\n• Processed This Month: {$count}\n• Total Amount: ₹" . number_format($total),
            'hi' => "💼 **पेरोल सारांश**\n• इस माह प्रक्रिया: {$count}\n• कुल राशि: ₹" . number_format($total),
            'as' => "💼 **পেৰ'ল সাৰাংশ**\n• এই মাহত প্ৰক্ৰিয়া: {$count}\n• মুঠ পৰিমাণ: ₹" . number_format($total),
        ];
    }
    $reply = $replies[$language] ?? $replies['en'];
}

// 21. CANTEEN
elseif (preg_match('/\b(canteen|menu|food|snack)\b/u', $msg)) {
    $intent = 'canteen';
    $items = db_count("SELECT COUNT(*) FROM canteen_items WHERE is_available = 1");
    $replies = [
        'en' => "🍔 **Canteen Status**\n• Available Items: {$items}\n\nVisit the Canteen module for menu and pricing. Payment via cash or RFID wallet.",
        'hi' => "🍔 **कैंटीन स्थिति**\n• उपलब्ध आइटम: {$items}",
        'as' => "🍔 **কেন্টিন অৱস্থা**\n• উপলব্ধ বস্তু: {$items}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 22. SCHOOL HOURS
elseif (preg_match('/\b(school hours?|timings?|what time|open|close)\b/u', $msg)) {
    $intent = 'school_hours';
    $replies = [
        'en' => "🕐 **School Hours**\n• Monday-Friday: 8:00 AM - 3:00 PM\n• Saturday: 8:00 AM - 12:00 PM\n• Assembly: 7:50 AM\n• Lunch: 12:00-12:30 PM",
        'hi' => "🕐 **स्कूल समय**\n• सोमवार-शुक्रवार: 8:00 AM - 3:00 PM\n• शनिवार: 8:00 AM - 12:00 PM",
        'as' => "🕐 **বিদ্যালয়ৰ সময়**\n• সোম-শুকুৰ: 8:00 AM - 3:00 PM\n• শনি: 8:00 AM - 12:00 PM",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 23. HOLIDAYS
elseif (preg_match('/\b(holiday|vacation|break|closed)\b/u', $msg)) {
    $intent = 'holidays';
    $replies = [
        'en' => "🏖️ **School Holidays**\n• Summer: May-June\n• Diwali: October\n• Winter: December-January\n\nFull calendar published at start of academic year.",
        'hi' => "🏖️ **स्कूल छुट्टियां**\n• गर्मी: मई-जून\n• दिवाली: अक्टूबर\n• सर्दी: दिसंबर-जनवरी",
        'as' => "🏖️ **বিদ্যালয়ৰ ছুটি**\n• গৰম: মে-জুন\n• দীপাৱলী: অক্টোবৰ\n• ঠাণ্ডা: ডিচেম্বৰ-জানুৱাৰী",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 24. UNIFORM
elseif (preg_match('/\b(uniform|dress code|dresscode)\b/u', $msg)) {
    $intent = 'uniform';
    $replies = [
        'en' => "👔 **Uniform Policy**\n• Summer: Light blue shirt/salwar, dark blue pants/skirt\n• Winter: Grey sweater with regular uniform\n• Sports uniform on PT days\n• Black shoes mandatory\n• Name badge must be worn",
        'hi' => "👔 **वर्दी नीति**\n• गर्मी: हल्का नीला शर्ट/सलवार, गहरा नीला पैंट/स्कर्ट\n• सर्दी: ग्रे स्वेटर",
        'as' => "👔 **বৰ্দ্ৰ নীতি**\n• গৰম: পাতল নীলা শাৰ্ট/চলৱাৰ, গাঢ় নীলা পেন্ট/স্কাৰ্ট",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 25. ADMISSION
elseif (preg_match('/\b(admission|admit|enroll|new student)\b/u', $msg)) {
    $intent = 'admission';
    $replies = [
        'en' => "📝 **Admission Process**\n1. Fill online admission form\n2. Submit documents (Birth cert, Aadhaar, photos)\n3. Take entrance exam\n4. Interview (if selected)\n5. Admission confirmation\n\nEntrance exam: English, Maths, GK",
        'hi' => "📝 **प्रवेश प्रक्रिया**\n1. ऑनलाइन फॉर्म भरें\n2. दस्तावेज़ जमा करें\n3. प्रवेश परीक्षा दें\n4. साक्षात्कार\n5. प्रवेश पुष्टि",
        'as' => "📝 **ভৰ্তি প্ৰক্ৰিয়া**\n1. অনলাইন ফৰ্ম পূৰণ কৰক\n2. নথী প্ৰদান কৰক\n3. প্ৰৱেশ পৰীক্ষা দিয়ক\n4. সাক্ষাৎকাৰ\n5. ভৰ্তি নিশ্চিতকৰণ",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 26. TRANSFER CERTIFICATE
elseif (preg_match('/\b(transfer certificate|tc|withdraw|leave school)\b/u', $msg)) {
    $intent = 'tc';
    $replies = [
        'en' => "📄 **Transfer Certificate Process**\n• Requirements: No pending dues, library books returned\n• Processing time: 7-15 working days\n• TC fee: ₹500\n• Apply through school office\n• TC available as PDF download",
        'hi' => "📄 **स्थानांतरण प्रमाणपत्र प्रक्रिया**\n• कोई बकाया नहीं, किताबें वापस\n• प्रक्रिया समय: 7-15 कार्य दिवस\n• शुल्क: ₹500",
        'as' => "📄 **স্থানান্তৰ প্ৰমাণপত্ৰ প্ৰক্ৰিয়া**\n• কোনো বকেয়া নাই, কিতাপ ঘূৰাই দিয়ক\n• প্ৰক্ৰিয়া সময়: 7-15 কৰ্ম দিৱস",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 27. GRADING
elseif (preg_match('/\b(grading|grade|percentage|pass|fail)\b/u', $msg)) {
    $intent = 'grading';
    $replies = [
        'en' => "📊 **Grading System**\n• A+ (90-100%), A (80-89%), B+ (70-79%)\n• B (60-69%), C (50-59%), D (40-49%)\n• E (33-39%), F (Below 33%)\n• Minimum passing: 33%\n• Report cards available as PDF",
        'hi' => "📊 **ग्रेडिंग प्रणाली**\n• A+ (90-100%), A (80-89%), B+ (70-79%)\n• न्यूनतम पास: 33%",
        'as' => "📊 **গ্ৰেডিং প্ৰণালী**\n• A+ (90-100%), A (80-89%), B+ (70-79%)\n• ন্যূনতম পাছ: 33%",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 28. ANTI-BULLYING
elseif (preg_match('/\b(bully|bullying|harass)\b/u', $msg)) {
    $intent = 'anti_bullying';
    $replies = [
        'en' => "⚠️ **Anti-Bullying Policy**\nZero tolerance for bullying. Any form (physical, verbal, cyber) results in strict disciplinary action. Report to any teacher immediately. All complaints investigated within 48 hours. Anonymous reporting available.",
        'hi' => "⚠️ **एंटी-बुलिंग नीति**\nबुलिंग के लिए शून्य सहिष्णुता। किसी भी रूप में कड़ी अनुशासनात्मक कार्रवाई होगी।",
        'as' => "⚠️ **এন্টি-বুলিং নীতি**\nবুলিংৰ বাবে শূন্য সহনশীলতা। যিকোনো ৰূপত কঠোৰ শাস্তিমূলক ব্যৱস্থা।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 29. MOBILE POLICY
elseif (preg_match('/\b(mobile|phone|cell)\b/u', $msg)) {
    $intent = 'mobile_policy';
    $replies = [
        'en' => "📱 **Mobile Phone Policy**\nMobile phones NOT allowed during class hours. Must be switched off and stored in bags. Emergency calls through school office. Confiscated phones returned to parents only. Smartwatches prohibited during exams.",
        'hi' => "📱 **मोबाइल फोन नीति**\nकक्षा के दौरान मोबाइल फोन की अनुमति नहीं। बंद करके बैग में रखें।",
        'as' => "📱 **মোবাইল ফোন নীতি**\nশ্ৰেণীৰ সময়ত মোবাইল ফোনৰ অনুমতি নাই।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 30. EMERGENCY
elseif (preg_match('/\b(emergency|urgent|accident|hospital)\b/u', $msg)) {
    $intent = 'emergency';
    $replies = [
        'en' => "🚨 **Emergency Contacts**\n• Principal: School Office\n• Medical Room: Extension 101\n• Transport: Extension 102\n\nIn medical emergency, student taken to nearest hospital. Parents informed immediately.",
        'hi' => "🚨 **आपातकालीन संपर्क**\n• प्रधानाचार्य: स्कूल कार्यालय\n• चिकित्सा कक्ष: एक्सटेंशन 101",
        'as' => "🚨 **জৰুৰীকালীন যোগাযোগ**\n• অধ্যক্ষ: বিদ্যালয় কাৰ্যালয়\n• চিকিৎসা কোঠা: এক্সটেনশ্যন 101",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 31. PTM
elseif (preg_match('/\b(ptm|parent.?teacher|meeting)\b/u', $msg)) {
    $intent = 'ptm';
    $replies = [
        'en' => "👨‍👩‍👧 **Parent-Teacher Meeting**\nPTM held once per quarter (every 3 months). Individual meetings can be requested through school office. PTM schedule announced via Notice Board. Please prepare questions in advance.",
        'hi' => "👨‍👩‍👧 **अभिभावक-शिक्षक बैठक**\nPTM प्रति तिमाही आयोजित। व्यक्तिगत बैठक स्कूल कार्यालय से अनुरोध करें।",
        'as' => "👨‍👩‍👧 **অভিভাৱক-শিক্ষক সভা**\nPTM প্ৰতি তিনিমহীয়া অনুষ্ঠিত।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 32. SCHOLARSHIP
elseif (preg_match('/\b(scholarship|concession|discount|financial aid)\b/u', $msg)) {
    $intent = 'scholarship';
    $replies = [
        'en' => "🎓 **Scholarships & Concessions**\n• Merit: 90%+ in previous exams\n• EWS: Income certificate required\n• Sibling: 10% (2nd child), 15% (3rd)\n\nApply to Accounts office with documents.",
        'hi' => "🎓 **छात्रवृत्ति और रियायतें**\n• मेरिट: 90%+\n• आर्थिक: आय प्रमाणपत्र\n• भाई-बहन: 10% (दूसरा)",
        'as' => "🎓 **বৃত্তি আৰু ৰেহাই**\n• মেৰিট: 90%+\n• আৰ্থিক: আয় প্ৰমাণপত্ৰ",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 33. LATE COMING
elseif (preg_match('/\b(late|tardy|delay)\b/u', $msg)) {
    $intent = 'late_coming';
    $replies = [
        'en' => "⏰ **Late Coming Policy**\nStudents arriving after 8:15 AM are marked late. Three lates = one absence. Repeated late coming results in parent notification and possible detention. Late slip required from office.",
        'hi' => "⏰ **देर से आने की नीति**\n8:15 AM के बाद आने वाले देर से चिह्नित। तीन देर = एक अनुपस्थिति।",
        'as' => "⏰ **পলমকৈ অহা নীতি**\n8:15 AM ৰ পিছত অহা ছাত্ৰ পলম চিহ্নিত। তিনি পলম = এটা অনুপস্থিতি।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 34. RE-EVALUATION
elseif (preg_match('/\b(re.?evaluat|recheck|review.*marks)\b/u', $msg)) {
    $intent = 'reevaluation';
    $replies = [
        'en' => "📝 **Re-evaluation Process**\n• Request within 3 days of result\n• Fee: ₹100 per subject\n• Conducted by different teacher\n• If marks increase by 10%+, fee refunded\n• Results are final",
        'hi' => "📝 **पुनर्मूल्यांकन प्रक्रिया**\n• परिणाम के 3 दिनों के भीतर अनुरोध\n• शुल्क: ₹100 प्रति विषय",
        'as' => "📝 **পুনৰ্মূল্যায়ন প্ৰক্ৰিয়া**\n• ফলাফলৰ 3 দিনৰ ভিতৰত অনুৰোধ\n• মাছুল: ₹100 প্ৰতি বিষয়",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 35. ATTENDANCE PERCENTAGE
elseif (preg_match('/\b(attendance.*percentage|percentage.*attendance|eligibility)\b/u', $msg)) {
    $intent = 'attendance_percentage';
    $replies = [
        'en' => "📊 **Attendance Percentage**\nFormula: (Present Days / Total Working Days) × 100\nMinimum 75% required for exam eligibility.\nBelow 75%: Medical certificate required.",
        'hi' => "📊 **उपस्थिति प्रतिशत**\nसूत्र: (उपस्थित दिन / कुल कार्य दिवस) × 100\nन्यूनतम 75% आवश्यक।",
        'as' => "📊 **উপস্থিতি শতকৰা**\nসূত্ৰ: (উপস্থিত দিন / মুঠ কৰ্ম দিন) × 100\nন্যূনতম 75% প্ৰয়োজন।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 36. FINE CALCULATION
elseif (preg_match('/\b(fine|penalty|late fee)\b/u', $msg)) {
    $intent = 'fine_calculation';
    $replies = [
        'en' => "💰 **Fine Calculation**\n• Library: ₹2/day for overdue books\n• Late Fee: ₹50/month past due date\n• Max library fine = book price\n• Fines must be paid before new borrowing",
        'hi' => "💰 **जुर्माना गणना**\n• लाइब्रेरी: ₹2/दिन\n• देर से फीस: ₹50/माह",
        'as' => "💰 **জৰিমনা গণনা**\n• লাইব্ৰেৰী: ₹2/দিন\n• পলম মাছুল: ₹50/মাহ",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 36.5 NEGATIVE FEEDBACK
elseif (preg_match('/\b(not helpful|useless|bad|wrong|incorrect|dislike|thumbs down|बकवास|ভুল)\b/u', $msg)) {
    $intent = 'negative_feedback';
    $replies = [
        'en' => "I'm sorry I wasn't helpful. I've logged your feedback so my developers can improve me!",
        'hi' => "मुझे खेद है कि मैं सहायक नहीं था। मैंने आपका फीडबैक दर्ज कर लिया है ताकि मेरे डेवलपर मुझे बेहतर बना सकें!",
        'as' => "মই দুঃখিত যে মই সহায়ক নাছিলো। মই আপোনাৰ প্ৰতিক্ৰিয়া লিপিৱদ্ধ কৰিছোঁ যাতে মোৰ বিকাশকসকলে মোক উন্নত কৰিব পাৰে!"
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// ─── 37. ENGLISH KNOWLEDGE BASE SEARCH ───────────────────────────────────
if (!$reply && mb_strlen($msg, 'UTF-8') > 4) {
    $matchedEntries = [];
    foreach ($knowledgeBaseEn as $entry) {
        $score = 0;
        foreach ($entry['tags'] as $tag) {
            if (mb_strpos($msg, mb_strtolower($tag, 'UTF-8'), 0, 'UTF-8') !== false)
                $score += 2;
        }
        if (mb_strpos(mb_strtolower($entry['title'], 'UTF-8'), $msg, 0, 'UTF-8') !== false)
            $score += 5;
        if (mb_strpos(mb_strtolower($entry['content'], 'UTF-8'), $msg, 0, 'UTF-8') !== false)
            $score += 1;
        if ($score >= 2)
            $matchedEntries[] = ['entry' => $entry, 'score' => $score];
    }
    if (!empty($matchedEntries)) {
        usort($matchedEntries, fn($a, $b) => $b['score'] - $a['score']);
        $best = $matchedEntries[0]['entry'];
        $intent = 'knowledge_base';
        $reply = "📖 **{$best['title']}**\n\n{$best['content']}";
    }
}

// ─── 38. HINDI EXTENDED KB (matches findHindiExtendedResponse) ───────────
if (!$reply && $language === 'hi') {
    $hiMatch = searchExtendedKB($knowledgeBaseHi, $msg);
    if ($hiMatch) {
        $intent = 'hindi_extended';
        $reply = "📖 **{$hiMatch['title']}**\n\n{$hiMatch['content']}";
    }
}

// ─── 39. ASSAMESE EXTENDED KB (matches findAssameseExtendedResponse) ──────
if (!$reply && $language === 'as') {
    $asMatch = searchExtendedKB($knowledgeBaseAs, $msg);
    if ($asMatch) {
        $intent = 'assamese_extended';
        $reply = "📖 **{$asMatch['title']}**\n\n{$asMatch['content']}";
    }
}

} catch (Exception $e) {
    error_log("Chatbot DB error: " . $e->getMessage());
    $errorReplies = [
        'en' => "I'm having trouble connecting to the school's database right now. Please try again later.",
        'hi' => "मुझे अभी स्कूल के डेटाबेस से जुड़ने में समस्या हो रही है। कृपया बाद में प्रयास करें।",
        'as' => "মই বৰ্তমান বিদ্যালয়ৰ ডাটাবেছৰ সৈতে সংযোগ কৰাত সমস্যা পাইছোঁ। অনুগ্ৰহ কৰি পিছত চেষ্টা কৰক।"
    ];
    $reply = $errorReplies[$language] ?? $errorReplies['en'];
    $intent = 'database_error';
}

// ─── 40. MULTI-INTENT "DID YOU MEAN?" (from nlpEngine.js detectMultipleIntents) ─
if (!$reply) {
    $multiIntents = chatbot_detect_multiple_intents($msg);
    if ($multiIntents && count($multiIntents) >= 2) {
        $intentLabels = [
            'attendance' => _cbl($language, ['en' => 'Attendance', 'hi' => 'उपस्थिति', 'as' => 'উপস্থিতি']),
            'fee' => _cbl($language, ['en' => 'Fees', 'hi' => 'फीस', 'as' => 'মাচুল']),
            'exam' => _cbl($language, ['en' => 'Exams', 'hi' => 'परीक्षा', 'as' => 'পৰীক্ষা']),
            'homework' => _cbl($language, ['en' => 'Homework', 'hi' => 'होमवर्क', 'as' => 'গৃহকাৰ্য']),
            'notice' => _cbl($language, ['en' => 'Notices', 'hi' => 'नोटिस', 'as' => 'নোটিছ']),
            'library' => _cbl($language, ['en' => 'Library', 'hi' => 'पुस्तकालय', 'as' => 'লাইব্ৰেৰী']),
            'transport' => _cbl($language, ['en' => 'Transport', 'hi' => 'ट्रांसपोर्ट', 'as' => 'পৰিবহণ']),
        ];
        $labels = array_map(fn($i) => '• ' . ($intentLabels[$i] ?? $i), $multiIntents);
        $list = implode("\n", $labels);
        $reply = _cbl($language, [
            'en' => "I noticed you asked about multiple topics:\n{$list}\n\nWhich one would you like me to answer first?",
            'hi' => "आपने कई विषयों के बारे में पूछा:\n{$list}\n\nआप पहले किसके बारे में जानना चाहते हैं?",
            'as' => "আপুনি কেইটামান বিষয়ে সোধিছে:\n{$list}\n\nআপুনি প্ৰথমে কোনটোৰ বিষয়ে জানিব বিচাৰে?",
        ]);
        $intent = 'multi_intent';
    }
}

// ─── 41. FOLLOW-UP CONTEXT (from nlpEngine.js handleFollowUp) ────────────────
if (!$reply && $intent === 'unknown') {
    $followUpReply = chatbot_handle_followup($ctx, $message, $language);
    if ($followUpReply) {
        $reply = $followUpReply;
        $intent = 'followup_context';
    }
}

// ─── 42. FINAL FALLBACK (role-based, from chatbot_engine.php) ────────────────
if (!$reply) {
    $intent = 'unknown';
    $fallback = chatbot_localized_fallback($language, $role);
    $reply = $fallback['message'];
}

// ═══════════════════════════════════════════════════════════════════════════
send_response:
// ═══════════════════════════════════════════════════════════════════════════

$responseTime = round((microtime(true) - $startTime) * 1000, 2);

// ─── UPDATE CONTEXT (from chatbot_engine.php) ──────────────────────────────
chatbot_update_context((string) $userId, $intent, $message);
chatbot_add_assistant_message((string) $userId, $reply);

// ─── ROLE-BASED SUGGESTIONS (if unknown) ──────────────────────────────────
if ($intent === 'unknown' || $intent === 'multi_intent') {
    $suggestions = chatbot_get_role_suggestions($role, $language);
} else {
    $suggestions = getSuggestionsForIntent($intent, $role);
}

// Log conversation
try {
    if (db_table_exists('chatbot_logs')) {
        $sessionId = session_id() ?: bin2hex(random_bytes(16));
        db_query(
            "INSERT INTO chatbot_logs (user_id, user_role, message, language, intent, response, response_time, session_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$userId, $role, $message, $language, $intent, $reply, $responseTime, $sessionId]
        );
    }
} catch (Exception $e) {
    error_log("Chatbot logging failed: " . $e->getMessage());
}

json_response([
    'reply' => $reply,
    'intent' => $intent,
    'language' => $language,
    'suggestions' => $suggestions,
    'responseTime' => $responseTime,
    'timestamp' => date('Y-m-d H:i:s'),
]);
