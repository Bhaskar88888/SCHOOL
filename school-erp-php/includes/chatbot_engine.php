<?php
/**
 * Chatbot Engine — v5.0
 * School ERP PHP — Full Node.js Feature Parity (No Gemini / No AI API)
 *
 * PORTED FROM Node.js:
 *  - Spell correction        (nlpEngine.js  SPELL_CORRECTIONS)
 *  - Synonym expansion       (nlpEngine.js  SYNONYMS)
 *  - Multi-intent detection  (nlpEngine.js  detectMultipleIntents)
 *  - Follow-up context       (nlpEngine.js  handleFollowUp)
 *  - Proactive alerts        (nlpEngine.js  getProactiveAlerts)
 *  - Role-based welcome      (chatbotUi.js  ROLE_WELCOME)
 *  - Role-based quick actions(chatbotUi.js  ROLE_ACTIONS)
 *  - Role-based suggestions  (chatbotUi.js  ROLE_SUGGESTIONS)
 */

// ═══════════════════════════════════════════════════════════════════
// 1. SPELL CORRECTION  (from nlpEngine.js SPELL_CORRECTIONS)
// ═══════════════════════════════════════════════════════════════════
const CHATBOT_SPELL_CORRECTIONS = [
    'atendance'     => 'attendance', 'attandance'    => 'attendance', 'attendence'    => 'attendance',
    'libary'        => 'library',    'librery'       => 'library',    'libray'        => 'library',
    'examm'         => 'exam',       'exame'         => 'exam',       'exm'           => 'exam',
    'hostal'        => 'hostel',     'hostl'         => 'hostel',     'hostle'        => 'hostel',
    'trasport'      => 'transport',  'transpor'      => 'transport',  'transportaion' => 'transport',
    'payrole'       => 'payroll',    'payrol'        => 'payroll',    'payrolee'      => 'payroll',
    'recipt'        => 'receipt',    'receit'        => 'receipt',    'reciept'       => 'receipt',
    'complant'      => 'complaint',  'complait'      => 'complaint',
    'timetabel'     => 'timetable',  'scedule'       => 'schedule',   'shedule'       => 'schedule',
    'notic'         => 'notice',     'paymant'       => 'payment',    'salry'         => 'salary',
    'stident'       => 'student',    'studnet'       => 'student',    'stduent'       => 'student',
    'tehcer'        => 'teacher',    'techer'        => 'teacher',    'teachr'        => 'teacher',
    'fess'          => 'fee',        'canteem'       => 'canteen',    'rout'          => 'route',
    'rute'          => 'route',      'narks'         => 'marks',      'marrks'        => 'marks',
    'homwork'       => 'homework',   'asignment'     => 'assignment', 'sholarship'    => 'scholarship',
    'schollrship'   => 'scholarship','finer'         => 'fine',       'remak'         => 'remark',
    'rimark'        => 'remark',     'cmplaint'      => 'complaint',  'complain'      => 'complaint',
];

function chatbot_correct_spelling(string $text): string
{
    foreach (CHATBOT_SPELL_CORRECTIONS as $wrong => $right) {
        $text = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/iu', $right, $text);
    }
    return $text;
}

