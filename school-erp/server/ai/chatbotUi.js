const LANGUAGE_OPTIONS = [
  { code: 'en', label: 'English' },
  { code: 'hi', label: 'Hindi' },
  { code: 'as', label: 'Assamese' },
];

function pickLanguage(language, copy) {
  return copy[language] || copy.en;
}

function normalizeRole(role) {
  return String(role || 'guest').toLowerCase();
}

const ROLE_WELCOME = {
  superadmin: {
    en: 'Hello. You can ask about students, fees, exams, notices, reports, payroll, transport, hostel, and system workflows.',
    hi: 'नमस्ते। आप छात्र, फीस, परीक्षा, नोटिस, रिपोर्ट, पेरोल, ट्रांसपोर्ट, हॉस्टल और सिस्टम वर्कफ़्लो के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি ছাত্ৰ, মাছুল, পৰীক্ষা, নোটিছ, প্ৰতিবেদন, পেৰ’ল, পৰিবহণ, হোষ্টেল আৰু চিষ্টেমৰ কাৰ্যপ্ৰবাহ বিষয়ে সোধিব পাৰে।',
  },
  admin: {
    en: 'Hello. You can ask about students, fees, exams, notices, reports, payroll, transport, hostel, and system workflows.',
    hi: 'नमस्ते। आप छात्र, फीस, परीक्षा, नोटिस, रिपोर्ट, पेरोल, ट्रांसपोर्ट, हॉस्टल और सिस्टम वर्कफ़्लो के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি ছাত্ৰ, মাছুল, পৰীক্ষা, নোটিছ, প্ৰতিবেদন, পেৰ’ল, পৰিবহণ, হোষ্টেল আৰু চিষ্টেমৰ কাৰ্যপ্ৰবাহ বিষয়ে সোধিব পাৰে।',
  },
  teacher: {
    en: 'Hello. Ask me about attendance, homework, exams, remarks, notices, routines, and your class-related work.',
    hi: 'नमस्ते। आप उपस्थिति, होमवर्क, परीक्षा, रिमार्क, नोटिस, रूटीन और अपनी कक्षा से जुड़े काम के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি উপস্থিতি, গৃহকাৰ্য, পৰীক্ষা, মন্তব্য, নোটিছ, ৰুটিন আৰু নিজৰ শ্ৰেণীৰ কামৰ বিষয়ে সোধিব পাৰে।',
  },
  student: {
    en: 'Hello. Ask me about your attendance, homework, exams, results, notices, library, hostel, or transport details.',
    hi: 'नमस्ते। आप अपनी उपस्थिति, होमवर्क, परीक्षा, परिणाम, नोटिस, लाइब्रेरी, हॉस्टल या ट्रांसपोर्ट के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি নিজৰ উপস্থিতি, গৃহকাৰ্য, পৰীক্ষা, ফলাফল, নোটিছ, লাইব্ৰেৰী, হোষ্টেল বা পৰিবহণৰ বিষয়ে সোধিব পাৰে।',
  },
  parent: {
    en: 'Hello. Ask me about your child attendance, fee status, results, homework, notices, complaints, or transport.',
    hi: 'नमस्ते। आप अपने बच्चे की उपस्थिति, फीस स्थिति, परिणाम, होमवर्क, नोटिस, शिकायत या ट्रांसपोर्ट के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি নিজৰ লিংক কৰা শিশুৰ উপস্থিতি, মাছুল, ফলাফল, গৃহকাৰ্য, নোটিছ, অভিযোগ বা পৰিবহণৰ বিষয়ে সোধিব পাৰে।',
  },
  accounts: {
    en: 'Hello. Ask me about fee collection, defaulters, collection reports, exports, and payroll summaries.',
    hi: 'नमस्ते। आप फीस कलेक्शन, डिफॉल्टर, कलेक्शन रिपोर्ट, एक्सपोर्ट और पेरोल सारांश के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি মাছুল সংগ্ৰহ, বকেয়া তালিকা, সংগ্ৰহ প্ৰতিবেদন, এক্সপ’ৰ্ট আৰু পেৰ’ল সাৰাংশৰ বিষয়ে সোধিব পাৰে।',
  },
  hr: {
    en: 'Hello. Ask me about staff attendance, leave balance, payroll records, notices, complaints, and staff workflows.',
    hi: 'नमस्ते। आप स्टाफ उपस्थिति, छुट्टी बैलेंस, पेरोल रिकॉर्ड, नोटिस, शिकायत और HR वर्कफ़्लो के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি কৰ্মচাৰীৰ উপস্থিতি, ছুটিৰ জেৰ, পেৰ’ল ৰেকৰ্ড, নোটিছ, অভিযোগ আৰু HR কাৰ্যপ্ৰবাহৰ বিষয়ে সোধিব পাৰে।',
  },
  canteen: {
    en: 'Hello. Ask me about canteen menu, sales, wallet balance, recharge help, and notice updates.',
    hi: 'नमस्ते। आप कैंटीन मेनू, बिक्री, वॉलेट बैलेंस, रिचार्ज और नोटिस के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি কেন্টিন মেনু, বিক্ৰী, ৱালেট জেৰ, ৰিচাৰ্জ আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
  },
  conductor: {
    en: 'Hello. Ask me about your route, transport attendance, student manifest, and notices.',
    hi: 'नमस्ते। आप अपने रूट, ट्रांसपोर्ट उपस्थिति, छात्र सूची और नोटिस के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি নিজৰ ৰুট, পৰিবহণ উপস্থিতি, ছাত্ৰ তালিকা আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
  },
  driver: {
    en: 'Hello. Ask me about your route, assigned vehicle, transport details, and notices.',
    hi: 'नमस्ते। आप अपने रूट, वाहन, ट्रांसपोर्ट विवरण और नोटिस के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি নিজৰ ৰুট, বাহন, পৰিবহণৰ বিৱৰণ আৰু নোটিছৰ বিষয়ে সোধিব পাৰে।',
  },
  staff: {
    en: 'Hello. Ask me about leave balance, notices, complaints, library help, and general school workflows.',
    hi: 'नमस्ते। आप छुट्टी बैलेंस, नोटिस, शिकायत, लाइब्रेरी और सामान्य स्कूल वर्कफ़्लो के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি ছুটিৰ জেৰ, নোটিছ, অভিযোগ, লাইব্ৰেৰী আৰু সাধাৰণ বিদ্যালয় কাৰ্যপ্ৰবাহৰ বিষয়ে সোধিব পাৰে।',
  },
  guest: {
    en: 'Hello. Ask me about the School ERP modules and common workflows.',
    hi: 'नमस्ते। आप स्कूल ERP मॉड्यूल और सामान्य प्रक्रियाओं के बारे में पूछ सकते हैं।',
    as: 'নমস্কাৰ। আপুনি স্কুল ERP মডিউল আৰু সাধাৰণ প্ৰক্ৰিয়াৰ বিষয়ে সোধিব পাৰে।',
  },
};

