const fs = require('fs');
const path = require('path');

const kbPath = path.join(__dirname, 'server', 'ai', 'kb', 'curatedKnowledgeBase.json');
const raw = fs.readFileSync(kbPath, 'utf8');
const entries = JSON.parse(raw);

console.log(`Current entries: ${entries.length}`);

const newEntries = [
  {
    "title": "Assamese: ছাত্ৰ ভৰ্তি প্ৰক্ৰিয়া",
    "content": "নতুন ছাত্ৰক ভৰ্তি কৰিবলৈ Students Page লৈ যাওক। '+ Admit Student' বুটামত ক্লিক কৰক। নাম, জন্ম তাৰিখ, লিংগ, শ্ৰেণী, অভিভাৱকৰ ফোন নম্বৰ পূৰণ কৰক। TC আৰু জন্ম প্ৰমাণপত্ৰ আপল'ড কৰক। 'Admit Student' ত ক্লিক কৰক। ভৰ্তি নম্বৰ স্বচালিতভাৱে সৃষ্টি হ'ব।",
    "tags": ["assamese", "admission", "student", "ভৰ্তি", "ছাত্ৰ"],
    "module": "students",
    "audience": ["superadmin", "accounts", "teacher"],
    "language": "as",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Assamese: উপস্থিতি ব্যৱস্থাপনা",
    "content": "উপস্থিতি চিহ্নিত কৰিবলৈ Attendance Page লৈ যাওক। শ্ৰেণী বাছনি কৰক। তাৰিখ বাছনি কৰক। প্ৰতিজন ছাত্ৰক উপস্থিত, অনুপস্থিত, পলমকৈ, বা অৰ্ধ-দিন হিচাপে চিহ্নিত কৰক। 'Save Attendance' ত ক্লিক কৰক। অনুপস্থিত ছাত্ৰৰ অভিভাৱকলৈ এছএমএছ পঠিওৱা হয়।",
    "tags": ["assamese", "attendance", "উপস্থিতি", "present", "absent"],
    "module": "attendance",
    "audience": ["teacher", "superadmin"],
    "language": "as",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Assamese: মাচুল সংগ্ৰহ প্ৰক্ৰিয়া",
    "content": "মাচুল সংগ্ৰহ কৰিবলৈ Fee Page লৈ যাওক। '+ Collect Fee' ত ক্লিক কৰক। ছাত্ৰ বাছনি কৰক। মাচুলৰ প্ৰকাৰ বাছনি কৰক। পৰিমাণ প্ৰৱেশ কৰক। নগদ, কাৰ্ড, UPI, বেংক স্থানান্তৰ, বা চেক বাছনি কৰক। 'Collect & Print Receipt' ত ক্লিক কৰক। ৰচিদ স্বচালিতভাৱে ডাউনল'ড হ'ব।",
    "tags": ["assamese", "fee", "মাচুল", "payment", "receipt"],
    "module": "fee",
    "audience": ["accounts", "superadmin"],
    "language": "as",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Assamese: পৰীক্ষা আৰু ফলাফল",
    "content": "পৰীক্ষাৰ সময়সূচী সৃষ্টি কৰিবলৈ Exams Page লৈ যাওক। 'Schedule Exam' ত ক্লিক কৰক। পৰীক্ষাৰ নাম, শ্ৰেণী, বিষয়, তাৰিখ, সময় পূৰণ কৰক। নম্বৰ প্ৰৱেশ কৰিবলৈ 'Enter Marks' ত ক্লিক কৰক। গ্ৰেড স্বচালিতভাৱে গণনা হ'ব। প্ৰতিবেদন পত্ৰ PDF হিচাপে ডাউনল'ড কৰিব পাৰি।",
    "tags": ["assamese", "exam", "পৰীক্ষা", "result", "ফলাফল"],
    "module": "exams",
    "audience": ["teacher", "superadmin"],
    "language": "as",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Assamese: পুস্তকালয় ব্যৱস্থাপনা",
    "content": "কিতাপ জাৰি কৰিবলৈ Library Page লৈ যাওক। কিতাপ বাছনি কৰক। 'Issue' ত ক্লিক কৰক। ছাত্ৰ বাছনি কৰক। নিৰ্ধাৰিত তাৰিখ নিৰ্ধাৰণ কৰক (ডিফল্ট: ১৪ দিন)। 'Issue Book' ত ক্লিক কৰক। কিতাপ ঘূৰাই দিবলৈ 'Return' ত ক্লিক কৰক। সময়সীমা পাৰ হ'লে জৰিমনা গণনা হ'ব।",
    "tags": ["assamese", "library", "পুস্তকালয়", "book", "issue"],
    "module": "library",
    "audience": ["teacher", "superadmin", "librarian"],
    "language": "as",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Hindi: छात्र प्रवेश प्रक्रिया",
    "content": "नए छात्र को भर्ती करने के लिए Students Page पर जाएं। '+ Admit Student' बटन पर क्लिक करें। नाम, जन्म तिथि, लिंग, कक्षा, अभिभावक फोन नंबर भरें। TC और जन्म प्रमाण पत्र अपलोड करें। 'Admit Student' पर क्लिक करें। प्रवेश संख्या स्वतः जनरेट होगी।",
    "tags": ["hindi", "admission", "student", "प्रवेश", "छात्र"],
    "module": "students",
    "audience": ["superadmin", "accounts", "teacher"],
    "language": "hi",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Hindi: उपस्थिति प्रबंधन",
    "content": "उपस्थिति चिह्नित करने के लिए Attendance Page पर जाएं। कक्षा चुनें। तारीख चुनें। प्रत्येक छात्र को उपस्थित, अनुपस्थित, देर से, या अर्धा-दिन के रूप में चिह्नित करें। 'Save Attendance' पर क्लिक करें। अनुपस्थित छात्रों के अभिभावकों को SMS भेजा जाता है।",
    "tags": ["hindi", "attendance", "उपस्थिति", "present", "absent"],
    "module": "attendance",
    "audience": ["teacher", "superadmin"],
    "language": "hi",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Hindi: शुल्क संग्रह प्रक्रिया",
    "content": "शुल्क संग्रह के लिए Fee Page पर जाएं। '+ Collect Fee' पर क्लिक करें। छात्र चुनें। शुल्क प्रकार चुनें। राशि दर्ज करें। नकद, कार्ड, UPI, बैंक ट्रांसफर या चेक चुनें। 'Collect & Print Receipt' पर क्लिक करें। रसीद स्वतः डाउनलोड होगी।",
    "tags": ["hindi", "fee", "शुल्क", "payment", "receipt"],
    "module": "fee",
    "audience": ["accounts", "superadmin"],
    "language": "hi",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Chatbot Multilingual Response Behavior",
    "content": "The chatbot should respond in the language the user types in. If the user types in Assamese, respond in Assamese. If the user types in Hindi, respond in Hindi. If the language is unclear, default to English. The chatbot should never mix languages within a single response.",
    "tags": ["chatbot", "multilingual", "language", "behavior", "response"],
    "module": "chatbot",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Chatbot Scope Limitations",
    "content": "The chatbot should answer questions about school policies, module navigation, and general how-to guidance. It should not expose personal data about other students or staff. It should not perform destructive actions without confirmation. It should direct users to the appropriate module for complex operations like fee collection or data entry.",
    "tags": ["chatbot", "scope", "limitations", "privacy", "behavior"],
    "module": "chatbot",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Chatbot Fallback Behavior",
    "content": "When the chatbot cannot understand a query, it should offer suggestions like 'Did you mean?' with 2-3 related topics. It should never respond with 'I don't know' without offering alternatives. The fallback should include quick action buttons for the most common modules: Admission, Attendance, Fee, Exam, Library.",
    "tags": ["chatbot", "fallback", "suggestions", "behavior", "ux"],
    "module": "chatbot",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Data Privacy and Chatbot",
    "content": "The chatbot should only return data that the authenticated user has permission to see. A student should only see their own records. A parent should only see their children's records. A teacher should only see their assigned classes. The chatbot enforces the same role-based access controls as the rest of the application.",
    "tags": ["chatbot", "privacy", "security", "role-based", "access"],
    "module": "chatbot",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Chatbot Offline Mode Guidance",
    "content": "When the server NLP engine is unreachable, the chatbot falls back to its local knowledge base. The local knowledge base contains policy answers and how-to guides but cannot query live database records. The chatbot should display a warning message when operating in offline mode so users know their answers may not reflect current data.",
    "tags": ["chatbot", "offline", "fallback", "knowledge-base", "warning"],
    "module": "chatbot",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Fee Defaulters Chatbot Guidance",
    "content": "When a user asks about fee defaulters, the chatbot should only show defaulter lists to users with accounts or superadmin roles. For parents, it should only show their own children's fee status. For students, it should redirect them to the fee payment page. The chatbot should never expose another family's financial information.",
    "tags": ["fee", "defaulters", "privacy", "role-based", "chatbot"],
    "module": "fee",
    "audience": ["accounts", "superadmin", "parent"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Attendance Query Role Restrictions",
    "content": "Attendance queries through the chatbot follow role-based access. Teachers can see attendance for their assigned classes. Parents can see their children's attendance. Students can see their own attendance. Accounts and HR can see aggregate reports. The chatbot should enforce these same restrictions and return 403 for unauthorized access attempts.",
    "tags": ["attendance", "role-based", "privacy", "access", "chatbot"],
    "module": "attendance",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Exam Results Chatbot Display Rules",
    "content": "The chatbot should display exam results only to the student themselves, their parent, or authorized staff. It should never show another student's results. The response format should include subject name, marks obtained, total marks, grade, and percentage. For bulk result queries, the chatbot should redirect to the Exams module.",
    "tags": ["exam", "results", "privacy", "role-based", "display"],
    "module": "exams",
    "audience": ["student", "parent", "teacher"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Library Fine Calculation Guidance",
    "content": "The chatbot should calculate library fines at 10 rupees per day overdue. The maximum fine is 100 rupees per book. The chatbot can tell users their current overdue books and total fine. Fine waivers require librarian approval and should be processed through the Library module, not the chatbot.",
    "tags": ["library", "fine", "calculation", "overdue", "policy"],
    "module": "library",
    "audience": ["student", "teacher", "librarian"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Transport Route Chatbot Queries",
    "content": "When users ask about bus routes, the chatbot should return the route name, stops, timing, driver name, and contact number. For parents, it should only show their children's assigned bus. For conductors and drivers, it should show their assigned route. The chatbot should not expose all route details to unauthorized users.",
    "tags": ["transport", "route", "bus", "privacy", "role-based"],
    "module": "transport",
    "audience": ["parent", "conductor", "driver", "superadmin"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Hostel Allocation Chatbot Guidance",
    "content": "The chatbot can answer questions about hostel room availability, room types, fee structure, and allocation process. It should not expose which student is in which room to unauthorized users. Only the hostel warden, superadmin, and the student's parent should see specific room assignments. General policy questions can be answered for all users.",
    "tags": ["hostel", "allocation", "room", "privacy", "policy"],
    "module": "hostel",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Payroll Chatbot Access Rules",
    "content": "The chatbot should only show payroll information to the staff member themselves, HR, accounts, or superadmin. A staff member can ask 'what is my salary' and receive their own payslip summary. HR can ask for aggregate payroll reports. The chatbot should never expose one employee's salary to another employee.",
    "tags": ["payroll", "salary", "privacy", "role-based", "access"],
    "module": "payroll",
    "audience": ["staff", "hr", "accounts", "superadmin"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  },
  {
    "title": "Complaint Status Chatbot Queries",
    "content": "Users can check the status of their own complaints through the chatbot. The response should include complaint ID, type, current status (pending, in-progress, resolved, escalated), and last update date. Users should not be able to see other users' complaints. Admin users can see all complaints with filtering.",
    "tags": ["complaint", "status", "privacy", "role-based", "tracking"],
    "module": "complaints",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Homework Chatbot Query Scope",
    "content": "Students can ask the chatbot about their pending homework. The response should include subject, description, due date, and attachment if any. Teachers can ask about homework assigned to their classes. Parents can ask about their children's homework. The chatbot should not expose another student's homework submissions or grades.",
    "tags": ["homework", "assignment", "privacy", "role-based", "scope"],
    "module": "homework",
    "audience": ["student", "teacher", "parent"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Notice Publishing Chatbot Queries",
    "content": "The chatbot can show recent notices to all authenticated users. Notices marked as urgent should be displayed first. The chatbot should respect notice targeting - if a notice is targeted to a specific role, only users with that role should see it. General notices are visible to all. The chatbot should not show draft or unpublished notices.",
    "tags": ["notice", "announcement", "targeting", "role-based", "visibility"],
    "module": "notices",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Chatbot Conversation Memory",
    "content": "The chatbot maintains short-term conversation context for up to 10 messages. This allows follow-up questions like 'what about fees?' after asking about attendance. Context expires after 1 hour of inactivity. The chatbot should use context to provide more relevant answers but should not store conversation history permanently beyond the logged queries.",
    "tags": ["chatbot", "context", "memory", "conversation", "behavior"],
    "module": "chatbot",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "normal"
  },
  {
    "title": "Chatbot Error Handling Behavior",
    "content": "When the chatbot encounters an error (database unreachable, NLP engine crash, etc.), it should display a friendly error message: 'I encountered an error processing your request. Please try again.' It should not expose stack traces or technical error details to users. Errors should be logged server-side for debugging. The chatbot should attempt to recover by falling back to the local knowledge base.",
    "tags": ["chatbot", "error", "handling", "fallback", "behavior"],
    "module": "chatbot",
    "audience": ["all"],
    "language": "en",
    "sourceType": "curated",
    "priority": "high"
  }
];

entries.push(...newEntries);
fs.writeFileSync(kbPath, JSON.stringify(entries, null, 2));
console.log(`Added ${newEntries.length} new curated KB entries. Total: ${entries.length} entries.`);
console.log('New entries include:');
console.log('- 5 Assamese language entries (admission, attendance, fee, exam, library)');
console.log('- 3 Hindi language entries (admission, attendance, fee)');
console.log('- 17 English policy/behavior entries (chatbot behavior, privacy, role-based access)');