// ═══════════════════════════════════════════════════════════════════
// 2. SYNONYM EXPANSION  (from nlpEngine.js SYNONYMS)
// ═══════════════════════════════════════════════════════════════════
const CHATBOT_SYNONYMS = [
    'fee'        => ['dues', 'charges', 'maasul', 'shulk', 'maachul', 'মাচুল', 'फीस', 'শুল্ক'],
    'exam'       => ['test', 'paper', 'assessment', 'pariksha', 'পৰীক্ষা', 'परीक्षा'],
    'attendance' => ['present', 'absent', 'hajiri', 'upasthiti', 'উপস্থিতি', 'उपस्थिति'],
    'library'    => ['books', 'kitab', 'pustakalay', 'লাইব্ৰেৰী', 'पुस्तकालय'],
    'marks'      => ['score', 'grade', 'result', 'number', 'nambar', 'নম্বৰ', 'नंबर'],
    'holiday'    => ['vacation', 'break', 'chutti', 'বন্ধ', 'छुट्टी'],
    'teacher'    => ['sir', 'maam', 'guru', 'shikshak', 'শিক্ষক', 'शिक्षक'],
    'student'    => ['pupil', 'child', 'baccha', 'chatra', 'ছাত্ৰ', 'छात्र'],
    'canteen'    => ['food', 'mess', 'lunch', 'khana', 'ahar', 'খাদ্য', 'खाना', 'snacks', 'meal'],
    'hostel'     => ['room', 'dormitory', 'boarding', 'awashan', 'হোষ্টেল', 'हॉस्टल', 'accommodation'],
    'transport'  => ['bus', 'vehicle', 'vahan', 'paribahan', 'পৰিবহণ', 'ट्रांसपोर्ट', 'van', 'cab'],
    'payroll'    => ['salary', 'dadarma', 'tanakha', 'দৰমহা', 'वेतन', 'wage', 'payslip'],
    'homework'   => ['assignment', 'griha kaam', 'গৃহকাৰ্য', 'होमवर्क', 'task', 'project'],
    'notice'     => ['announcement', 'janani', 'suchana', 'নোটিছ', 'सूचना', 'circular', 'news'],
    'complaint'  => ['issue', 'problem', 'shikayat', 'abhiyog', 'অভিযোগ', 'शिकायत', 'report', 'grievance'],
    'timetable'  => ['routine', 'schedule', 'samay suchi', 'ৰুটিন', 'समय सारणी', 'period'],
    'fine'       => ['penalty', 'late fee', 'jurmana', 'jrimana', 'জৰিমনা', 'जुर्माना', 'charge'],
    'remark'     => ['comment', 'feedback', 'review', 'tippani', 'mantavya', 'टिप्पणी', 'মন্তব্য'],
];

function chatbot_expand_synonyms(string $text): string
{
    $lower = mb_strtolower($text, 'UTF-8');
    foreach (CHATBOT_SYNONYMS as $canonical => $synonyms) {
        foreach ($synonyms as $syn) {
            if (mb_strpos($lower, mb_strtolower($syn, 'UTF-8'), 0, 'UTF-8') !== false) {
                // Only replace if canonical not already present
                if (mb_strpos($lower, $canonical, 0, 'UTF-8') === false) {
                    $lower = str_ireplace($syn, $canonical, $lower);
                }
            }
        }
    }
    return $lower;
}

// ═══════════════════════════════════════════════════════════════════
// 3. MULTI-INTENT DETECTION  (from nlpEngine.js detectMultipleIntents)
// ═══════════════════════════════════════════════════════════════════
const CHATBOT_INTENT_KEYWORDS = [
    'attendance' => ['attendance', 'present', 'absent', 'hajiri', 'উপস্থিতি', 'उपस्थिति'],
    'fee'        => ['fee', 'payment', 'due', 'balance', 'dues', 'maasul', 'মাচুল', 'फीस'],
    'exam'       => ['exam', 'test', 'result', 'marks', 'pariksha', 'পৰীক্ষা', 'परीक्षा'],
    'homework'   => ['homework', 'assignment', 'griha', 'গৃহকাৰ্য', 'होमवर्क'],
    'notice'     => ['notice', 'announcement', 'janani', 'নোটিছ', 'सूचना'],
    'library'    => ['book', 'library', 'kitab', 'borrow', 'লাইব্ৰেৰী', 'पुस्तकालय'],
    'transport'  => ['bus', 'transport', 'route', 'vahan', 'পৰিবহণ', 'ट्रांसपोर्ट'],
];

/**
 * Returns array of matched intent keys if 2+ intents detected, else null.
 */
function chatbot_detect_multiple_intents(string $text): ?array
{
    $lower   = mb_strtolower($text, 'UTF-8');
    $matched = [];
    foreach (CHATBOT_INTENT_KEYWORDS as $intent => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($lower, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
                $matched[] = $intent;
                break;
            }
        }
    }
    return count($matched) >= 2 ? array_unique($matched) : null;
}

// ═══════════════════════════════════════════════════════════════════
// 4. SESSION-BASED CONTEXT  (replaces Node.js conversationContext Map)
// ═══════════════════════════════════════════════════════════════════
function chatbot_get_context(string $userId): array
{
    if (!isset($_SESSION['chatbot_context'][$userId])) {
        $_SESSION['chatbot_context'][$userId] = [
            'messages'   => [],
            'lastIntent' => null,
            'entities'   => [],
            'timestamp'  => time(),
        ];
    }
    // Expire context older than 1 hour
    if (time() - ($_SESSION['chatbot_context'][$userId]['timestamp'] ?? 0) > 3600) {
        $_SESSION['chatbot_context'][$userId] = [
            'messages'   => [],
            'lastIntent' => null,
            'entities'   => [],
            'timestamp'  => time(),
        ];
    }
    return $_SESSION['chatbot_context'][$userId];
}

