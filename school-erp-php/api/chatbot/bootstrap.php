<?php
/**
 * Chatbot Bootstrap API - Role-based welcome messages and quick actions
 * School ERP PHP v3.0 - Matches Node.js chatbotUi.js
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$role = get_current_role() ?? 'guest';
$language = $_GET['lang'] ?? 'en';

// Language options
$languages = [
    ['code' => 'en', 'label' => 'English'],
    ['code' => 'hi', 'label' => 'Hindi'],
    ['code' => 'as', 'label' => 'Assamese'],
];

// Role-based welcome messages (EN/HI/AS)
$welcome = [
    'en' => [
        'superadmin' => 'Hello. You can ask about students, fees, exams, notices, reports, payroll, transport, hostel, and system workflows.',
        'admin' => 'Hello. You can ask about students, fees, exams, notices, reports, payroll, transport, hostel, and system workflows.',
        'teacher' => 'Hello. Ask me about attendance, homework, exams, remarks, notices, routines, and your class-related work.',
        'student' => 'Hello. Ask me about your attendance, homework, exams, results, notices, library, hostel, or transport details.',
        'parent' => 'Hello. Ask me about your child attendance, fee status, results, homework, notices, complaints, or transport.',
        'accounts' => 'Hello. Ask me about fee collection, defaulters, collection reports, exports, and payroll summaries.',
        'hr' => 'Hello. Ask me about staff attendance, leave balance, payroll records, notices, complaints, and staff workflows.',
        'canteen' => 'Hello. Ask me about canteen menu, sales, wallet balance, recharge help, and notice updates.',
        'conductor' => 'Hello. Ask me about your route, transport attendance, student manifest, and notices.',
        'driver' => 'Hello. Ask me about your route, assigned vehicle, transport details, and notices.',
        'librarian' => 'Hello. Ask me about library books, issue/return, overdue books, and catalog management.',
        'guest' => 'Hello. Ask me about the School ERP modules and common workflows.',
    ],
    'hi' => [
        'superadmin' => 'नमस्ते। आप छात्र, फीस, परीक्षा, नोटिस, रिपोर्ट, पेरोल, ट्रांसपोर्ट, हॉस्टल और सिस्टम वर्कफ़्लो के बारे में पूछ सकते हैं।',
        'admin' => 'नमस्ते। आप छात्र, फीस, परीक्षा, नोटिस, रिपोर्ट, पेरोल, ट्रांसपोर्ट, हॉस्टल और सिस्टम वर्कफ़्लो के बारे में पूछ सकते हैं।',
        'teacher' => 'नमस्ते। आप उपस्थिति, होमवर्क, परीक्षा, रिमार्क, नोटिस, रूटीन और अपनी कक्षा से जुड़े काम के बारे में पूछ सकते हैं।',
        'student' => 'नमस्ते। आप अपनी उपस्थिति, होमवर्क, परीक्षा, परिणाम, नोटिस, लाइब्रेरी, हॉस्टल या ट्रांसपोर्ट के बारे में पूछ सकते हैं।',
        'parent' => 'नमस्ते। आप अपने बच्चे की उपस्थिति, फीस स्थिति, परिणाम, होमवर्क, नोटिस, शिकायत या ट्रांसपोर्ट के बारे में पूछ सकते हैं।',
        'accounts' => 'नमस्ते। आप फीस कलेक्शन, डिफॉल्टर, कलेक्शन रिपोर्ट, एक्सपोर्ट और पेरोल सारांश के बारे में पूछ सकते हैं।',
        'hr' => 'नमस्ते। आप स्टाफ उपस्थिति, छुट्टी बैलेंस, पेरोल रिकॉर्ड, नोटिस, शिकायत और HR वर्कफ़्लो के बारे में पूछ सकते हैं।',
        'canteen' => 'नमस्ते। आप कैंटीन मेनू, बिक्री, वॉलेट बैलेंस, रिचार्ज और नोटिस के बारे में पूछ सकते हैं।',
        'conductor' => 'नमस्ते। आप अपने रूट, ट्रांसपोर्ट उपस्थिति, छात्र सूची और नोटिस के बारे में पूछ सकते हैं।',
        'driver' => 'नमस्ते। आप अपने रूट, वाहन, ट्रांसपोर्ट विवरण और नोटिस के बारे में पूछ सकते हैं।',
        'librarian' => 'नमस्ते। आप लाइब्रेरी किताबें, इश्यू/रिटर्न, ओवरड्यू किताबें और कैटलॉग मैनेजमेंट के बारे में पूछ सकते हैं।',
        'guest' => 'नमस्ते। आप स्कूल ERP मॉड्यूल और सामान्य प्रक्रियाओं के बारे में पूछ सकते हैं।',
    ],
    'as' => [
        'superadmin' => 'নমস্কাৰ। আপুনি ছাত্ৰ, মাছুল, পৰীক্ষা, নোটিছ, প্ৰতিবেদন, পেৰ\'ল, পৰিবহণ, হোষ্টেল আৰু চিষ্টেমৰ কাৰ্যপ্ৰবাহ বিষয়ে সোধিব পাৰে।',
        'admin' => 'নমস্কাৰ। আপুনি ছাত্ৰ, মাছুল, পৰীক্ষা, নোটিছ, প্ৰতিবেদন, পেৰ\'ল, পৰিবহণ, হোষ্টেল আৰু চিষ্টেমৰ কাৰ্যপ্ৰবাহ বিষয়ে সোধিব পাৰে।',
        'teacher' => 'নমস্কাৰ। আপুনি উপস্থিতি, গৃহকাৰ্য, পৰীক্ষা, মন্তব্য, নোটিছ, ৰুটিন আৰু নিজৰ শ্ৰেণীৰ কামৰ বিষয়ে সোধিব পাৰে।',
        'student' => 'নমস্কাৰ। আপুনি নিজৰ উপস্থিতি, গৃহকাৰ্য, পৰীক্ষা, ফলাফল, নোটিছ, লাইব্ৰেৰী, হোষ্টেল বা পৰিবহণৰ বিষয়ে সোধিব পাৰে।',
        'parent' => 'নমস্কাৰ। আপুনি নিজৰ লিংক কৰা শিশুৰ উপস্থিতি, মাছুল, ফলাফল, গৃহকাৰ্য, নোটিছ, অভিযোগ বা পৰিবহণৰ বিষয়ে সোধিব পাৰে।',
        'accounts' => 'নমস্কাৰ। আপুনি মাছুল সংগ্ৰহ, বকেয়া তালিকা, সংগ্ৰহ প্ৰতিবেদন, এক্সপ\'ৰ্ট আৰু পেৰ\'ল সাৰাংশৰ বিষয়ে সোধিব পাৰে।',
        'hr' => 'নমস্কাৰ। আপুনি কৰ্মচাৰীৰ উপস্থিতি, ছুটিৰ জেৰ, পেৰ\'ল ৰেকৰ্ড, নোটিছ, অভিযোগ আৰু HR কাৰ্যপ্ৰবাহৰ বিষয়ে সোধিব পাৰে।',
        'canteen' => 'নমস্কাৰ। আপুনি কেন্টিন মেনু, বিক্ৰী, ৱালেট জেৰ, ৰিচাৰ্জ আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
        'conductor' => 'নমস্কাৰ। আপুনি নিজৰ ৰুট, পৰিবহণ উপস্থিতি, ছাত্ৰ তালিকা আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
        'driver' => 'নমস্কাৰ। আপুনি নিজৰ ৰুট, বাহন, পৰিবহণৰ বিৱৰণ আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
        'librarian' => 'নমস্কাৰ। আপুনি লাইব্ৰেৰী কিতাপ, ইস্যু/ৰিটাৰ্ন, অতিথি কিতাপ আৰু কেটালগ ব্যৱস্থাপনাৰ বিষয়ে সোধিব পাৰে।',
        'guest' => 'নমস্কাৰ। আপুনি স্কুল ERP মডিউল আৰু সাধাৰণ প্ৰক্ৰিয়াৰ বিষয়ে সোধিব পাৰে।',
    ],
];

// Role-based quick actions
$quickActions = [
    'en' => [
        'superadmin' => ['Show dashboard', 'Student admission', 'Fee collection', 'Exam schedule', 'Show notices'],
        'admin' => ['Show dashboard', 'Student admission', 'Fee collection', 'Exam schedule', 'Show notices'],
        'teacher' => ['Mark attendance', 'Show homework', 'Exam timetable', 'Show routine', 'Remarks help'],
        'student' => ['My attendance', 'My homework', 'My exams', 'My results', 'Show notices'],
        'parent' => ['Child attendance', 'Child fee status', 'Child results', 'Show notices', 'Complaint status'],
        'accounts' => ['Fee collection', 'Fee defaulters', 'Collection report', 'Payroll summary', 'Export fees'],
        'hr' => ['Leave balance', 'Staff attendance', 'Payroll records', 'Show notices', 'Complaint status'],
        'canteen' => ['Canteen menu', 'Sales summary', 'Wallet balance', 'Recharge help', 'Show notices'],
        'conductor' => ['My transport', 'Transport attendance', 'Student manifest', 'Route details', 'Show notices'],
        'driver' => ['My transport', 'Route details', 'Assigned vehicle', 'Show notices'],
        'librarian' => ['Library books', 'Issue book', 'Return book', 'Overdue list', 'Show notices'],
        'guest' => ['Show dashboard', 'Student admission', 'Canteen menu'],
    ],
    'hi' => [
        'superadmin' => ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश', 'फीस कलेक्शन', 'परीक्षा समय', 'नोटिस दिखाओ'],
        'admin' => ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश', 'फीस कलेक्शन', 'परीक्षा समय', 'नोटिस दिखाओ'],
        'teacher' => ['उपस्थिति दर्ज करें', 'होमवर्क दिखाओ', 'परीक्षा समय', 'रूटीन दिखाओ', 'रिमार्क सहायता'],
        'student' => ['मेरी उपस्थिति', 'मेरा होमवर्क', 'मेरी परीक्षा', 'मेरा परिणाम', 'नोटिस दिखाओ'],
        'parent' => ['बच्चे की उपस्थिति', 'बच्चे की फीस', 'बच्चे का परिणाम', 'नोटिस दिखाओ', 'शिकायत स्थिति'],
        'accounts' => ['फीस कलेक्शन', 'फीस डिफॉल्टर', 'कलेक्शन रिपोर्ट', 'पेरोल सारांश', 'फीस एक्सपोर्ट'],
        'hr' => ['छुट्टी बैलेंस', 'स्टाफ उपस्थिति', 'पेरोल रिकॉर्ड', 'नोटिस दिखाओ', 'शिकायत स्थिति'],
        'canteen' => ['कैंटीन मेनू', 'बिक्री सारांश', 'वॉलेट बैलेंस', 'रिचार्ज सहायता', 'नोटिस दिखाओ'],
        'conductor' => ['मेरा ट्रांसपोर्ट', 'ट्रांसपोर्ट उपस्थिति', 'छात्र सूची', 'रूट विवरण', 'नोटिस दिखाओ'],
        'driver' => ['मेरा ट्रांसपोर्ट', 'रूट विवरण', 'मेरा वाहन', 'नोटिस दिखाओ'],
        'librarian' => ['लाइब्रेरी किताबें', 'किताब इश्यू', 'किताब रिटर्न', 'ओवरड्यू सूची', 'नोटिस दिखाओ'],
        'guest' => ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश', 'कैंटीन मेनू'],
    ],
    'as' => [
        'superadmin' => ['ডেশ্বব\'ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি', 'মাছুল সংগ্ৰহ', 'পৰীক্ষাৰ সময়', 'নোটিছ দেখুওৱা'],
        'admin' => ['ডেশ্বব\'ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি', 'মাছুল সংগ্ৰহ', 'পৰীক্ষাৰ সময়', 'নোটিছ দেখুওৱা'],
        'teacher' => ['উপস্থিতি চিহ্নিত কৰক', 'গৃহকাৰ্য দেখুওৱা', 'পৰীক্ষাৰ সময়', 'ৰুটিন দেখুওৱা', 'মন্তব্য সহায়'],
        'student' => ['মোৰ উপস্থিতি', 'মোৰ গৃহকাৰ্য', 'মোৰ পৰীক্ষা', 'মোৰ ফলাফল', 'নোটিছ দেখুওৱা'],
        'parent' => ['শিশুৰ উপস্থিতি', 'শিশুৰ মাছুল', 'শিশুৰ ফলাফল', 'নোটিছ দেখুওৱা', 'অভিযোগৰ অৱস্থা'],
        'accounts' => ['মাছুল সংগ্ৰহ', 'বকেয়া মাছুল', 'সংগ্ৰহ প্ৰতিবেদন', 'পেৰ\'ল সাৰাংশ', 'মাছুল এক্সপ\'ৰ্ট'],
        'hr' => ['ছুটিৰ জেৰ', 'কৰ্মচাৰী উপস্থিতি', 'পেৰ\'ল ৰেকৰ্ড', 'নোটিছ দেখুওৱা', 'অভিযোগৰ অৱস্থা'],
        'canteen' => ['কেন্টিন মেনু', 'বিক্ৰী সাৰাংশ', 'ৱালেট জেৰ', 'ৰিচাৰ্জ সহায়', 'নোটিছ দেখুওৱা'],
        'conductor' => ['মোৰ পৰিবহণ', 'পৰিবহণ উপস্থিতি', 'ছাত্ৰ তালিকা', 'ৰুটৰ বিৱৰণ', 'নোটিছ দেখুওৱা'],
        'driver' => ['মোৰ পৰিবহণ', 'ৰুটৰ বিৱৰণ', 'মোৰ বাহন', 'নোটিছ দেখুওৱা'],
        'librarian' => ['লাইব্ৰেৰী কিতাপ', 'কিতাপ ইস্যু', 'কিতাপ ৰিটাৰ্ন', 'অতিথি তালিকা', 'নোটিছ দেখুওৱা'],
        'guest' => ['ডেশ্বব\'ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি', 'কেন্টিন মেনু'],
    ],
];

// Get welcome message for role and language
$roleWelcome = $welcome[$language][$role] ?? $welcome['en']['guest'];
$actions = $quickActions[$language][$role] ?? $quickActions['en']['guest'];

// Log chatbot bootstrap access
$userId = get_current_user_id();
if (db_table_exists('chatbot_logs')) {
    db_query(
        "INSERT INTO chatbot_logs (user_id, user_role, message, language, intent, response, session_id) 
         VALUES (?, ?, 'bootstrap', ?, 'bootstrap', 'welcome_message', ?)",
        [$userId, $role, $language, session_id()]
    );
}

json_response([
    'welcome' => $roleWelcome,
    'quickActions' => $actions,
    'suggestions' => ['Show dashboard', 'Show notices', 'Help'],
    'languages' => $languages,
    'defaultLanguage' => 'en',
    'currentLanguage' => $language,
    'role' => $role,
]);
