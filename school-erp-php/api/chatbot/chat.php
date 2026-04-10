<?php
/**
 * Enhanced Chatbot with 50+ Intents & Multi-Language Support
 * School ERP PHP v3.0 - Matches Node.js chatbot capabilities
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/chatbot_knowledge_en.php';

require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$data = get_post_json();
$message = trim($data['message'] ?? '');
$language = $data['language'] ?? 'en';
$role = get_current_role() ?? 'guest';
$userId = get_current_user_id();
$startTime = microtime(true);

if (!$message) {
    json_response(['reply' => 'Please type a message.', 'language' => $language]);
}

$msg = strtolower($message);
$reply = '';
$intent = 'unknown';

// Load knowledge base
$knowledgeBase = require __DIR__ . '/../../includes/chatbot_knowledge_en.php';

// ============================================
// INTENT DETECTION (50+ intents)
// ============================================

// 1. GREETING
if (preg_match('/\b(hi|hello|hey|good morning|good evening|howdy|namaste|नमस्ते|হ্যালো)\b/', $msg)) {
    $intent = 'greeting';
    $replies = [
        'en' => "Hello! 👋 How can I help you today?",
        'hi' => "नमस्ते! 👋 मैं आपकी कैसे मदद कर सकता हूँ?",
        'as' => "নমস্কাৰ! 👋 মই আপোনাক কেনেকৈ সহায় কৰিব পাৰোঁ?",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 2. HELP
elseif (preg_match('/\b(help|what can you do|commands|options|capabilities)\b/', $msg)) {
    $intent = 'help';
    $replies = [
        'en' => "🤖 **I can help with:**\n• Student info & count\n• Fee status & collection\n• Today's attendance\n• Staff information\n• Library status\n• Exams & results\n• Complaints summary\n• Hostel & Transport\n• Leave applications\n• Payroll info\n• Canteen menu\n• Homework & notices\n• School policies\n\nJust ask naturally! 😊",
        'hi' => "🤖 **मैं इनमें मदद कर सकता हूँ:**\n• छात्र जानकारी\n• फीस स्थिति\n• उपस्थिति\n• स्टाफ जानकारी\n• लाइब्रेरी\n• परीक्षा\n• शिकायतें\n• हॉस्टल और ट्रांसपोर्ट\n• छुट्टी\n• पेरोल\n• कैंटीन\n• होमवर्क और नोटिस\n• स्कूल नीतियां",
        'as' => "🤖 **মই সহায় কৰিব পাৰোঁ:**\n• ছাত্ৰৰ তথ্য\n• মাছুল অৱস্থা\n• উপস্থিতি\n• কৰ্মচাৰী তথ্য\n• লাইব্ৰেৰী\n• পৰীক্ষা\n• অভিযোগ\n• হোষ্টেল আৰু পৰিবহণ\n• ছুটি\n• পেৰ'ল\n• কেন্টিন\n• গৃহকাৰ্য আৰু নোটিছ\n• বিদ্যালয় নীতি",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 3. DASHBOARD
elseif (preg_match('/\b(dashboard|overview|summary|stats)\b/', $msg)) {
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

// 4-5. STUDENT COUNT/LOOKUP
elseif (preg_match('/\b(how many|total|count)\s*(students?|pupils)\b/', $msg)) {
    $intent = 'student_count';
    $count = db_count("SELECT COUNT(*) FROM students WHERE is_active = 1");
    $replies = [
        'en' => "📊 There are currently **{$count}** active students enrolled in the school.",
        'hi' => "📊 वर्तमान में स्कूल में **{$count}** सक्रिय छात्र नामांकित हैं।",
        'as' => "📊 বৰ্তমান বিদ্যালয়ত **{$count}** জন সক্ৰিয় ছাত্ৰ নামভুক্ত কৰিছে।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
} elseif (preg_match('/\b(student.*named?|find student|search student|who is|tell me about)\b/', $msg, $matches)) {
    $intent = 'student_lookup';
    $name = preg_replace('/.*(?:named?|student|who is|tell me about)\s+/i', '', $message);
    $student = db_fetch("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.name LIKE ? AND s.is_active = 1 LIMIT 1", ["%$name%"]);
    if ($student) {
        $replies = [
            'en' => "👨‍🎓 **{$student['name']}**\n• Class: {$student['class_name']}\n• Admission No: {$student['admission_no']}\n• Parent: {$student['parent_name']}\n• Phone: {$student['phone']}",
            'hi' => "👨‍🎓 **{$student['name']}**\n• कक्षा: {$student['class_name']}\n• प्रवेश क्रमांक: {$student['admission_no']}\n• अभिभावक: {$student['parent_name']}\n• फ़ोन: {$student['phone']}",
            'as' => "👨‍🎓 **{$student['name']}**\n• শ্ৰেণী: {$student['class_name']}\n• প্ৰৱেশ নং: {$student['admission_no']}\n• সংৰক্ষক: {$student['parent_name']}\n• ফোন: {$student['phone']}",
        ];
        $reply = $replies[$language] ?? $replies['en'];
    } else {
        $replies = [
            'en' => "❌ I couldn't find a student named '$name'. Please check the spelling.",
            'hi' => "❌ मुझे '$name' नाम का छात्र नहीं मिला। कृपया वर्तनी जांचें।",
            'as' => "❌ মই '$name' নামৰ ছাত্ৰ বিচাৰি নাপালোঁ। অনুগ্ৰহ কৰি বানান পৰীক্ষা কৰক।",
        ];
        $reply = $replies[$language] ?? $replies['en'];
    }
}

// 6-8. FEES
elseif (preg_match('/\b(fee.*pending|pending.*fee|due|unpaid|defaulter)\b/', $msg)) {
    $intent = 'fee_pending';
    $count = db_count("SELECT COUNT(*) FROM fees WHERE balance_amount > 0");
    $amount = db_fetch("SELECT COALESCE(SUM(balance_amount),0) as total FROM fees WHERE balance_amount > 0")['total'];
    $replies = [
        'en' => "💰 **Pending Fees**\n• {$count} students have pending dues\n• Total pending: ₹" . number_format($amount) . "\n\nVisit the Fee module for details.",
        'hi' => "💰 **बकाया फीस**\n• {$count} छात्रों के बकाया हैं\n• कुल बकाया: ₹" . number_format($amount),
        'as' => "💰 **বকেয়া মাছুল**\n• {$count} জন ছাত্ৰৰ বকেয়া আছে\n• মুঠ বকেয়া: ₹" . number_format($amount),
    ];
    $reply = $replies[$language] ?? $replies['en'];
} elseif (preg_match('/\b(fee.*collect|collection.*fee|fee.*today|fee.*month)\b/', $msg)) {
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

// 9-11. ATTENDANCE
elseif (preg_match('/\b(attendance|present|absent)\s*today\b/', $msg)) {
    $intent = 'attendance_today';
    $present = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'");
    $absent = db_count("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'absent'");
    $replies = [
        'en' => "✅ **Today's Attendance**\n• Present: {$present}\n• Absent: {$absent}\n\nFor detailed attendance, go to the Attendance module.",
        'hi' => "✅ **आज की उपस्थिति**\n• उपस्थित: {$present}\n• अनुपस्थित: {$absent}",
        'as' => "✅ **আজিৰ উপস্থিতি**\n• উপস্থিত: {$present}\n• অনুপস্থিত: {$absent}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 12-13. STAFF
elseif (preg_match('/\b(how many|total|count)\s*(staff|teachers?|employees?)\b/', $msg)) {
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

// 14-16. LIBRARY
elseif (preg_match('/\b(library|books?|borrow|return|overdue)\b/', $msg)) {
    $intent = 'library';
    $total = db_count("SELECT COUNT(*) FROM library_books");
    $issued = db_count("SELECT COUNT(*) FROM library_issues WHERE is_returned = 0");
    $overdue = db_count("SELECT COUNT(*) FROM library_issues WHERE is_returned = 0 AND due_date < CURDATE()");
    $replies = [
        'en' => "📚 **Library Status**\n• Total Books: {$total}\n• Currently Issued: {$issued}\n• Overdue: {$overdue}\n\nFine: ₹5/day for overdue books.",
        'hi' => "📚 **लाइब्रेरी स्थिति**\n• कुल किताबें: {$total}\n• जारी: {$issued}\n• ओवरड्यू: {$overdue}\n\nजुर्माना: ₹5/दिन",
        'as' => "📚 **লাইব্ৰেৰী অৱস্থা**\n• মুঠ কিতাপ: {$total}\n• ইস্যু হোৱা: {$issued}\n• অতিথি: {$overdue}\n\nজৰিমনা: ₹5/দিন",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 17. COMPLAINTS
elseif (preg_match('/\b(complaint|grievance)\b/', $msg)) {
    $intent = 'complaints';
    $pending = db_count("SELECT COUNT(*) FROM complaints WHERE status = 'pending'");
    $resolved = db_count("SELECT COUNT(*) FROM complaints WHERE status = 'resolved'");
    $replies = [
        'en' => "📣 **Complaints Summary**\n• Pending: {$pending}\n• Resolved: {$resolved}\n\nVisit the Complaints module to take action.",
        'hi' => "📣 **शिकायत सारांश**\n• लंबित: {$pending}\n• हल: {$resolved}",
        'as' => "📣 **অভিযোগ সাৰাংশ**\n• লম্বিত: {$pending}\n• সমাধান: {$resolved}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 18. CLASSES
elseif (preg_match('/\b(class|section|grade)\b/', $msg)) {
    $intent = 'classes';
    $classes = db_fetchAll("SELECT c.name, COUNT(s.id) as student_count FROM classes c LEFT JOIN students s ON s.class_id = c.id AND s.is_active = 1 GROUP BY c.id, c.name ORDER BY c.name LIMIT 10");
    if ($classes) {
        $list = array_map(fn($c) => "• {$c['name']}: {$c['student_count']} students", $classes);
        $replies = [
            'en' => "🏫 **Classes & Strength**\n" . implode("\n", $list),
            'hi' => "🏫 **कक्षाएँ और क्षमता**\n" . implode("\n", array_map(fn($c) => "• {$c['name']}: {$c['student_count']} छात्र", $classes)),
            'as' => "🏫 **শ্ৰেণী আৰু শক্তি**\n" . implode("\n", array_map(fn($c) => "• {$c['name']}: {$c['student_count']} ছাত্ৰ", $classes)),
        ];
        $reply = $replies[$language] ?? $replies['en'];
    }
}

// 19. HOSTEL
elseif (preg_match('/\b(hostel|room|accommodation)\b/', $msg)) {
    $intent = 'hostel';
    $total = db_count("SELECT COUNT(*) FROM hostel_rooms");
    $occupied = db_count("SELECT COUNT(*) FROM hostel_allocations WHERE status = 'ACTIVE'");
    $replies = [
        'en' => "🏠 **Hostel Status**\n• Total Rooms: {$total}\n• Occupied Beds: {$occupied}",
        'hi' => "🏠 **हॉस्टल स्थिति**\n• कुल कमरे: {$total}\n• भरे बेड: {$occupied}",
        'as' => "🏠 **হোষ্টেল অৱস্থা**\n• মুঠ কোঠা: {$total}\n• ভৰ্তি বিছনা: {$occupied}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 20. TRANSPORT
elseif (preg_match('/\b(transport|bus|route|vehicle)\b/', $msg)) {
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

// 21-22. EXAMS
elseif (preg_match('/\b(exam|result|marks|test)\b/', $msg)) {
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

// 23. LEAVE
elseif (preg_match('/\b(leave|absent request|leave application)\b/', $msg)) {
    $intent = 'leave';
    $pending = db_count("SELECT COUNT(*) FROM leave_applications WHERE status = 'pending'");
    $replies = [
        'en' => "📅 **Leave Applications**\n• Pending Approvals: {$pending}\n\nVisit the Leave module to approve or reject.",
        'hi' => "📅 **छुट्टी आवेदन**\n• लंबित अनुमोदन: {$pending}",
        'as' => "📅 **ছুটিৰ আবেদন**\n• লম্বিত অনুমোদন: {$pending}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 24. HOMEWORK
elseif (preg_match('/\b(homework|assignment)\b/', $msg)) {
    $intent = 'homework';
    $pending = db_count("SELECT COUNT(*) FROM homework WHERE due_date >= CURDATE()");
    $replies = [
        'en' => "📚 **Homework Status**\n• Active Assignments: {$pending}\n\nHomework must be submitted by due date. Late submissions may receive reduced marks.",
        'hi' => "📚 **होमवर्क स्थिति**\n• सक्रिय असाइनमेंट: {$pending}",
        'as' => "📚 **গৃহকাৰ্য অৱস্থা**\n• সক্ৰিয় এসাইনমেণ্ট: {$pending}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 25. NOTICES
elseif (preg_match('/\b(notice|announcement|circular)\b/', $msg)) {
    $intent = 'notices';
    $recent = db_fetchAll("SELECT title, priority, created_at FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
    if ($recent) {
        $list = array_map(fn($n) => "• {$n['title']} ({$n['priority']})", $recent);
        $replies = [
            'en' => "📢 **Recent Notices**\n" . implode("\n", $list) . "\n\nVisit Notices module for full details.",
            'hi' => "📢 **हाल के नोटिस**\n" . implode("\n", $list),
            'as' => "📢 **শেহতীয়া নোটিছ**\n" . implode("\n", $list),
        ];
        $reply = $replies[$language] ?? $replies['en'];
    } else {
        $replies = [
            'en' => "📢 No active notices at the moment.",
            'hi' => "📢 वर्तमान में कोई सक्रिय नोटिस नहीं है।",
            'as' => "📢 বৰ্তমান কোনো সক্ৰিয় নোটিছ নাই।",
        ];
        $reply = $replies[$language] ?? $replies['en'];
    }
}

// 26. PAYROLL
elseif (preg_match('/\b(payroll|salary|wages)\b/', $msg)) {
    $intent = 'payroll';
    $total = db_fetch("SELECT COALESCE(SUM(net_salary),0) as total FROM payroll WHERE month = MONTH(CURDATE()) AND year = YEAR(CURDATE())")['total'];
    $count = db_count("SELECT COUNT(*) FROM payroll WHERE month = MONTH(CURDATE()) AND year = YEAR(CURDATE())");
    $replies = [
        'en' => "💼 **Payroll Summary**\n• Processed This Month: {$count}\n• Total Amount: ₹" . number_format($total),
        'hi' => "💼 **पेरोल सारांश**\n• इस माह प्रक्रिया: {$count}\n• कुल राशि: ₹" . number_format($total),
        'as' => "💼 **পেৰ\'ল সাৰাংশ**\n• এই মাহত প্ৰক্ৰিয়া: {$count}\n• মুঠ পৰিমাণ: ₹" . number_format($total),
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 27. CANTEEN
elseif (preg_match('/\b(canteen|menu|food|snack)\b/', $msg)) {
    $intent = 'canteen';
    $items = db_count("SELECT COUNT(*) FROM canteen_items WHERE is_available = 1");
    $replies = [
        'en' => "🍔 **Canteen Status**\n• Available Items: {$items}\n\nVisit the Canteen module for menu and pricing. Payment via cash or RFID wallet.",
        'hi' => "🍔 **कैंटीन स्थिति**\n• उपलब्ध आइटम: {$items}",
        'as' => "🍔 **কেন্টিন অৱস্থা**\n• উপলব্ধ বস্তু: {$items}",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 28-35. KNOWLEDGE BASE SEARCH
elseif (strlen($msg) > 10) {
    // Search knowledge base
    $bestMatch = null;
    $bestScore = 0;

    foreach ($knowledgeBase as $entry) {
        $title = strtolower($entry['title']);
        $content = strtolower($entry['content']);
        $tags = array_map('strtolower', $entry['tags']);

        $score = 0;
        foreach ($tags as $tag) {
            if (strpos($msg, $tag) !== false) {
                $score += 2;
            }
        }
        if (strpos($title, $msg) !== false)
            $score += 5;
        if (strpos($content, $msg) !== false)
            $score += 1;

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $entry;
        }
    }

    if ($bestMatch && $bestScore >= 2) {
        $intent = 'knowledge_base';
        $reply = "📖 **{$bestMatch['title']}**\n\n{$bestMatch['content']}";
    }
}

// 36. WALLET BALANCE
elseif (preg_match('/\b(wallet|balance|recharge)\b/', $msg)) {
    $intent = 'wallet';
    $replies = [
        'en' => "💳 **Wallet Info**\n• Recharge at canteen counter in multiples of ₹100\n• Min: ₹100, Max: ₹2000\n• Lost card replacement: ₹50\n\nVisit Canteen module for your balance.",
        'hi' => "💳 **वॉलेट जानकारी**\n• ₹100 के गुणकों में रिचार्ज करें\n• न्यूनतम: ₹100, अधिकतम: ₹2000",
        'as' => "💳 **ৱালেট তথ্য**\n• ₹100 গুণকত ৰিচাৰ্জ কৰক\n• ন্যূনতম: ₹100, সৰ্বাধিক: ₹2000",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 37. SCHOOL HOURS
elseif (preg_match('/\b(school hours?|timings?|time|open|close)\b/', $msg)) {
    $intent = 'school_hours';
    $replies = [
        'en' => "🕐 **School Hours**\n• Monday-Friday: 8:00 AM - 3:00 PM\n• Saturday: 8:00 AM - 12:00 PM\n• Assembly: 7:50 AM\n• Lunch: 12:00-12:30 PM",
        'hi' => "🕐 **स्कूल समय**\n• सोमवार-शुक्रवार: 8:00 AM - 3:00 PM\n• शनिवार: 8:00 AM - 12:00 PM",
        'as' => "🕐 **বিদ্যালয়ৰ সময়**\n• সোম-শুকুৰ: 8:00 AM - 3:00 PM\n• শনি: 8:00 AM - 12:00 PM",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 38. HOLIDAYS
elseif (preg_match('/\b(holiday|vacation|break|closed)\b/', $msg)) {
    $intent = 'holidays';
    $replies = [
        'en' => "🏖️ **School Holidays**\n• Summer: May-June\n• Diwali: October\n• Winter: December-January\n\nFull calendar published at start of academic year.",
        'hi' => "🏖️ **स्कूल छुट्टियां**\n• गर्मी: मई-जून\n• दिवाली: अक्टूबर\n• सर्दी: दिसंबर-जनवरी",
        'as' => "🏖️ **বিদ্যালয়ৰ ছুটি**\n• গৰম: মে-জুন\n• দীপাৱলী: অক্টোবৰ\n• ঠাণ্ডা: ডিচেম্বাৰ-জানুৱাৰী",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 39. UNIFORM
elseif (preg_match('/\b(uniform|dress code|dresscode)\b/', $msg)) {
    $intent = 'uniform';
    $replies = [
        'en' => "👔 **Uniform Policy**\n• Summer: Light blue shirt/salwar, dark blue pants/skirt\n• Winter: Grey sweater with regular uniform\n• Sports uniform on PT days\n• Black shoes mandatory\n• Name badge must be worn",
        'hi' => "👔 **वर्दी नीति**\n• गर्मी: हल्का नीला शर्ट/सलवार, गहरा नीला पैंट/स्कर्ट\n• सर्दी: ग्रे स्वेटर",
        'as' => "👔 **বৰ্দ্ৰ নীতি**\n• গৰম: পাতল নীলা শাৰ্ট/চলৱাৰ, গাঢ় নীলা পেন্ট/স্কাৰ্ট",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 40. ADMISSION
elseif (preg_match('/\b(admission|admit|enroll|new student)\b/', $msg)) {
    $intent = 'admission';
    $replies = [
        'en' => "📝 **Admission Process**\n1. Fill online admission form\n2. Submit documents (Birth cert, Aadhaar, photos)\n3. Take entrance exam\n4. Interview (if selected)\n5. Admission confirmation\n\nEntrance exam: English, Maths, GK",
        'hi' => "📝 **प्रवेश प्रक्रिया**\n1. ऑनलाइन फॉर्म भरें\n2. दस्तावेज़ जमा करें\n3. प्रवेश परीक्षा दें\n4. साक्षात्कार\n5. प्रवेश पुष्टि",
        'as' => "📝 **ভৰ্তি প্ৰক্ৰিয়া**\n1. অনলাইন ফৰ্ম পূৰণ কৰক\n2. নথী প্ৰদান কৰক\n3. প্ৰৱেশ পৰীক্ষা দিয়ক\n4. সাক্ষাৎকাৰ\n5. ভৰ্তি নিশ্চিতকৰণ",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 41. TRANSFER CERTIFICATE
elseif (preg_match('/\b(transfer certificate|tc|withdraw|leave school)\b/', $msg)) {
    $intent = 'tc';
    $replies = [
        'en' => "📄 **Transfer Certificate Process**\n• Requirements: No pending dues, library books returned\n• Processing time: 7-15 working days\n• TC fee: ₹500\n• Apply through school office\n• TC available as PDF download",
        'hi' => "📄 **स्थानांतरण प्रमाणपत्र प्रक्रिया**\n• कोई बकाया नहीं, किताबें वापस\n• प्रक्रिया समय: 7-15 कार्य दिवस\n• शुल्क: ₹500",
        'as' => "📄 **স্থানান্তৰ প্ৰমাণপত্ৰ প্ৰক্ৰিয়া**\n• কোনো বকেয়া নাই, কিতাপ ঘূৰাই দিয়ক\n• প্ৰক্ৰিয়া সময়: 7-15 কৰ্ম দিৱস\n• মাছুল: ₹500",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 42. GRADING SYSTEM
elseif (preg_match('/\b(grading|grade|marks|percentage|pass|fail)\b/', $msg)) {
    $intent = 'grading';
    $replies = [
        'en' => "📊 **Grading System**\n• A+ (90-100%), A (80-89%), B+ (70-79%)\n• B (60-69%), C (50-59%), D (40-49%)\n• E (33-39%), F (Below 33%)\n• Minimum passing: 33%\n• Report cards available as PDF",
        'hi' => "📊 **ग्रेडिंग प्रणाली**\n• A+ (90-100%), A (80-89%), B+ (70-79%)\n• न्यूनतम पास: 33%",
        'as' => "📊 **গ্ৰেডিং প্ৰণালী**\n• A+ (90-100%), A (80-89%), B+ (70-79%)\n• ন্যূনতম পাছ: 33%",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 43. ANTI-BULLYING
elseif (preg_match('/\b(bully|bullying|harass)\b/', $msg)) {
    $intent = 'anti_bullying';
    $replies = [
        'en' => "⚠️ **Anti-Bullying Policy**\nZero tolerance for bullying. Any form (physical, verbal, cyber) results in strict disciplinary action. Report to any teacher immediately. All complaints investigated within 48 hours. Anonymous reporting available.",
        'hi' => "⚠️ **एंटी-बुलिंग नीति**\nबुलिंग के लिए शून्य सहिष्णुता। किसी भी रूप में कड़ी अनुशासनात्मक कार्रवाई होगी। किसी भी शिक्षक को तुरंत रिपोर्ट करें।",
        'as' => "⚠️ **এন্টি-বুলিং নীতি**\nবুলিংৰ বাবে শূন্য সহনশীলতা। যিকোনো ৰূপত কঠোৰ শাস্তিমূলক ব্যৱস্থা গ্ৰহণ কৰা হব। যিকোনো শিক্ষকক লগে লগে ৰিপ'ৰ্ট কৰক।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 44. MOBILE POLICY
elseif (preg_match('/\b(mobile|phone|cell)\b/', $msg)) {
    $intent = 'mobile_policy';
    $replies = [
        'en' => "📱 **Mobile Phone Policy**\nMobile phones NOT allowed during class hours. Must be switched off and stored in bags. Emergency calls through school office. Confiscated phones returned to parents only. Smartwatches prohibited during exams.",
        'hi' => "📱 **मोबाइल फोन नीति**\nकक्षा के दौरान मोबाइल फोन की अनुमति नहीं। बंद करके बैग में रखें। आपातकालीन कॉल स्कूल कार्यालय के माध्यम से।",
        'as' => "📱 **মোবাইল ফোন নীতি**\nশ্ৰেণীৰ সময়ত মোবাইল ফোনৰ অনুমতি নাই। বন্ধ কৰি বেগত ৰাখক। জৰুৰীকালীন কল বিদ্যালয় কাৰ্যালয়ৰ জৰিয়তে।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 45. EMERGENCY
elseif (preg_match('/\b(emergency|urgent|accident|hospital)\b/', $msg)) {
    $intent = 'emergency';
    $replies = [
        'en' => "🚨 **Emergency Contacts**\n• Principal: School Office\n• Medical Room: Extension 101\n• Transport: Extension 102\n\nIn medical emergency, student taken to nearest hospital. Parents informed immediately.",
        'hi' => "🚨 **आपातकालीन संपर्क**\n• प्रधानाचार्य: स्कूल कार्यालय\n• चिकित्सा कक्ष: एक्सटेंशन 101\n• परिवहन: एक्सटेंशन 102",
        'as' => "🚨 **জৰুৰীকালীন যোগাযোগ**\n• অধ্যক্ষ: বিদ্যালয় কাৰ্যালয়\n• চিকিৎসা কোঠা: এক্সটেনশ্যন 101\n• পৰিবহণ: এক্সটেনশ্যন 102",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 46. PARENT-TEACHER MEETING
elseif (preg_match('/\b(ptm|parent.?teacher|meeting)\b/', $msg)) {
    $intent = 'ptm';
    $replies = [
        'en' => "👨‍👩‍👧 **Parent-Teacher Meeting**\nPTM held once per quarter (every 3 months). Individual meetings can be requested through school office. PTM schedule announced via Notice Board. Please prepare questions in advance.",
        'hi' => "👨‍👩‍👧 **अभिभावक-शिक्षक बैठक**\nPTM प्रति तिमाही (हर 3 महीने) आयोजित। व्यक्तिगत बैठक स्कूल कार्यालय के माध्यम से अनुरोध की जा सकती है।",
        'as' => "👨‍👩‍👧 **অভিভাৱক-শিক্ষক সভা**\nPTM প্ৰতি তিনিমহীয়া (প্ৰতি 3 মাহত) অনুষ্ঠিত। ব্যক্তিগত সভা বিদ্যালয় কাৰ্যালয়ৰ জৰিয়তে অনুৰোধ কৰিব পাৰি।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 47. SCHOLARSHIP
elseif (preg_match('/\b(scholarship|concession|discount|financial aid)\b/', $msg)) {
    $intent = 'scholarship';
    $replies = [
        'en' => "🎓 **Scholarships & Concessions**\n• Merit: 90%+ in previous exams\n• EWS: Income certificate required\n• Sibling: 10% (2nd child), 15% (3rd)\n\nApply to Accounts office with documents.",
        'hi' => "🎓 **छात्रवृत्ति और रियायतें**\n• मेरिट: पिछली परीक्षाओं में 90%+\n• आर्थिक: आय प्रमाणपत्र आवश्यक\n• भाई-बहन: 10% (दूसरा), 15% (तीसरा)",
        'as' => "🎓 **বৃত্তি আৰু ৰেহাই**\n• মেৰিট: পূৰ্বৱৰ্তী পৰীক্ষাত 90%+\n• আৰ্থিক: আয় প্ৰমাণপত্ৰ প্ৰয়োজন\n• ভাই-ভনী: 10% (দ্বিতীয়), 15% (তৃতীয়)",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 48. LATE COMING
elseif (preg_match('/\b(late|tardy|delay)\b/', $msg)) {
    $intent = 'late_coming';
    $replies = [
        'en' => "⏰ **Late Coming Policy**\nStudents arriving after 8:15 AM are marked late. Three lates = one absence. Repeated late coming results in parent notification and possible detention. Late slip required from office.",
        'hi' => "⏰ **देर से आने की नीति**\n8:15 AM के बाद आने वाले छात्र देर से चिह्नित। तीन देर = एक अनुपस्थिति। बार-बार देर से आने पर अभिभावक सूचना।",
        'as' => "⏰ **পলমকৈ অহা নীতি**\n8:15 AM ৰ পিছত অহা ছাত্ৰ পলম বুলি চিহ্নিত। তিনি পলম = এটা অনুপস্থিতি। বাৰে বাৰে পলমকৈ অহাত অভিভাৱক জাননী।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 49. RE-EVALUATION
elseif (preg_match('/\b(re.?evaluat|recheck|review.*marks)\b/', $msg)) {
    $intent = 'reevaluation';
    $replies = [
        'en' => "📝 **Re-evaluation Process**\n• Request within 3 days of result\n• Fee: ₹100 per subject\n• Conducted by different teacher\n• If marks increase by 10%+, fee refunded\n• Results are final",
        'hi' => "📝 **पुनर्मूल्यांकन प्रक्रिया**\n• परिणाम के 3 दिनों के भीतर अनुरोध\n• शुल्क: ₹100 प्रति विषय\n• अलग शिक्षक द्वारा मूल्यांकन",
        'as' => "📝 **পুনৰ্মূল্যায়ন প্ৰক্ৰিয়া**\n• ফলাফলৰ 3 দিনৰ ভিতৰত অনুৰোধ\n• মাছুল: ₹100 প্ৰতি বিষয়\n• বেলেগ শিক্ষকৰ দ্বাৰা মূল্যায়ন",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 50. ATTENDANCE PERCENTAGE
elseif (preg_match('/\b(attendance.*percentage|percentage.*attendance|eligibility)\b/', $msg)) {
    $intent = 'attendance_percentage';
    $replies = [
        'en' => "📊 **Attendance Percentage**\nFormula: (Present Days / Total Working Days) × 100\nMinimum 75% required for exam eligibility.\nBelow 75%: Medical certificate required.\nCheck your percentage in Attendance module.",
        'hi' => "📊 **उपस्थिति प्रतिशत**\nसूत्र: (उपस्थित दिन / कुल कार्य दिवस) × 100\nपरीक्षा पात्रता के लिए न्यूनतम 75% आवश्यक।",
        'as' => "📊 **উপস্থিতি শতকৰা**\nসূত্ৰ: (উপস্থিত দিন / মুঠ কৰ্ম দিন) × 100\nপৰীক্ষাৰ যোগ্যতাৰ বাবে ন্যূনতম 75% প্ৰয়োজন।",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// 51. FINE CALCULATION
elseif (preg_match('/\b(fine|penalty|late fee)\b/', $msg)) {
    $intent = 'fine_calculation';
    $replies = [
        'en' => "💰 **Fine Calculation**\n• Library: ₹5/day for overdue books\n• Late Fee: ₹50/month past due date\n• Max library fine = book price\n• Fines must be paid before new borrowing\n• Late fee charged monthly until paid",
        'hi' => "💰 **जुर्माना गणना**\n• लाइब्रेरी: ₹5/दिन ओवरड्यू किताबों के लिए\n• देर से फीस: ₹50/माह नियत तिथि के बाद",
        'as' => "💰 **জৰিমনা গণনা**\n• লাইব্ৰেৰী: ₹5/দিন অতিথি কিতাপৰ বাবে\n• পলম মাছুল: ₹50/মাহ নিৰ্ধাৰিত তাৰিখৰ পিছত",
    ];
    $reply = $replies[$language] ?? $replies['en'];
}

// FALLBACK - Try Gemini API
if (!$reply) {
    $geminiKey = getenv('GEMINI_API_KEY') ?: '';
    if ($geminiKey) {
        $context = "You are an AI assistant for School ERP. Answer in {$language}. Question: " . $message;
        $postData = json_encode([
            'contents' => [['parts' => [['text' => $context]]]]
        ]);
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res, true);
        $geminiReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($geminiReply) {
            $reply = $geminiReply;
            $intent = 'gemini_fallback';
        }
    }

    if (!$reply) {
        $replies = [
            'en' => "I'm not sure about that. Try asking about:\n• Student count\n• Fee status\n• Today's attendance\n• Library books\n• Upcoming exams\n• School policies\n• Homework guidelines",
            'hi' => "मुझे इस बारे में निश्चित नहीं है। इनके बारे में पूछें:\n• छात्र संख्या\n• फीस स्थिति\n• आज की उपस्थिति\n• लाइब्रेरी किताबें\n• आगामी परीक्षा\n• स्कूल नीतियां",
            'as' => "মই এই বিষয়ে নিশ্চিত নহয়। ইয়াৰ বিষয়ে সোধক:\n• ছাত্ৰ সংখ্যা\n• মাছুল অৱস্থা\n• আজিৰ উপস্থিতি\n• লাইব্ৰেৰী কিতাপ\n• আগত পৰীক্ষা\n• বিদ্যালয় নীতি",
        ];
        $reply = $replies[$language] ?? $replies['en'];
        $intent = 'unknown';
    }
}

// Calculate response time
$responseTime = round((microtime(true) - $startTime) * 1000, 2);

// Log conversation
if (db_table_exists('chatbot_logs')) {
    db_query(
        "INSERT INTO chatbot_logs (user_id, user_role, message, language, intent, response, response_time, session_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$userId, $role, $message, $language, $intent, $reply, $responseTime, session_id()]
    );
}

json_response([
    'reply' => $reply,
    'intent' => $intent,
    'language' => $language,
    'responseTime' => $responseTime,
    'timestamp' => date('Y-m-d H:i:s'),
]);