function chatbot_update_context(string $userId, string $intent, string $message): void
{
    $ctx = chatbot_get_context($userId);
    $ctx['lastIntent'] = $intent;
    $ctx['messages'][] = ['role' => 'user', 'message' => $message, 'time' => time()];
    if (count($ctx['messages']) > 10) {
        $ctx['messages'] = array_slice($ctx['messages'], -10);
    }
    $ctx['timestamp'] = time();
    $_SESSION['chatbot_context'][$userId] = $ctx;
}

function chatbot_add_assistant_message(string $userId, string $message): void
{
    $ctx = chatbot_get_context($userId);
    $ctx['messages'][] = ['role' => 'assistant', 'message' => $message, 'time' => time()];
    if (count($ctx['messages']) > 10) {
        $ctx['messages'] = array_slice($ctx['messages'], -10);
    }
    $_SESSION['chatbot_context'][$userId] = $ctx;
}

// ═══════════════════════════════════════════════════════════════════
// 5. FOLLOW-UP CONTEXT  (from nlpEngine.js handleFollowUp)
// ═══════════════════════════════════════════════════════════════════
function chatbot_handle_followup(array $context, string $message, string $lang): ?string
{
    $lower = mb_strtolower($message, 'UTF-8');
    $last  = $context['lastIntent'] ?? null;

    if (!$last) return null;

    // Attendance → asked about students
    if ($last === 'attendance_today' && mb_strpos($lower, 'student', 0, 'UTF-8') !== false) {
        return _cbl($lang, [
            'en' => 'For student attendance details, visit the Attendance module. Would you like help marking student attendance?',
            'hi' => 'छात्र उपस्थिति विवरण के लिए Attendance मॉड्यूल देखें। क्या आपको उपस्थिति दर्ज करने में सहायता चाहिए?',
            'as' => 'ছাত্ৰৰ উপস্থিতিৰ বাবে Attendance মডিউল চাওক। উপস্থিতি চিহ্নিত কৰাত সহায় লাগিবনে?',
        ]);
    }

    // Fee → asked about payment methods
    if (in_array($last, ['fee_pending', 'fee_collection'], true) &&
        (mb_strpos($lower, 'pay', 0, 'UTF-8') !== false || mb_strpos($lower, 'method', 0, 'UTF-8') !== false)) {
        return _cbl($lang, [
            'en' => '💳 Fee payments accepted: Cash, Card, UPI, Bank Transfer, Cheque. Visit the Fee module to collect or print receipts.',
            'hi' => '💳 फीस भुगतान: नकद, कार्ड, UPI, बैंक ट्रांसफर, चेक। Fee मॉड्यूल से रसीद प्राप्त करें।',
            'as' => '💳 মাচুল পৰিশোধ: নগদ, কাৰ্ড, UPI, বেংক ট্ৰান্সফাৰ, চেক। Fee মডিউলৰ পৰা ৰচিদ লওক।',
        ]);
    }

    // Notices → asked about urgent/important
    if ($last === 'notices' &&
        (mb_strpos($lower, 'important', 0, 'UTF-8') !== false || mb_strpos($lower, 'urgent', 0, 'UTF-8') !== false)) {
        return _cbl($lang, [
            'en' => 'Urgent notices appear at the top of the notices list. You can filter by priority on the Notices page.',
            'hi' => 'तत्काल नोटिस सूची में सबसे ऊपर दिखते हैं। Notices पेज पर priority से फ़िल्टर करें।',
            'as' => 'জৰুৰী নোটিছ তালিকাৰ শীৰ্ষত দেখা যায়। Notices পৃষ্ঠাত priority অনুযায়ী ফিল্টাৰ কৰক।',
        ]);
    }

    // Homework → asked about due dates
    if (in_array($last, ['homework'], true) &&
        (mb_strpos($lower, 'due', 0, 'UTF-8') !== false || mb_strpos($lower, 'when', 0, 'UTF-8') !== false)) {
        return _cbl($lang, [
            'en' => 'Homework entries include their due date. For only overdue work, ask for "pending homework" or "overdue homework".',
            'hi' => 'होमवर्क में due date होती है। केवल overdue work के लिए "pending homework" पूछें।',
            'as' => 'গৃহকাৰ্যত due date থাকে। কেৱল overdue কামৰ বাবে "pending homework" বুলি সোধক।',
        ]);
    }

    // Exams → asked about report card
    if ($last === 'exams' &&
        (mb_strpos($lower, 'report', 0, 'UTF-8') !== false || mb_strpos($lower, 'card', 0, 'UTF-8') !== false)) {
        return _cbl($lang, [
            'en' => 'For a full report card, open the Exams module and generate the student report card PDF.',
            'hi' => 'पूर्ण रिपोर्ट कार्ड के लिए Exams मॉड्यूल खोलें और PDF generate करें।',
            'as' => 'সম্পূৰ্ণ ৰিপোৰ্ট কাৰ্ডৰ বাবে Exams মডিউল খুলক আৰু PDF generate কৰক।',
        ]);
    }

    // Transport → asked about driver/contact
    if ($last === 'transport' &&
        (mb_strpos($lower, 'driver', 0, 'UTF-8') !== false || mb_strpos($lower, 'contact', 0, 'UTF-8') !== false)) {
        return _cbl($lang, [
            'en' => 'You can find driver contact info in the Transport module under route details. Ask by bus number if you know it.',
            'hi' => 'ड्राइवर संपर्क Transport मॉड्यूल के route विवरण में मिलेगा। बस नंबर देकर पूछ सकते हैं।',
            'as' => 'চালকৰ যোগাযোগ Transport মডিউলৰ ৰুট বিৱৰণত পাব। বাছ নম্বৰ দি সোধিব পাৰে।',
        ]);
    }

    // Generic follow-up
    if (mb_strpos($lower, 'what', 0, 'UTF-8') !== false
        || mb_strpos($lower, 'how', 0, 'UTF-8') !== false
        || mb_strpos($lower, 'why', 0, 'UTF-8') !== false) {
        return _cbl($lang, [
            'en' => "Based on our previous conversation about {$last}, could you be more specific about what you'd like to know?",
            'hi' => "{$last} के बारे में हमारी बातचीत के आधार पर, कृपया स्पष्ट करें क्या जानना चाहते हैं?",
            'as' => "{$last}ৰ বিষয়ে আমাৰ কথোপকথনৰ ভিত্তিত, আপুনি কি জানিব বিচাৰিছে সেয়া স্পষ্ট কৰক।",
        ]);
    }

    return null;
}