const ROLE_ACTIONS = {
  superadmin: {
    en: ['Show dashboard', 'Student admission process', 'Fee collection workflow', 'Exam schedule', 'Show notices'],
    hi: ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश प्रक्रिया', 'फीस कलेक्शन प्रक्रिया', 'परीक्षा समय-सारणी', 'नोटिस दिखाओ'],
    as: ['ডেশ্বব’ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি প্ৰক্ৰিয়া', 'মাছুল সংগ্ৰহ প্ৰক্ৰিয়া', 'পৰীক্ষাৰ সময়সূচী', 'নোটিছ দেখুওৱা'],
  },
  admin: {
    en: ['Show dashboard', 'Student admission process', 'Fee collection workflow', 'Exam schedule', 'Show notices'],
    hi: ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश प्रक्रिया', 'फीस कलेक्शन प्रक्रिया', 'परीक्षा समय-सारणी', 'नोटिस दिखाओ'],
    as: ['ডেশ্বব’ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি প্ৰক্ৰিয়া', 'মাছুল সংগ্ৰহ প্ৰক্ৰিয়া', 'পৰীক্ষাৰ সময়সূচী', 'নোটিছ দেখুওৱা'],
  },
  teacher: {
    en: ['Mark attendance', 'Show homework', 'Exam timetable', 'Show routine', 'Remarks help'],
    hi: ['उपस्थिति दर्ज करें', 'होमवर्क दिखाओ', 'परीक्षा समय-सारणी', 'रूटीन दिखाओ', 'रिमार्क सहायता'],
    as: ['উপস্থিতি চিহ্নিত কৰক', 'গৃহকাৰ্য দেখুওৱা', 'পৰীক্ষাৰ সময়সূচী', 'ৰুটিন দেখুওৱা', 'মন্তব্য সহায়'],
  },
  student: {
    en: ['My attendance', 'My homework', 'My exams', 'My results', 'Show notices'],
    hi: ['मेरी उपस्थिति', 'मेरा होमवर्क', 'मेरी परीक्षा', 'मेरा परिणाम', 'नोटिस दिखाओ'],
    as: ['মোৰ উপস্থিতি', 'মোৰ গৃহকাৰ্য', 'মোৰ পৰীক্ষা', 'মোৰ ফলাফল', 'নোটিছ দেখুওৱা'],
  },
  parent: {
    en: ['Child attendance', 'Child fee status', 'Child results', 'Show notices', 'Complaint status'],
    hi: ['बच्चे की उपस्थिति', 'बच्चे की फीस स्थिति', 'बच्चे का परिणाम', 'नोटिस दिखाओ', 'शिकायत स्थिति'],
    as: ['শিশুৰ উপস্থিতি', 'শিশুৰ মাছুল অৱস্থা', 'শিশুৰ ফলাফল', 'নোটিছ দেখুওৱা', 'অভিযোগৰ অৱস্থা'],
  },
  accounts: {
    en: ['Fee collection workflow', 'Fee defaulters', 'Collection report', 'Payroll summary', 'Export fees'],
    hi: ['फीस कलेक्शन प्रक्रिया', 'फीस डिफॉल्टर', 'कलेक्शन रिपोर्ट', 'पेरोल सारांश', 'फीस एक्सपोर्ट'],
    as: ['মাছুল সংগ্ৰহ প্ৰক্ৰিয়া', 'বকেয়া মাছুল', 'সংগ্ৰহ প্ৰতিবেদন', 'পেৰ’ল সাৰাংশ', 'মাছুল এক্সপ’ৰ্ট'],
  },
  hr: {
    en: ['Leave balance', 'Staff attendance', 'Payroll records', 'Show notices', 'Complaint status'],
    hi: ['छुट्टी बैलेंस', 'स्टाफ उपस्थिति', 'पेरोल रिकॉर्ड', 'नोटिस दिखाओ', 'शिकायत स्थिति'],
    as: ['ছুটিৰ জেৰ', 'কৰ্মচাৰী উপস্থিতি', 'পেৰ’ল ৰেকৰ্ড', 'নোটিছ দেখুওৱা', 'অভিযোগৰ অৱস্থা'],
  },
  canteen: {
    en: ['Canteen menu', 'Sales summary', 'Wallet balance', 'Recharge help', 'Show notices'],
    hi: ['कैंटीन मेनू', 'बिक्री सारांश', 'वॉलेट बैलेंस', 'रिचार्ज सहायता', 'नोटिस दिखाओ'],
    as: ['কেন্টিন মেনু', 'বিক্ৰী সাৰাংশ', 'ৱালেট জেৰ', 'ৰিচাৰ্জ সহায়', 'নোটিছ দেখুওৱা'],
  },
  conductor: {
    en: ['My transport', 'Transport attendance', 'Student manifest', 'Route details', 'Show notices'],
    hi: ['मेरा ट्रांसपोर्ट', 'ट्रांसपोर्ट उपस्थिति', 'छात्र सूची', 'रूट विवरण', 'नोटिस दिखाओ'],
    as: ['মোৰ পৰিবহণ', 'পৰিবহণ উপস্থিতি', 'ছাত্ৰ তালিকা', 'ৰুটৰ বিৱৰণ', 'নোটিছ দেখুওৱা'],
  },
  driver: {
    en: ['My transport', 'Route details', 'Assigned vehicle', 'Show notices'],
    hi: ['मेरा ट्रांसपोर्ट', 'रूट विवरण', 'मेरा वाहन', 'नोटिस दिखाओ'],
    as: ['মোৰ পৰিবহণ', 'ৰুটৰ বিৱৰণ', 'মোৰ বাহন', 'নোটিছ দেখুওৱা'],
  },
  staff: {
    en: ['Leave balance', 'Show notices', 'Complaint status', 'Library help'],
    hi: ['छुट्टी बैलेंस', 'नोटिस दिखाओ', 'शिकायत स्थिति', 'लाइब्रेरी सहायता'],
    as: ['ছুটিৰ জেৰ', 'নোটিছ দেখুওৱা', 'অভিযোগৰ অৱস্থা', 'লাইব্ৰেৰী সহায়'],
  },
  guest: {
    en: ['Show dashboard', 'Student admission process', 'Canteen menu'],
    hi: ['डैशबोर्ड दिखाओ', 'छात्र प्रवेश प्रक्रिया', 'कैंटीन मेनू'],
    as: ['ডেশ্বব’ৰ্ড দেখুওৱা', 'ছাত্ৰ ভৰ্তি প্ৰক্ৰিয়া', 'কেন্টিন মেনু'],
  },
};