// Helper: pick localized copy
function _cbl(string $lang, array $copy): string
{
    return $copy[$lang] ?? $copy['en'];
}

// ═══════════════════════════════════════════════════════════════════
// 6. PROACTIVE ALERTS  (from nlpEngine.js getProactiveAlerts)
// ═══════════════════════════════════════════════════════════════════
function chatbot_get_proactive_alerts(int $userId, string $role): string
{
    $alerts = [];

    if (!in_array($role, ['student', 'parent'], true)) {
        return '';
    }

    try {
        // Get student IDs linked to this user
        $studentIds = [];
        if ($role === 'student') {
            $s = db_fetch("SELECT id FROM students WHERE user_id = ? AND is_active = 1", [$userId]);
            if ($s) $studentIds[] = $s['id'];
        } elseif ($role === 'parent') {
            $rows = db_fetchAll("SELECT id FROM students WHERE parent_user_id = ? AND is_active = 1", [$userId]);
            $studentIds = $rows ? array_column($rows, 'id') : [];
        }

        if (!empty($studentIds)) {
            $ph = implode(',', array_fill(0, count($studentIds), '?'));

            // Attendance alert
            $total   = db_count("SELECT COUNT(*) FROM attendance WHERE student_id IN ($ph)", $studentIds);
            $present = db_count("SELECT COUNT(*) FROM attendance WHERE student_id IN ($ph) AND status = 'present'", $studentIds);
            if ($total > 0) {
                $pct = round(($present / $total) * 100);
                if ($pct < 75) {
                    $alerts[] = "⚠️ Your attendance is {$pct}% — below the 75% minimum required for exams!";
                }
            }

            // Overdue books alert
            $overdue = db_count(
                "SELECT COUNT(*) FROM library_issues WHERE student_id IN ($ph) AND is_returned = 0 AND due_date < CURDATE()",
                $studentIds
            );
            if ($overdue > 0) {
                $alerts[] = "📖 You have {$overdue} overdue library book(s). Please return them to avoid fines.";
            }
        }
    } catch (Exception $e) {
        error_log('Chatbot proactive alerts error: ' . $e->getMessage());
    }

    return $alerts ? implode("\n\n", $alerts) . "\n\n" : '';
}

// ═══════════════════════════════════════════════════════════════════
// 7. ROLE-BASED WELCOME  (from chatbotUi.js ROLE_WELCOME)
// ═══════════════════════════════════════════════════════════════════
const CHATBOT_ROLE_WELCOME = [
    'superadmin' => [
        'en' => 'Hello! 👋 You can ask about students, fees, exams, notices, reports, payroll, transport, hostel, and system workflows.',
        'hi' => 'नमस्ते! 👋 आप छात्र, फीस, परीक्षा, नोटिस, रिपोर्ट, पेरोल, ट्रांसपोर्ट, हॉस्टल और सिस्टम वर्कफ़्लो के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি ছাত্ৰ, মাচুল, পৰীক্ষা, নোটিছ, প্ৰতিবেদন, পেৰ\'ল, পৰিবহণ, হোষ্টেল আৰু চিষ্টেমৰ কাৰ্যপ্ৰবাহ বিষয়ে সোধিব পাৰে।',
    ],
    'admin' => [
        'en' => 'Hello! 👋 You can ask about students, fees, exams, notices, reports, payroll, transport, hostel, and system workflows.',
        'hi' => 'नमस्ते! 👋 आप छात्र, फीस, परीक्षा, नोटिस, रिपोर्ट, पेरोल, ट्रांसपोर्ट, हॉस्टल और सिस्टम वर्कफ़्लो के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি ছাত্ৰ, মাচুল, পৰীক্ষা, নোটিছ, প্ৰতিবেদন, পেৰ\'ল, পৰিবহণ, হোষ্টেল আৰু চিষ্টেমৰ কাৰ্যপ্ৰবাহ বিষয়ে সোধিব পাৰে।',
    ],
    'teacher' => [
        'en' => 'Hello! 👋 Ask me about attendance, homework, exams, remarks, notices, routines, and your class-related work.',
        'hi' => 'नमस्ते! 👋 आप उपस्थिति, होमवर्क, परीक्षा, रिमार्क, नोटिस, रूटीन और अपनी कक्षा से जुड़े काम के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি উপস্থিতি, গৃহকাৰ্য, পৰীক্ষা, মন্তব্য, নোটিছ, ৰুটিন আৰু নিজৰ শ্ৰেণীৰ কামৰ বিষয়ে সোধিব পাৰে।',
    ],
    'student' => [
        'en' => 'Hello! 👋 Ask me about your attendance, homework, exams, results, notices, library, hostel, or transport details.',
        'hi' => 'नमस्ते! 👋 आप अपनी उपस्थिति, होमवर्क, परीक्षा, परिणाम, नोटिस, लाइब्रेरी, हॉस्टल या ट्रांसपोर्ट के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি নিজৰ উপস্থিতি, গৃহকাৰ্য, পৰীক্ষা, ফলাফল, নোটিছ, লাইব্ৰেৰী, হোষ্টেল বা পৰিবহণৰ বিষয়ে সোধিব পাৰে।',
    ],
    'parent' => [
        'en' => 'Hello! 👋 Ask me about your child\'s attendance, fee status, results, homework, notices, complaints, or transport.',
        'hi' => 'नमस्ते! 👋 आप अपने बच्चे की उपस्थिति, फीस स्थिति, परिणाम, होमवर्क, नोटिस, शिकायत या ट्रांसपोर्ट के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি নিজৰ সন্তানৰ উপস্থিতি, মাচুল, ফলাফল, গৃহকাৰ্য, নোটিছ, অভিযোগ বা পৰিবহণৰ বিষয়ে সোধিব পাৰে।',
    ],
    'accounts' => [
        'en' => 'Hello! 👋 Ask me about fee collection, defaulters, collection reports, exports, and payroll summaries.',
        'hi' => 'नमस्ते! 👋 आप फीस कलेक्शन, डिफॉल्टर, कलेक्शन रिपोर्ट, एक्सपोर्ट और पेरोल सारांश के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি মাচুল সংগ্ৰহ, বকেয়া তালিকা, সংগ্ৰহ প্ৰতিবেদন, এক্সপ\'ৰ্ট আৰু পেৰ\'ল সাৰাংশৰ বিষয়ে সোধিব পাৰে।',
    ],
    'hr' => [
        'en' => 'Hello! 👋 Ask me about staff attendance, leave balance, payroll records, notices, complaints, and HR workflows.',
        'hi' => 'नमस्ते! 👋 आप स्टाफ उपस्थिति, छुट्टी बैलेंस, पेरोल रिकॉर्ड, नोटिस, शिकायत और HR वर्कफ़्लो के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি কৰ্মচাৰীৰ উপস্থিতি, ছুটিৰ জেৰ, পেৰ\'ল ৰেকৰ্ড, নোটিছ, অভিযোগ আৰু HR কাৰ্যপ্ৰবাহৰ বিষয়ে সোধিব পাৰে।',
    ],
    'canteen' => [
        'en' => 'Hello! 👋 Ask me about canteen menu, sales, wallet balance, recharge help, and notice updates.',
        'hi' => 'नमस्ते! 👋 आप कैंटीन मेनू, बिक्री, वॉलेट बैलेंस, रिचार्ज और नोटिस के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি কেন্টিন মেনু, বিক্ৰী, ৱালেট জেৰ, ৰিচাৰ্জ আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
    ],
    'conductor' => [
        'en' => 'Hello! 👋 Ask me about your route, transport attendance, student manifest, and notices.',
        'hi' => 'नमस्ते! 👋 आप अपने रूट, ट्रांसपोर्ट उपस्थिति, छात्र सूची और नोटिस के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি নিজৰ ৰুট, পৰিবহণ উপস্থিতি, ছাত্ৰ তালিকা আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
    ],
    'driver' => [
        'en' => 'Hello! 👋 Ask me about your route, assigned vehicle, transport details, and notices.',
        'hi' => 'नमस्ते! 👋 आप अपने रूट, वाहन, ट्रांसपोर्ट विवरण और नोटिस के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি নিজৰ ৰুট, বাহন, পৰিবহণৰ বিৱৰণ আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
    ],
    'librarian' => [
        'en' => 'Hello! 👋 Ask me about library books, issued items, overdue books, fines, and notices.',
        'hi' => 'नमस्ते! 👋 आप पुस्तकालय की किताबें, जारी आइटम, ओवरड्यू और जुर्माने के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি লাইব্ৰেৰীৰ কিতাপ, ইস্যু, অৱকাশ আৰু জৰিমনাৰ বিষয়ে সোধিব পাৰে।',
    ],
    'guest' => [
        'en' => 'Hello! 👋 Ask me about the School ERP modules and common workflows.',
        'hi' => 'नमस्ते! 👋 आप स्कूल ERP मॉड्यूल और सामान्य प्रक्रियाओं के बारे में पूछ सकते हैं।',
        'as' => 'নমস্কাৰ! 👋 আপুনি স্কুল ERP মডিউল আৰু সাধাৰণ প্ৰক্ৰিয়াৰ বিষয়ে সোধিব পাৰে।',
    ],
];