const ROLE_SUGGESTIONS = {
  teacher: {
    en: ['Show homework', 'Exam timetable', 'Show attendance report'],
    hi: ['होमवर्क दिखाओ', 'परीक्षा समय-सारणी', 'उपस्थिति रिपोर्ट दिखाओ'],
    as: ['গৃহকাৰ্য দেখুওৱা', 'পৰীক্ষাৰ সময়সূচী', 'উপস্থিতি প্ৰতিবেদন দেখুওৱা'],
  },
  student: {
    en: ['My attendance history', 'My results', 'Library help'],
    hi: ['मेरी उपस्थिति हिस्ट्री', 'मेरा परिणाम', 'लाइब्रेरी सहायता'],
    as: ['মোৰ উপস্থিতি ইতিহাস', 'মোৰ ফলাফল', 'লাইব্ৰেৰী সহায়'],
  },
  parent: {
    en: ['Child attendance history', 'Child fee history', 'Show notices'],
    hi: ['बच्चे की उपस्थिति हिस्ट्री', 'बच्चे की फीस हिस्ट्री', 'नोटिस दिखाओ'],
    as: ['শিশুৰ উপস্থিতি ইতিহাস', 'শিশুৰ মাছুল ইতিহাস', 'নোটিছ দেখুওৱা'],
  },
  default: {
    en: ['Show dashboard', 'Show notices', 'Help'],
    hi: ['डैशबोर्ड दिखाओ', 'नोटिस दिखाओ', 'सहायता'],
    as: ['ডেশ্বব’ৰ্ড দেখুওৱা', 'নোটিছ দেখুওৱা', 'সহায়'],
  },
};

function getChatbotBootstrap({ language = 'en', role } = {}) {
  const safeRole = normalizeRole(role);
  const welcomeCopy = ROLE_WELCOME[safeRole] || ROLE_WELCOME.guest;
  const quickActions = pickLanguage(language, ROLE_ACTIONS[safeRole] || ROLE_ACTIONS.guest);
  const suggestions = pickLanguage(language, ROLE_SUGGESTIONS[safeRole] || ROLE_SUGGESTIONS.default);

  return {
    welcome: pickLanguage(language, welcomeCopy),
    quickActions,
    suggestions,
    languages: LANGUAGE_OPTIONS,
    defaultLanguage: 'en',
  };
}

module.exports = {
  LANGUAGE_OPTIONS,
  pickLanguage,
  getChatbotBootstrap,
};