function chatbot_get_role_welcome(string $role, string $lang): string
{
    $role   = strtolower($role ?: 'guest');
    $map    = CHATBOT_ROLE_WELCOME[$role] ?? CHATBOT_ROLE_WELCOME['guest'];
    return $map[$lang] ?? $map['en'];
}

// ═══════════════════════════════════════════════════════════════════
// 8. ROLE-BASED QUICK ACTIONS  (from chatbotUi.js ROLE_ACTIONS)
// ═══════════════════════════════════════════════════════════════════
const CHATBOT_ROLE_ACTIONS = [
    'superadmin' => [
        'en' => ['Show dashboard', 'Student admission process', 'Fee collection workflow', 'Exam schedule', 'Show notices'],
        'hi' => ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश प्रक्रिया', 'फीस कलेक्शन', 'परीक्षा समय-सारणी', 'नोटिस दिखाओ'],
        'as' => ['ডেশ্বব\'ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি প্ৰক্ৰিয়া', 'মাচুল সংগ্ৰহ', 'পৰীক্ষাৰ সময়সূচী', 'নোটিছ দেখুওৱা'],
    ],
    'admin' => [
        'en' => ['Show dashboard', 'Student admission process', 'Fee collection workflow', 'Exam schedule', 'Show notices'],
        'hi' => ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश प्रक्रिया', 'फीस कलेक्शन', 'परीक्षा समय-सारणी', 'नोटिस दिखाओ'],
        'as' => ['ডেশ্বব\'ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি প্ৰক্ৰিয়া', 'মাচুল সংগ্ৰহ', 'পৰীক্ষাৰ সময়সূচী', 'নোটিছ দেখুওৱা'],
    ],
    'teacher' => [
        'en' => ['Mark attendance', 'Show homework', 'Exam timetable', 'Show routine', 'Remarks help'],
        'hi' => ['उपस्थिति दर्ज करें', 'होमवर्क दिखाओ', 'परीक्षा समय-सारणी', 'रूटीन दिखाओ', 'रिमार्क सहायता'],
        'as' => ['উপস্থিতি চিহ্নিত কৰক', 'গৃহকাৰ্য দেখুওৱা', 'পৰীক্ষাৰ সময়সূচী', 'ৰুটিন দেখুওৱা', 'মন্তব্য সহায়'],
    ],
    'student' => [
        'en' => ['My attendance', 'My homework', 'My exams', 'My results', 'Show notices'],
        'hi' => ['मेरी उपस्थिति', 'मेरा होमवर्क', 'मेरी परीक्षा', 'मेरा परिणाम', 'नोटिस दिखाओ'],
        'as' => ['মোৰ উপস্থিতি', 'মোৰ গৃহকাৰ্য', 'মোৰ পৰীক্ষা', 'মোৰ ফলাফল', 'নোটিছ দেখুওৱা'],
    ],
    'parent' => [
        'en' => ['Child attendance', 'Child fee status', 'Child results', 'Show notices', 'Complaint status'],
        'hi' => ['बच्चे की उपस्थिति', 'बच्चे की फीस', 'बच्चे का परिणाम', 'नोटिस दिखाओ', 'शिकायत स्थिति'],
        'as' => ['শিশুৰ উপস্থিতি', 'শিশুৰ মাচুল', 'শিশুৰ ফলাফল', 'নোটিছ দেখুওৱা', 'অভিযোগৰ অৱস্থা'],
    ],
    'accounts' => [
        'en' => ['Fee collection workflow', 'Fee defaulters', 'Collection report', 'Payroll summary', 'Export fees'],
        'hi' => ['फीस कलेक्शन', 'फीस डिफॉल्टर', 'कलेक्शन रिपोर्ट', 'पेरोल सारांश', 'फीस एक्सपोर्ट'],
        'as' => ['মাচুল সংগ্ৰহ', 'বকেয়া মাচুল', 'সংগ্ৰহ প্ৰতিবেদন', 'পেৰ\'ল সাৰাংশ', 'মাচুল এক্সপ\'ৰ্ট'],
    ],
    'hr' => [
        'en' => ['Leave balance', 'Staff attendance', 'Payroll records', 'Show notices', 'Complaint status'],
        'hi' => ['छुट्टी बैलेंस', 'स्टाफ उपस्थिति', 'पेरोल रिकॉर्ड', 'नोटिस दिखाओ', 'शिकायत स्थिति'],
        'as' => ['ছুটিৰ জেৰ', 'কৰ্মচাৰী উপস্থিতি', 'পেৰ\'ল ৰেকৰ্ড', 'নোটিছ দেখুওৱা', 'অভিযোগৰ অৱস্থা'],
    ],
    'canteen' => [
        'en' => ['Canteen menu', 'Sales summary', 'Wallet balance', 'Recharge help', 'Show notices'],
        'hi' => ['कैंटीन मेनू', 'बिक्री सारांश', 'वॉलेट बैलेंस', 'रिचार्ज सहायता', 'नोटिस दिखाओ'],
        'as' => ['কেন্টিন মেনু', 'বিক্ৰী সাৰাংশ', 'ৱালেট জেৰ', 'ৰিচাৰ্জ সহায়', 'নোটিছ দেখুওৱা'],
    ],
    'conductor' => [
        'en' => ['My transport', 'Transport attendance', 'Student manifest', 'Route details', 'Show notices'],
        'hi' => ['मेरा ट्रांसपोर्ट', 'ट्रांसपोर्ट उपस्थिति', 'छात्र सूची', 'रूट विवरण', 'नोटिस दिखाओ'],
        'as' => ['মোৰ পৰিবহণ', 'পৰিবহণ উপস্থিতি', 'ছাত্ৰ তালিকা', 'ৰুটৰ বিৱৰণ', 'নোটিছ দেখুওৱা'],
    ],
    'driver' => [
        'en' => ['My transport', 'Route details', 'Assigned vehicle', 'Show notices'],
        'hi' => ['मेरा ट्रांसपोर्ट', 'रूट विवरण', 'मेरा वाहन', 'नोटिस दिखाओ'],
        'as' => ['মোৰ পৰিবহণ', 'ৰুটৰ বিৱৰণ', 'মোৰ বাহন', 'নোটিছ দেখুওৱা'],
    ],
    'librarian' => [
        'en' => ['Overdue books', 'Issue a book', 'Library status', 'Show notices'],
        'hi' => ['ओवरड्यू किताबें', 'किताब जारी करें', 'लाइब्रेरी स्थिति', 'नोटिस दिखाओ'],
        'as' => ['অৱকাশ কিতাপ', 'কিতাপ ইস্যু কৰক', 'লাইব্ৰেৰী অৱস্থা', 'নোটিছ দেখুওৱা'],
    ],
    'guest' => [
        'en' => ['Show dashboard', 'Student admission process', 'Canteen menu'],
        'hi' => ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश प्रक्रिया', 'कैंटीन मेनू'],
        'as' => ['ডেশ্বব\'ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি প্ৰক্ৰিয়া', 'কেন্টিন মেনু'],
    ],
];

// ═══════════════════════════════════════════════════════════════════
// 9. ROLE-BASED SUGGESTIONS  (from chatbotUi.js ROLE_SUGGESTIONS)
// ═══════════════════════════════════════════════════════════════════
const CHATBOT_ROLE_SUGGESTIONS = [
    'teacher' => [
        'en' => ['Show homework', 'Exam timetable', 'Show attendance report'],
        'hi' => ['होमवर्क दिखाओ', 'परीक्षा समय-सारणी', 'उपस्थिति रिपोर्ट'],
        'as' => ['গৃহকাৰ্য দেখুওৱা', 'পৰীক্ষাৰ সময়সূচী', 'উপস্থিতি প্ৰতিবেদন'],
    ],
    'student' => [
        'en' => ['My attendance history', 'My results', 'Library help'],
        'hi' => ['मेरी उपस्थिति इतिहास', 'मेरा परिणाम', 'लाइब्रेरी सहायता'],
        'as' => ['মোৰ উপস্থিতি ইতিহাস', 'মোৰ ফলাফল', 'লাইব্ৰেৰী সহায়'],
    ],
    'parent' => [
        'en' => ['Child attendance history', 'Child fee history', 'Show notices'],
        'hi' => ['बच्चे की उपस्थिति इतिहास', 'बच्चे की फीस इतिहास', 'नोटिस दिखाओ'],
        'as' => ['শিশুৰ উপস্থিতি ইতিহাস', 'শিশুৰ মাচুল ইতিহাস', 'নোটিছ দেখুওৱা'],
    ],
    'default' => [
        'en' => ['Show dashboard', 'Show notices', 'Help'],
        'hi' => ['डैशबोर्ड दिखाओ', 'नोटिस दिखाओ', 'सहायता'],
        'as' => ['ডেশ্বব\'ৰ্ড দেখুওৱা', 'নোটিছ দেখুওৱা', 'সহায়'],
    ],
];

function chatbot_get_role_quick_actions(string $role, string $lang): array
{
    $role = strtolower($role ?: 'guest');
    $map  = CHATBOT_ROLE_ACTIONS[$role] ?? CHATBOT_ROLE_ACTIONS['guest'];
    return $map[$lang] ?? $map['en'];
}

function chatbot_get_role_suggestions(string $role, string $lang): array
{
    $role = strtolower($role ?: 'guest');
    $map  = CHATBOT_ROLE_SUGGESTIONS[$role] ?? CHATBOT_ROLE_SUGGESTIONS['default'];
    return $map[$lang] ?? $map['en'];
}

// ═══════════════════════════════════════════════════════════════════
// 10. LOCALIZED FALLBACK  (matches nlpEngine.js getLocalizedFallback)
// ═══════════════════════════════════════════════════════════════════
function chatbot_localized_fallback(string $lang, string $role): array
{
    $message = _cbl($lang, [
        'en' => "I didn't quite understand that. Try asking about:\n• Attendance • Fees • Exams • Library • Transport\n• Homework • Notices • Hostel • Payroll\n\nOr type **/help** for shortcuts.",
        'hi' => "मैं पूरी तरह समझ नहीं पाया। इनके बारे में पूछें:\n• उपस्थिति • फीस • परीक्षा • पुस्तकालय • ट्रांसपोर्ट\n• होमवर्क • नोटिस • हॉस्टल\n\nया **/help** टाइप करें।",
        'as' => "মই পুৰাপুৰি বুজি নাপালোঁ। এইবোৰৰ বিষয়ে সোধক:\n• উপস্থিতি • মাচুল • পৰীক্ষা • লাইব্ৰেৰী • পৰিবহণ\n• গৃহকাৰ্য • নোটিছ • হোষ্টেল\n\nঅথবা **/help** টাইপ কৰক।",
    ]);
    $suggestions = chatbot_get_role_suggestions($role, $lang);
    return ['message' => $message, 'suggestions' => $suggestions];
}
