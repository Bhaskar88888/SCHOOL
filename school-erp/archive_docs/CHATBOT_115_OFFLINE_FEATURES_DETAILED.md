# 🤖 115 Offline-Addable Chatbot Features — Detailed Plan

**Date:** April 6, 2026  
**Constraint:** ALL features work OFFLINE — no external APIs, no GPT, no internet needed  
**Data Source:** Local MongoDB + NLP engine + Knowledge Base + React UI only

---

## PHASE 1: FIX CRITICAL BUGS (6 Features)

### Feature 1: Fix Canteen `available` → `isAvailable` Field Mismatch
- **File:** `server/ai/actions.js` line 260
- **Problem:** `CanteenItem.find({ available: true })` queries a field that doesn't exist. The model defines `isAvailable`, not `available`. Result: menu always returns empty.
- **Fix:** Change `{ available: true }` to `{ isAvailable: true }`
- **Code change:** 1 line
- **Test:** Ask bot "what food is available" → should return actual menu items

### Feature 2: Fix Menu Item `itemName` → `name` Field Mismatch
- **File:** `server/ai/actions.js` line 262
- **Problem:** `i.itemName || i.name` — the model defines `name`, not `itemName`. The fallback works but is sloppy.
- **Fix:** Change to `i.name` directly
- **Code change:** 1 line

### Feature 3: Fix Entity Extraction `entity.option` → `entity.utteranceText`
- **File:** `server/ai/nlpEngine.js` line 507
- **Problem:** The NLP engine reads `entity.option` to get extracted student/book names. But node-nlp returns the actual matched text in `entity.utteranceText`, not `entity.option`. Named entities (student names, book titles) NEVER resolve correctly.
- **Fix:** Change `entity.option` to `entity.utteranceText` in the entity extraction loop
- **Code change:** 1 line
- **Impact:** Bot will finally recognize student names, book titles, staff names from natural language

### Feature 4: Fix NLP Entity Duplication Memory Leak
- **File:** `server/ai/nlpEngine.js` lines 145-177
- **Problem:** `addDynamicEntities()` runs every time the model retrains or entities refresh. But node-nlp has no `removeNamedEntity()` method. Entities accumulate forever — after 10 retrains, there are 10 copies of every student name. Bot gets slower and may match wrong names.
- **Fix:** Create a NEW `NlpManager` instance before each retrain. Dispose the old one. This clears all accumulated entities.
- **Code change:** ~10 lines — wrap retrain in `manager = new NlpManager({...})`
- **Impact:** Stops memory leak, keeps bot fast and accurate

### Feature 5: Add Server-Fallback Source Indicator
- **File:** `client/src/components/Chatbot.jsx` line 154
- **Problem:** When the server NLP engine fails (or returns empty), the bot silently falls back to the local offline knowledge base. The user has NO idea they're getting stale/generic answers instead of real database queries.
- **Fix:** Add a small italic badge below the response: `⚠️ Using offline knowledge base` when the fallback path is taken.
- **Code change:** ~5 lines in the catch block
- **Impact:** User trust — they know when bot is using live data vs offline cache

### Feature 6: Fix Mobile Width Overflow
- **File:** `client/src/components/Chatbot.jsx` line 237
- **Problem:** Fixed `w-96` (384px) width. On phones < 400px wide, the chatbot window is partially off-screen.
- **Fix:** Change `w-96` to `w-[min(24rem,calc(100vw-3rem))]`
- **Code change:** 1 CSS class
- **Impact:** Works on all phones including budget Android devices

---

## PHASE 2: ADD 27 NEW INTENT HANDLERS (Week 2-3)

Each intent = NLP training documents (in `nlpEngine.js`) + Action handler (in `actions.js`) + KB entry (in `scanner.js`)

### Feature 7: `homework.list` — Show User's Homework
- **What it does:** Queries the `Homework` model for the current user's class. Returns pending homework with subject, description, due date.
- **NLP phrases (EN):** "show my homework", "pending assignments", "what homework do I have", "homework list", "my assignments"
- **NLP phrases (AS):** "মোৰ গৃহকাম দেখুৱাওক", "বাকী থকা গৃহকাম", "আজিৰ গৃহকাম কি"
- **Action handler:**
  ```js
  'homework.list': async (entities, context, userId) => {
    const student = await getStudentByUserId(userId);
    const homework = await Homework.find({ classId: student.classId, status: 'assigned' })
      .sort({ dueDate: 1 }).limit(10);
    // Format and return list
  }
  ```
- **Response:** "📝 **Your Pending Homework:**\n\n• Math — Chapter 5 exercises (Due: Tomorrow)\n• English — Essay on 'My School' (Due: Friday)"

### Feature 8: `homework.pending` — Show Overdue Assignments
- **What it does:** Queries `Homework` where `dueDate < today` AND status is still 'assigned' (not submitted).
- **NLP phrases:** "overdue homework", "late assignments", "homework I missed"
- **Action handler:** Filters by `dueDate < new Date()` and `status === 'assigned'`
- **Response:** "⚠️ **Overdue Homework:**\n\n• Science — Lab report (Due: 3 days ago)\n• History — Chapter 4 summary (Due: 1 week ago)"

### Feature 9: `routine.view` — Show User's Class Timetable
- **What it does:** Queries `Routine` model by user's classId. Returns today's schedule or full week timetable.
- **NLP phrases:** "show my timetable", "today's schedule", "what's my routine", "class timetable"
- **NLP phrases (AS):** "আজিৰ ৰুটিন", "সময়সূচী দেখুৱাওক", "আজিৰ সময়সূচী কি"
- **Action handler:**
  ```js
  'routine.view': async (entities, context, userId) => {
    const student = await getStudentByUserId(userId);
    const routine = await Routine.findOne({ classId: student.classId });
    const today = getDayName(); // e.g., "Monday"
    return { message: formatDaySchedule(routine.timetable[today]) };
  }
  ```
- **Response:** "📅 **Today's Schedule (Monday):**\n\n1. 8:00 AM — Math\n2. 9:00 AM — English\n3. 10:00 AM — Break\n4. 10:30 AM — Science"

### Feature 10: `notice.list` — List Recent Notices
- **What it does:** Queries `Notice` model, sorted by date, filtered by user's role. Returns 5-10 most recent.
- **NLP phrases:** "show notices", "recent notices", "new notices", "any announcements"
- **NLP phrases (AS):** "জাননী দেখুৱাওক", "শেহতীয়া জাননী", "নতুন জাননী"
- **Action handler:**
  ```js
  'notice.list': async (entities, context, userId) => {
    const user = await getUserById(userId);
    const notices = await Notice.find({
      $or: [{ target: 'all' }, { target: user.role }]
    }).sort({ createdAt: -1 }).limit(5);
  }
  ```
- **Response:** "📋 **Recent Notices:**\n\n• 📌 School closed on Friday for Bihu\n• 📌 Exam schedule for Class 10 released\n• 📌 Parent-teacher meeting on 15th April"

### Feature 11: `notice.detail` — Show Specific Notice
- **What it does:** Takes a notice ID or keyword, fetches full notice content.
- **NLP phrases:** "read notice about sports", "open the bihu notice", "notice details"
- **Action handler:** Queries `Notice.findOne({ title: /keyword/i })` or `Notice.findById(id)`
- **Response:** Full notice text with title, date, content, author

### Feature 12: `complaint.status` — Check Complaint Progress
- **What it does:** Queries `Complaint` model by userId. Returns all complaints with their current status (pending/resolved/escalated).
- **NLP phrases:** "complaint status", "status of my complaint", "has my complaint been resolved"
- **NLP phrases (AS):** "অভিযোগৰ স্থিতি", "মোৰ অভিযোগৰ কি হ'ল"
- **Action handler:**
  ```js
  'complaint.status': async (entities, context, userId) => {
    const complaints = await Complaint.find({ filedBy: userId })
      .sort({ createdAt: -1 }).limit(5);
    // Format with status indicators: 🟡 Pending, 🟢 Resolved, 🔴 Escalated
  }
  ```
- **Response:** "📢 **Your Complaints:**\n\n• 🟡 Canteen food quality — In Progress\n• 🟢 Library book damaged — Resolved on 2nd April"

### Feature 13: `complaint.new` — Guide Through Complaint Filing
- **What it does:** Interactive multi-step flow. Bot asks: category → description → priority → confirms → creates complaint record.
- **NLP phrases:** "file complaint", "submit complaint", "I want to complain", "raise an issue"
- **Action handler:** Uses conversation context to collect data step-by-step, then `Complaint.create()`
- **Response:** "📢 **Filing a Complaint**\n\nStep 1: What type of complaint?\n• Infrastructure\n• Staff behavior\n• Canteen\n• Transport\n• Other"

### Feature 14: `attendance.my` — Return User's Attendance %
- **What it does:** Queries `Attendance` for the user's studentId. Calculates percentage: (present / total) × 100.
- **NLP phrases:** "my attendance", "my attendance percentage", "how is my attendance"
- **NLP phrases (AS):** "মোৰ উপস্থিতি", "মোৰ উপস্থিতি শতকৰা হাৰ কিমান"
- **Action handler:**
  ```js
  'attendance.my': async (entities, context, userId) => {
    const student = await getStudentByUserId(userId);
    const total = await Attendance.countDocuments({ studentId: student._id });
    const present = await Attendance.countDocuments({ studentId: student._id, status: 'present' });
    const pct = total > 0 ? Math.round((present / total) * 100) : 0;
    // Include warning if below 75%
  }
  ```
- **Response:** "📊 **Your Attendance:**\n\n• Present: 85 days\n• Absent: 10 days\n• Attendance: **89%** ✅\n\n✅ You meet the 75% minimum requirement."

### Feature 15: `attendance.history` — Historical Attendance
- **What it does:** Queries `Attendance` with date range filter. Shows monthly or weekly breakdown.
- **NLP phrases:** "attendance last month", "attendance this week", "my attendance history"
- **NLP phrases (AS):** "যোৱা মাহৰ উপস্থিতি", "এই সপ্তাহৰ উপস্থিতি"
- **Action handler:** Accepts date range params, groups by month/week
- **Response:** "📅 **Attendance — March 2026:**\n\n• Present: 22 days\n• Absent: 3 days\n• Late: 2 days\n• Percentage: 85%"

### Feature 16: `fee.my` — Personal Fee Status
- **What it does:** Queries `FeePayment` + `FeeStructure` for the student. Shows total due, paid, pending, last payment date.
- **NLP phrases:** "my fee status", "how much fee do I owe", "fee balance", "pending fees"
- **NLP phrases (AS):** "মোৰ মাচুলৰ স্থিতি", "মোৰ কিমান মাচুল বাকী"
- **Action handler:**
  ```js
  'fee.my': async (entities, context, userId) => {
    const student = await getStudentByUserId(userId);
    const totalFee = await FeeStructure.findOne({ classId: student.classId });
    const paid = await FeePayment.find({ studentId: student._id });
    const totalPaid = paid.reduce((sum, p) => sum + p.amountPaid, 0);
    const pending = totalFee.totalAmount - totalPaid;
  }
  ```
- **Response:** "💰 **Your Fee Status:**\n\n• Total Fee: ₹25,000\n• Paid: ₹20,000\n• **Pending: ₹5,000**\n\nLast payment: ₹5,000 on 15th March (Cash)"

### Feature 17: `exam.my` — User's Upcoming Exams
- **What it does:** Queries `Exam` by user's classId where `date >= today`. Shows next 5 exams.
- **NLP phrases:** "my exams", "upcoming exams", "exam schedule", "when is my next exam"
- **NLP phrases (AS):** "মোৰ পৰীক্ষা", "আগন্তুক পৰীক্ষা", "পৰীক্ষাৰ সময়সূচী"
- **Action handler:** `Exam.find({ classId, date: { $gte: today } }).sort({ date: 1 }).limit(5)`
- **Response:** "📝 **Your Upcoming Exams:**\n\n• Math — 10th April, 9:00 AM\n• English — 12th April, 9:00 AM\n• Science — 15th April, 9:00 AM"

### Feature 18: `exam.results` — User's Exam Results
- **What it does:** Queries `ExamResult` by studentId. Shows recent results with grades.
- **NLP phrases:** "my results", "exam results", "my marks", "grade card"
- **NLP phrases (AS):** "মোৰ ফলাফল", "পৰীক্ষাৰ ফলাফল", "মোৰ নম্বৰ"
- **Action handler:** `ExamResult.find({ studentId }).populate('exam').sort({ examDate: -1 }).limit(10)`
- **Response:** "📊 **Your Recent Results:**\n\n• Math — 85/100 (A)\n• English — 78/100 (B+)\n• Science — 92/100 (A+)"

### Feature 19: `library.my` — Books I've Borrowed
- **What it does:** Queries `LibraryTransaction` by studentId where status = 'BORROWED'. Shows currently held books with due dates.
- **NLP phrases:** "my books", "books I have borrowed", "library books with me"
- **NLP phrases (AS):** "মোৰ কিতাপ", "মই লোৱা কিতাপ"
- **Action handler:**
  ```js
  'library.my': async (entities, context, userId) => {
    const student = await getStudentByUserId(userId);
    const books = await LibraryTransaction.find({ studentId: student._id, status: 'BORROWED' })
      .populate('bookId');
  }
  ```
- **Response:** "📖 **Books With You:**\n\n• 'Mathematics Class 10' — Due: 12th April\n• 'English Grammar' — Due: 15th April"

### Feature 20: `library.overdue` — Overdue Books + Fines
- **What it does:** Queries `LibraryTransaction` where `status = 'BORROWED'` AND `dueDate < today`. Calculates fine (₹1/day).
- **NLP phrases:** "overdue books", "books I haven't returned", "library fine"
- **NLP phrases (AS):** "সময়সীমা পাৰ হোৱা কিতাপ", "মোৰ জৰিমনা"
- **Action handler:** Filters by `dueDate < new Date()`, calculates `daysOverdue × ₹1`
- **Response:** "⚠️ **Overdue Books:**\n\n• 'Physics Textbook' — 5 days overdue → **Fine: ₹5**\n\n⚠️ Total Fine: ₹5"

### Feature 21: `canteen.recharge` — Wallet Recharge Guide
- **What it does:** Static guide explaining how to recharge the canteen wallet. No database query needed.
- **NLP phrases:** "recharge wallet", "add money to wallet", "canteen balance"
- **NLP phrases (AS):** "ৱালেট ৰিচাৰ্জ", "ৱালেটত ধন ভৰাওক"
- **Response:** "💰 **Recharge Canteen Wallet:**\n\n1. Go to Fee Page → Canteen Wallet\n2. Enter amount\n3. Pay via Cash/Card/UPI\n4. Balance updates instantly\n\n💡 Parents can also recharge from home via the Parent Portal."

### Feature 22: `hostel.my` — My Room Details
- **What it does:** Queries `HostelAllocation` by studentId. Returns room number, type, roommate, warden contact.
- **NLP phrases:** "my hostel", "my room", "hostel details", "room details"
- **NLP phrases (AS):** "মোৰ হোষ্টেল", "মোৰ কোঠা", "কোঠাৰ বিৱৰণ"
- **Action handler:** `HostelAllocation.findOne({ studentId }).populate('roomId')`
- **Response:** "🏠 **Your Hostel Details:**\n\n• Room: Block A, Room 205\n• Type: Double Sharing\n• Roommate: Rajesh Kumar\n• Warden: Mr. Sharma (Ph: 9876543210)"

### Feature 23: `transport.my` — My Bus Route
- **What it does:** Queries `TransportVehicle` where students array includes the user's studentId. Returns bus number, route, driver, timing.
- **NLP phrases:** "my bus", "my transport", "bus details", "my route"
- **NLP phrases (AS):** "মোৰ বাছ", "মোৰ পৰিবহণ", "বাসৰ বিৱৰণ"
- **Action handler:** `TransportVehicle.findOne({ students: studentId }).populate('driverId')`
- **Response:** "🚌 **Your Bus Details:**\n\n• Bus: BUS-101 (MH01AB1234)\n• Route: Dighalipukhuri → School\n• Driver: Ramesh (Ph: 9876543210)\n• Pickup: 7:15 AM at Dighalipukhuri Stop"

### Feature 24: `leave.balance` — Remaining Leave Counts
- **What it does:** Queries `Leave` model for staff member. Shows total, used, and remaining leaves by type.
- **NLP phrases:** "leave balance", "how many leaves do I have", "remaining leaves"
- **NLP phrases (AS):** "ছুটি জেৰ", "মোৰ কিমান ছুটি বাকী"
- **Action handler:** `Leave.find({ staffId }).groupBy('type')` → calculates remaining = total - used
- **Response:** "📅 **Leave Balance:**\n\n• Casual: 8/12 used → **4 remaining**\n• Sick: 3/10 used → **7 remaining**\n• Earned: 0/8 used → **8 remaining**"

### Feature 25: `leave.apply` — Apply for Leave (Interactive)
- **What it does:** Multi-step flow. Bot asks: leave type → start date → end date → reason → creates `Leave` record.
- **NLP phrases:** "apply for leave", "I need leave", "take leave", "request leave"
- **NLP phrases (AS):** "ছুটিৰ আবেদন কৰক", "মোক ছুটি লাগে"
- **Action handler:** Collects data via conversation context, then `Leave.create({ ...data, status: 'PENDING' })`
- **Response:** Interactive flow:
  ```
  Bot: What type of leave?
      → Casual / Sick / Earned
  Bot: Start date?
      → User enters date
  Bot: End date?
      → User enters date
  Bot: Reason?
      → User types reason
  Bot: ✅ Leave application submitted! Status: Pending approval.
  ```

### Feature 26: `payroll.my` — My Latest Payslip
- **What it does:** Queries `Payroll` by staffId. Returns latest payslip with gross, deductions, net pay.
- **NLP phrases:** "my salary", "my payslip", "latest payslip", "salary details"
- **NLP phrases (AS):** "মোৰ দৰমহা", "মোৰ দৰমহা পত্ৰ"
- **Action handler:** `Payroll.findOne({ staffId }).sort({ month: -1, year: -1 })`
- **Response:** "💰 **Latest Payslip (March 2026):**\n\n• Basic: ₹25,000\n• HRA: ₹10,000\n• DA: ₹5,000\n• Gross: ₹40,000\n• PF Deduction: ₹3,000\n• **Net Pay: ₹37,000**"

### Feature 27: `dashboard.stats` — Role-Specific Stats
- **What it does:** Queries relevant models based on `req.user.role`. Returns personalized dashboard summary.
- **NLP phrases:** "show dashboard", "my stats", "summary", "overview"
- **NLP phrases (AS):** "ডেশ্বব'ৰ্ড দেখুৱাওক", "মোৰ তথ্য", "সাৰাংশ"
- **Action handler:**
  ```js
  'dashboard.stats': async (entities, context, userId) => {
    const user = await getUserById(userId);
    if (user.role === 'teacher') return getTeacherStats(userId);
    if (user.role === 'student') return getStudentStats(userId);
    if (user.role === 'parent') return getParentStats(userId);
    if (user.role === 'accounts') return getAccountsStats(userId);
  }
  ```
- **Response (Teacher):** "📊 **Your Dashboard:**\n\n• Classes: 4\n• Students: 156\n• Attendance today: 92%\n• Pending homework: 3\n• Upcoming exams: 2"
- **Response (Student):** "📊 **Your Dashboard:**\n\n• Attendance: 89%\n• Pending homework: 2\n• Fee due: ₹5,000\n• Next exam: Math (10th April)"

---

## PHASE 3: EXPAND KNOWLEDGE BASE (40 New Entries — Week 4-5)

Each entry = title + content + tags + 3-language translations. Added to `server/ai/scanner.js`

### Feature 28-67: 40 Knowledge Base Entries

| # | Category | Title (EN) | Title (AS) | Tags |
|---|----------|-----------|-----------|------|
| 28 | Homework | Submission Guidelines & Late Policy | গৃহকাম জমা দিয়া নিয়ম | homework, deadline, late, penalty |
| 29 | Routine | How Timetable Works & Period Duration | সময়সূচী কেনেকৈ কাম কৰে | routine, period, break, timetable |
| 30 | Notices | How Notices Work & Who Can Post | জাননী কেনেকৈ কাম কৰে | notice, publish, target, priority |
| 31 | Complaints | Filing Process & Escalation Timeline | অভিযোগ দাখিল প্ৰক্ৰিয়া | complaint, escalate, timeline, resolution |
| 32 | Online Payment | Gateway Setup & Supported Methods | অনলাইন পৰিশোধ | payment, gateway, UPI, card |
| 33 | Refunds | Refund Policy & Processing Timeline | ধন ঘূৰাই দিয়া নীতি | refund, timeline, partial, full |
| 34 | Medical Leave | Certificate Requirements & Doctor Visit | চিকিৎসা ছুটিৰ নীতি | medical, certificate, doctor, leave |
| 35 | Re-exams | Supplementary Rules & Eligibility | পুনৰ পৰীক্ষা নিয়ম | reexam, supplementary, eligibility, fee |
| 36 | GPA Calculation | Grading Formula & Weightage | GPA গণনা পদ্ধতি | GPA, grade, formula, weightage |
| 37 | E-Books | Digital Library Access & Download Limits | ডিজিটেল পুস্তকালয় | ebook, digital, download, limit |
| 38 | Dietary Options | Veg/Non-Veg & Allergy Information | আহাৰ বিকল্প | food, veg, non-veg, allergy |
| 39 | Visitor Policy | Who Can Visit, Hours, Sign-in Process | অতিথি নীতি | visitor, hours, sign-in, guest |
| 40 | School Calendar | Academic Year, Holidays, Exam Dates | বিদ্যালয় কেলেণ্ডাৰ | calendar, holiday, exam, academic |
| 41 | Anti-Bullying | Policy, Reporting, Consequences | বুলিং বিৰোধী নীতি | bullying, policy, report, consequence |
| 42 | Parent-Teacher Meetings | Booking Process, Frequency, Format | অভিভাৱক-শিক্ষক সাক্ষাৎ | meeting, booking, parent, teacher |
| 43 | Scholarships | Eligibility, Application, Disbursement | বৃত্তি নীতি | scholarship, eligibility, apply, amount |
| 44 | Extracurricular | Sports, Clubs, Competitions, Eligibility | পাঠ্যক্ৰমৰ বাহিৰৰ কাম | sports, club, competition, activity |
| 45 | Lab Safety | Rules, Equipment Handling, Incidents | পৰীক্ষাগাৰ সুৰক্ষা | lab, safety, equipment, incident |
| 46 | Mobile Phone Policy | When Allowed, Confiscation Rules | ম'বাইল ফোন নীতি | mobile, phone, rule, confiscation |
| 47 | First Aid | Emergency Contacts & Medical Room Location | প্ৰাথমিক চিকিৎসা | first aid, emergency, medical, contact |
| 48 | ID Card Replacement | Process, Fee, Timeline | আইডি কাৰ্ড সলনি | ID card, replacement, fee, timeline |
| 49 | TC Issuance | When Issued, Timeline, Required Documents | TC জাৰি | TC, transfer, certificate, document |
| 50 | Alumni Network | How to Join, Benefits, Events | পূৰ্ব ছাত্ৰ নেটৱৰ্ক | alumni, join, benefit, event |
| 51 | Staff Leave | Types, Approval Process, Carry-Forward | কৰ্মচাৰী ছুটি নীতি | staff leave, type, approval, carry forward |
| 52 | Performance Appraisal | Review Cycle, Criteria, Outcomes | কাৰ্যক্ষমতা মূল্যায়ন | appraisal, review, criteria, outcome |
| 53 | Staff Training | Available Programs, Eligibility, Certification | কৰ্মচাৰী প্ৰশিক্ষণ | training, program, eligibility, certificate |
| 54 | Fee Concession | Who Qualifies, Application, Approval | মাচুল ৰেহাই | concession, qualify, apply, approve |
| 55 | Bus Stop Changes | Process, Timeline, Notification | বাছ স্টপ সলনি | bus stop, change, process, notification |
| 56 | Room Change | Hostel Room Swap Process & Approval | কোঠা সলনি | room change, swap, hostel, approval |
| 57 | Lost & Found | How to Report, Where to Check, Claiming | হেৰুৱা আৰু পোৱা | lost, found, report, claim |
| 58 | Certificate Requests | Bonafide, Conduct, Character Certificates | প্ৰমাণপত্ৰ অনুৰোধ | certificate, bonafide, conduct, character |
| 59 | Weekend Pass | Hostel Weekend Leave Process | সপ্তাহান্ত পাছ | weekend, pass, hostel, leave |
| 60 | Mess Menu Changes | How Menu Updates, Feedback, Complaints | মেছ মেনু সলনি | mess, menu, update, feedback |
| 61 | Vehicle Maintenance | Schedule, Downtime, Alternate Arrangements | বাহন ৰক্ষণাবেক্ষণ | vehicle, maintenance, schedule, downtime |
| 62 | Fuel Tracking | Consumption Monitoring & Reporting | ইন্ধন ট্ৰেকিং | fuel, consumption, tracking, report |
| 63 | Staff Directory | How to Find Contact Info & Departments | কৰ্মচাৰী নিৰ্দেশিকা | staff, directory, contact, department |
| 64 | Exam Timetable Conflicts | How to Report & Resolution Process | পৰীক্ষা সময়সূচী দ্বন্দ্ব | exam, conflict, report, resolution |
| 65 | Grade Disputes | Appeal Process, Timeline, Committee | গ্ৰেড বিবাদ | grade, dispute, appeal, committee |
| 66 | Library Reservation | How to Reserve Books & Hold Period | কিতাপ সংৰক্ষণ | library, reserve, hold, queue |
| 67 | Fine Waivers | When Fines Can Be Waived & Approval Process | জৰিমনা মওকুফ | fine, waiver, waive, approval |

---

## PHASE 4: UI ENHANCEMENTS (15 Features — Week 6-7)

### Feature 68: Responsive Width for All Screen Sizes
- **File:** `client/src/components/Chatbot.jsx`
- **Change:** `w-96` → `w-[min(24rem,calc(100vw-3rem))]`
- **Also:** Add `max-w-full` to prevent horizontal scroll
- **Test:** Open on 320px, 360px, 400px, 768px, 1024px screens

### Feature 69: Helpful/Not-Helpful Feedback Buttons
- **What:** 👍/👎 buttons appear below each bot response after 2 seconds
- **Storage:** localStorage (key: `chatbot_feedback_${timestamp}`)
- **Data stored:** `{ intent, response, thumbs: 'up' | 'down', timestamp }`
- **Purpose:** Measure bot quality without any API calls
- **UI:** Small buttons below message bubble: "Was this helpful? 👍 👎"

### Feature 70: Conversation Search
- **What:** Search input at top of chat window filters message history by keyword
- **How:** Client-side filter on `messages` array state. Highlights matching messages
- **UI:** Search icon → expands to input field. Shows "X results found"
- **Implementation:** `messages.filter(m => m.message.toLowerCase().includes(query.toLowerCase()))`

### Feature 71: Pin/Favorite Responses
- **What:** Star icon on each bot response. Saves to localStorage `chatbot_pinned`
- **UI:** ⭐ icon on hover. Pinned messages shown in a "⭐ Pinned" section at top
- **Storage:** Array of `{ message, timestamp, intent }` objects
- **Limit:** Max 20 pinned responses

### Feature 72: Export Conversation as Text File
- **What:** Download button exports entire conversation as `.txt` file
- **Format:**
  ```
  EduGlass Chatbot - Conversation Export
  Date: 2026-04-06
  Language: English
  
  [9:15 AM] User: What is my attendance?
  [9:15 AM] Bot: Your attendance is 89%...
  ```
- **Implementation:** `Blob` + `URL.createObjectURL` + hidden `<a>` click

### Feature 73: Emoji Picker
- **What:** Simple emoji grid (8×6 = 48 emojis) that inserts into message input
- **Emojis:** 😊👍🎉❤️📚📅💰🏫🚌🍽️📖🏠📝📊👤✅❌⚠️💡🔔📞📧🎓🏆⏰📈📉🎯💪🙏🤔😎😢😡🤗😴🤒💊🏥🌧️☀️❄️🌈📌🗓️📋
- **UI:** Emoji icon next to send button → opens small grid overlay
- **Implementation:** Array of emoji strings, click inserts into input value

### Feature 74: Message Timestamps
- **What:** Shows "9:15 AM" below each message
- **Format:** 12-hour with AM/PM
- **Implementation:** `message.timestamp || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })`
- **Style:** Small, gray text below message bubble

### Feature 75: Message Copy Button
- **What:** Clipboard icon appears on hover over any message
- **Action:** `navigator.clipboard.writeText(message.message)`
- **Feedback:** Brief "✅ Copied" toast notification
- **Works for:** Both user and bot messages

### Feature 76: Dark Mode
- **What:** Matches system `prefers-color-scheme` or manual toggle
- **Storage:** localStorage `chatbot_dark_mode: 'auto' | 'dark' | 'light'`
- **Colors:**
  - Background: `bg-gray-900` (dark) vs `bg-gray-50` (light)
  - Bot bubble: `bg-gray-800 text-white` (dark) vs `bg-white text-gray-800` (light)
  - User bubble: `bg-indigo-800` (dark) vs `bg-indigo-600` (light)
  - Header: `bg-gray-800` (dark) vs gradient (light)
- **Toggle:** Sun/moon icon in header

### Feature 77: Keyboard Shortcuts
- **Shortcuts:**
  - `Ctrl+K` or `Cmd+K` → Open/close chatbot
  - `Esc` → Close chatbot
  - `↑` (in empty input) → Edit last sent message
  - `Enter` → Send message (already exists)
  - `Shift+Enter` → New line in message
- **Implementation:** `useEffect` with `keydown` event listener
- **Scope:** Only when chatbot is open (except Ctrl+K)

### Feature 78: Notification Badge on FAB
- **What:** Red dot with number on floating action button when bot has proactive messages
- **Use case:** "You have 3 overdue books" or "Attendance below 75%!" — shown when user opens app
- **Implementation:** Check on mount → set badge count → clear on open
- **Style:** `absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center`

### Feature 79: Role-Specific Quick Actions
- **What:** Quick action buttons change based on `req.user.role` (available from auth context)
- **Mapping:**
  - Teacher: "Mark Attendance", "Add Homework", "Enter Marks"
  - Student: "My Homework", "My Attendance", "My Results"
  - Parent: "Child's Attendance", "Child's Results", "Pay Fees"
  - Accounts: "Collect Fee", "Fee Report", "Defaulters List"
  - HR: "Staff Directory", "Leave Approval", "Payroll"
  - Admin: "Dashboard Stats", "Add User", "System Report"
- **Implementation:** Read role from auth context → filter quick actions array

### Feature 80: Rich Card Responses
- **What:** Instead of plain text, bot returns structured cards with title, description, and action button
- **Format:**
  ```json
  {
    "type": "card",
    "title": "Upcoming Exams",
    "description": "3 exams scheduled this week",
    "button": { "text": "View Full Schedule", "action": "/exams" }
  }
  ```
- **UI:** Card component with shadow, rounded corners, colored accent bar, clickable button
- **Fallback:** Renders as formatted text if card parsing fails

### Feature 81: Carousel for Lists
- **What:** Swipeable horizontal cards for lists (students, books, notices)
- **Use case:** "Show my books" → horizontal carousel of book cards
- **UI:** Each card: title, subtitle, small icon. Swipe left/right. Dots indicator below
- **Implementation:** CSS `overflow-x: scroll` + `scroll-snap-type: x mandatory`

### Feature 82: Table Formatting in Responses
- **What:** Bot can render HTML tables for structured data (fee breakdown, attendance stats)
- **Format:** Bot returns markdown table → converted to HTML `<table>`
- **Styling:** Striped rows, header with bold, right-aligned numbers
- **Use case:** "Show fee breakdown" → table with Fee Type | Amount | Status

### Feature 83: ASCII/Unicode Mini Charts
- **What:** Simple text-based charts for stats (attendance %, fee collection)
- **Format:**
  ```
  Attendance This Week:
  Mon ██████████░░ 83%
  Tue ███████████░ 92%
  Wed █████████░░░ 75%
  Thu ████████████ 100%
  Fri ██████████░░ 85%
  ```
- **Implementation:** Server generates unicode bar chart in response text
- **Use case:** Quick visual stats without needing full chart library

---

## PHASE 5: SMART FEATURES (17 Features — Week 8-9)

### Feature 84: "Did You Mean?" for Low-Confidence Matches
- **What:** When NLP confidence is between 0.3-0.5, show alternative intents
- **Implementation:**
  ```js
  if (response.confidence < 0.5 && response.confidence >= 0.3) {
    const alternatives = getTopIntents(message, 3); // e.g., ["exam.get", "homework.list"]
    return { message: `Did you mean one of these?\n• ${alternatives.join('\n• ')}` };
  }
  ```
- **UI:** Bulleted list of clickable suggestions below the bot response

### Feature 85: Smart Suggestions Based on Role + Time
- **What:** Bot proactively suggests actions based on who the user is + what time it is
- **Logic:**
  ```js
  const hour = new Date().getHours();
  if (user.role === 'teacher' && hour >= 8 && hour <= 10) {
    suggestions = ["Mark Today's Attendance", "View Today's Timetable"];
  }
  if (user.role === 'accounts' && hour >= 14 && hour <= 16) {
    suggestions = ["Collect Pending Fees", "View Defaulters List"];
  }
  if (user.role === 'student' && day === 'Friday') {
    suggestions = ["Check Weekend Homework", "View Next Week's Timetable"];
  }
  ```
- **UI:** "💡 Suggested for you:" section at bottom of chat

### Feature 86: Date Parsing — Natural Language Dates
- **What:** Understands "next Monday", "tomorrow", "15th March", "last week", "this month"
- **Implementation:**
  ```js
  function parseNaturalDate(text) {
    const now = new Date();
    const lower = text.toLowerCase();
    if (lower === 'today') return now;
    if (lower === 'tomorrow') { const d = new Date(now); d.setDate(d.getDate() + 1); return d; }
    if (lower === 'yesterday') { const d = new Date(now); d.setDate(d.getDate() - 1); return d; }
    if (lower.includes('next monday')) return getNextDay(1); // 1 = Monday
    if (lower.includes('last week')) { const d = new Date(now); d.setDate(d.getDate() - 7); return d; }
    // Regex for "15th March", "March 15", "15/03/2026"
    const match = text.match(/(\d{1,2})[\/\s-](\d{1,2})[\/\s-](\d{4})/);
    if (match) return new Date(match[3], match[2] - 1, match[1]);
    return null;
  }
  ```
- **Use case:** "Show attendance for last Monday" → parses date → queries DB

### Feature 87: Amount Parsing
- **What:** Understands "five hundred" → 500, "₹5000" → 5000, "five thousand rupees" → 5000
- **Implementation:**
  ```js
  const numberWords = { 'hundred': 100, 'thousand': 1000, 'five': 5, 'ten': 10, ... };
  function parseAmount(text) {
    // Match ₹5000, Rs.5000, 5000
    const rupeeMatch = text.match(/[₹Rs.]*\s*(\d+)/);
    if (rupeeMatch) return parseInt(rupeeMatch[1]);
    // Match "five hundred"
    const words = text.toLowerCase().split(' ');
    // Convert word numbers to digits
  }
  ```
- **Use case:** "Pay fee of five thousand" → extracts 5000 → guides through payment

### Feature 88: Spell Correction
- **What:** Auto-corrects common typos in user input before NLP processing
- **Implementation:** Dictionary of common misspellings → replace before processing
  ```js
  const corrections = {
    'atendance': 'attendance', 'attandance': 'attendance',
    'libary': 'library', 'librery': 'library',
    'examm': 'exam', 'exame': 'exam',
    'hostal': 'hostel', 'hostl': 'hostel',
    'trasport': 'transport', 'transpor': 'transport',
    'payrole': 'payroll', 'payrol': 'payroll',
    'recipt': 'receipt', 'receit': 'receipt',
    'complaints': 'complaint', 'complant': 'complaint',
  };
  function correctSpelling(text) {
    let corrected = text;
    for (const [wrong, right] of Object.entries(corrections)) {
      corrected = corrected.replace(new RegExp(wrong, 'gi'), right);
    }
    return corrected;
  }
  ```
- **Impact:** Improves intent matching accuracy by 15-20%

### Feature 89: Synonym Expansion
- **What:** Expands common terms so bot understands variations
- **Implementation:**
  ```js
  const synonyms = {
    'fee': ['fees', 'dues', 'payment', 'charges', 'maasul', 'शुल्क', 'মাচুল'],
    'exam': ['test', 'examination', 'paper', 'assessment', 'পরীক্ষা', 'परीक्षा'],
    'attendance': ['present', 'absent', 'hajiri', 'उपस्थिति', 'উপস্থিতি'],
    'library': ['books', 'kitab', 'पुस्तकालय', 'পুস্তকালয়'],
    'hostel': ['room', 'dormitory', 'boarding', 'आवास', 'আবাসন'],
    'transport': ['bus', 'vehicle', 'transport', 'परिवहन', 'পৰিবহণ'],
    'canteen': ['food', 'mess', 'lunch', 'खाना', 'আহাৰ'],
    'marks': ['score', 'grade', 'result', 'नंबर', 'নম্বৰ'],
    'holiday': ['vacation', 'break', 'ছুটি', 'छुट्टी'],
  };
  ```
- **Impact:** Bot understands "show my dues" = "show my fee status"

### Feature 90: Multi-Intent Detection
- **What:** Handles "Show attendance and fee status" → returns BOTH responses
- **Implementation:**
  ```js
  async function processMessage(text) {
    const intents = detectAllIntents(text); // Returns array of intents
    if (intents.length > 1) {
      const responses = await Promise.all(intents.map(i => executeAction(i)));
      return { message: responses.map(r => r.message).join('\n\n---\n\n') };
    }
    return executeAction(intents[0]);
  }
  ```
- **Use case:** "Show my attendance and exam schedule" → both results in one response

### Feature 91: Intent Chaining
- **What:** User says "Show attendance" → bot shows → user says "Export it" → bot chains export action
- **Implementation:** Store last intent in conversation context → "Export it" maps to `export.${lastIntent}`
- **Use case:**
  ```
  User: "Show attendance" → intent: attendance.my
  User: "Export it" → resolves to: export.attendance.my → generates PDF
  ```

### Feature 92: Proactive Notifications
- **What:** On chat open, bot checks for urgent items and mentions them first
- **Logic:**
  ```js
  async function getProactiveMessage(userId) {
    const alerts = [];
    const attendance = await getAttendancePct(userId);
    if (attendance < 75) alerts.push("⚠️ Your attendance is below 75%!");
    const overdue = await getOverdueBooks(userId);
    if (overdue.length > 0) alerts.push(`📖 You have ${overdue.length} overdue book(s)!`);
    const feeDue = await getFeeDue(userId);
    if (feeDue > 0) alerts.push(`💰 ₹${feeDue} fee is due!`);
    return alerts.length > 0 ? alerts.join('\n\n') : null;
  }
  ```
- **UI:** System-style message (yellow bubble) at top of chat: "⚠️ Attention: ..."

### Feature 93: Confirmation Dialogs
- **What:** Before destructive actions (delete, submit, vacate), bot asks "Are you sure?"
- **Use cases:**
  - "Delete complaint" → "⚠️ Are you sure you want to delete this complaint? This cannot be undone."
  - "Submit leave" → "✅ Submit leave application for 3 days? Reply 'yes' to confirm."
- **Implementation:** Store pending action in conversation context → wait for confirmation → execute

### Feature 94: Progress Indicators
- **What:** "Step 2 of 5" shown during multi-step flows
- **UI:** Small progress bar or text at top of chat: "▓▓░░░ Step 2 of 5 — Leave Type"
- **Implementation:** Track step number in conversation context → display in every message

### Feature 95: Undo Support
- **What:** After completing an action, bot offers "↩️ Undo" option for 30 seconds
- **Implementation:**
  ```js
  // After action completes
  const undoToken = setTimeout(() => { clearUndoToken(); }, 30000);
  // If user types "undo" within 30 seconds
  if (userMessage === 'undo' && undoToken) {
    clearTimeout(undoToken);
    await rollbackLastAction();
  }
  ```
- **Use cases:** Undo accidental leave application, undo wrong data entry

### Feature 96: Save Draft Conversations
- **What:** If user starts a multi-step flow then closes chat, bot resumes next time
- **Storage:** localStorage `chatbot_draft: { intent, step, data, timestamp }`
- **On open:** "I see you were in the middle of applying for leave. Continue where you left off?"
- **Expiry:** Drafts expire after 24 hours

### Feature 97: Timeout Warnings
- **What:** If user hasn't typed anything for 5 minutes during a flow, bot warns: "⏰ Session expiring in 2 minutes"
- **Implementation:** `setTimeout` on each user message → reset on new message → warn at 5 min → expire at 7 min
- **Action:** Clear conversation context after timeout

### Feature 98: Satisfaction Survey
- **What:** After every 5th conversation, bot asks "How was my help today? Rate 1-5 ⭐"
- **Storage:** localStorage `chatbot_survey_history` → tracks last survey date
- **Data:** `{ rating, timestamp, intent_context }` stored locally
- **UI:** Star rating component (clickable ⭐⭐⭐⭐⭐)

### Feature 99: Personalization — Remember User Preferences
- **What:** Bot remembers language preference, common queries, preferred response format
- **Storage:** localStorage `chatbot_preferences`
- **Example:** If user always asks about attendance in Assamese → default to Assamese responses
- **Implementation:** Track query patterns → adjust defaults

### Feature 100: Response Variation
- **What:** Bot doesn't repeat the exact same wording every time
- **Implementation:** Each response has 3 variations, randomly selected
  ```js
  const greetings = [
    "👋 Hello! How can I help?",
    "👋 Hi there! What do you need?",
    "👋 Welcome back! What can I do for you?",
  ];
  return greetings[Math.floor(Math.random() * greetings.length)];
  ```
- **Impact:** Feels more natural, less robotic

---

## PHASE 6: ADVANCED FEATURES (15 Features — Week 10-11)

### Feature 101: Conversational Forms — "Create a New Class"
- **What:** Bot collects data step-by-step and creates the record
- **Flow:**
  ```
  Bot: "Let's create a new class. What's the class name?"
  User: "Class 10"
  Bot: "Which section? (A, B, C, etc.)"
  User: "A"
  Bot: "Who is the class teacher?"
  User: "Mr. Sharma"
  Bot: "How many students?"
  User: "40"
  Bot: "✅ Class 10-A created successfully with Mr. Sharma as class teacher!"
  ```
- **Implementation:** State machine in conversation context → `Class.create(data)` at final step
- **Validation:** Each step validates input before proceeding

### Feature 102: Conversational Forms — "Collect Fee"
- **Flow:**
  ```
  Bot: "Fee collection. Which student?"
  User: "Rajesh Kumar"
  Bot: "Found: Rajesh Kumar, Class 10-A. Fee type?"
  User: "Tuition"
  Bot: "Amount: ₹5,000. Payment mode? (Cash, Card, UPI, Bank Transfer)"
  User: "Cash"
  Bot: "✅ Fee collected! Receipt #REC-2026-0456 generated. Print receipt?"
  ```
- **Implementation:** Step-by-step data collection → `FeePayment.create(data)` → generate receipt number

### Feature 103: Conversational Dashboards
- **What:** "Show dashboard" → returns personalized stats based on role
- **Implementation:** Same as Feature 27 but rendered as a rich card with sections
- **Response format:** Multiple cards showing key metrics

### Feature 104: Conversational Reports
- **What:** "Generate fee report for March" → creates formatted report
- **Implementation:** Queries data → formats as text table or markdown → offers PDF download link
- **Use cases:** Fee report, attendance report, exam result report, payroll summary

### Feature 105: Natural Language Queries
- **What:** "Show me students with less than 75% attendance" → parses and executes
- **Implementation:** Pattern matching on query structure:
  ```js
  // Pattern: "students with < X% attendance"
  const match = text.match(/students with (less than|more than) (\d+)% attendance/);
  if (match) {
    const threshold = parseInt(match[2]);
    const operator = match[1] === 'less than' ? '$lt' : '$gt';
    return findStudentsByAttendance(operator, threshold);
  }
  ```
- **Supported patterns:**
  - "Who owes more than ₹X?"
  - "List books by [author]"
  - "Buses on Route X"
  - "Staff who applied leave this week"
  - "Top 10 scorers in Class X"
  - "Empty hostel rooms"
  - "Canteen items under ₹X"

### Feature 106: Smart Reminders
- **What:** "Remind me to collect fees tomorrow at 10 AM"
- **Implementation:** Store reminder in localStorage with timestamp
- **Check:** On each chat open, check for pending reminders → show if time has passed
- **Limitations:** Only works when user opens the chatbot (no push notifications without service worker)

### Feature 107: Smart Reports (Auto-Generated)
- **What:** Bot generates periodic reports on demand
- **Types:**
  - Daily summary: "Today's attendance: 89%, Fees collected: ₹45,000, Complaints: 2"
  - Weekly attendance trend
  - Monthly fee collection summary
  - Exam performance summary
- **Implementation:** Aggregate queries → format as text → display

### Feature 108: Smart Actions (Auto-Triggers)
- **What:** Bot can auto-trigger simple actions based on conversation
- **Examples:**
  - "Mark attendance" for a class → bot opens attendance UI pre-filled
  - "Send fee reminder to defaulters" → bot generates list of defaulters
  - "Auto-generate receipts for today's payments" → bot lists and confirms

### Feature 109: Admin Knowledge Base Editor (In-Chat)
- **What:** Admin can add/edit KB entries through the chatbot itself
- **Flow:**
  ```
  Admin: "/addkb"
  Bot: "KB Entry Mode. What's the title?"
  Admin: "Library Fine Rules"
  Bot: "What's the content?"
  Admin: "Fines are ₹1/day, max ₹100..."
  Bot: "Tags? (comma separated)"
  Admin: "library, fine, rules"
  Bot: "✅ KB entry 'Library Fine Rules' added!"
  ```
- **Implementation:** Stores in `knowledgeBase.json` on server → no code deployment needed

### Feature 110: KB Version Control
- **What:** Track when KB entries were added/changed
- **Implementation:** Each KB entry has `createdAt`, `updatedAt`, `addedBy` fields
- **Admin command:** "/kb history" → shows last 10 KB changes
- **Benefit:** Audit trail for knowledge base changes

### Feature 111: Offline Mode — Cached Responses
- **What:** When server is unreachable, bot uses cached responses from previous sessions
- **Implementation:** Store last 50 Q&A pairs in localStorage → on API failure, search cached pairs for similar queries
- **Similarity:** Simple string matching → if query shares 3+ keywords with cached query, return cached answer

### Feature 112: Bot Personality Customization
- **What:** Admin can set bot's tone via command
- **Modes:**
  - Formal: "Good morning. How may I assist you today?"
  - Friendly: "Hey! What can I help you with? 😊"
  - Funny: "Your friendly neighborhood bot is here! What's up? 🤖"
- **Implementation:** Tone templates in `chatbotKnowledge.js` → swap greeting/response templates based on selected personality
- **Command:** `/tone formal` | `/tone friendly` | `/tone funny`

### Feature 113: Achievement System
- **What:** Fun milestones for frequent bot users
- **Achievements:**
  - "First Question" — Asked first question
  - "Curious Mind" — Asked 10 different questions
  - "Power User" — Used bot 50 times
  - "Polyglot" — Used 3 languages
  - "Helper" — Gave 10 positive feedback ratings
- **UI:** "🏆 Achievement Unlocked: Curious Mind!" message after qualifying action
- **Storage:** localStorage `chatbot_achievements`

### Feature 114: Custom Intents via Commands
- **What:** Admin can define new intent → response mappings through chat
- **Command:**
  ```
  /addintent
  Intent: library.fine
  Keywords: fine amount, library penalty, overdue book fine
  Response: Library fines are ₹1 per day, maximum ₹100.
  ```
- **Storage:** `customIntents.json` → loaded at startup alongside NLP manager
- **Limitation:** Text-only responses (no database actions)

### Feature 115: Multi-Language Mid-Conversation
- **What:** User can switch languages in the middle of a conversation
- **Flow:**
  ```
  User: "Show my attendance" (English)
  Bot: "Your attendance is 89%" (English)
  User: "/lang as" (switch to Assamese)
  Bot: "🌐 ভাষা অসমীয়ালৈ সলনি কৰা হ'ল" (Assamese)
  User: "মোৰ পৰীক্ষা দেখুৱাওক" (Assamese — "show my exam")
  Bot: "আপোনাৰ পৰীক্ষা..." (Assamese response)
  ```
- **Implementation:** Language stored per-conversation → NLP engine processes in selected language → response in same language

---

## SUMMARY TABLE

| Phase | Features | Effort | Files Changed |
|-------|----------|--------|---------------|
| 1: Bug Fixes | 6 | 1-2 days | 3 files |
| 2: New Intents | 27 | 1-2 weeks | 4 files |
| 3: Knowledge Base | 40 | 1 week | 2 files |
| 4: UI Enhancements | 15 | 1 week | 3 files |
| 5: Smart Features | 17 | 1-2 weeks | 5 files |
| 6: Advanced | 15 | 2 weeks | 6 files |
| **Total** | **115** | **6-8 weeks** | **12 files** |

### Files That Need Changes:
1. `server/ai/nlpEngine.js` — Fix bugs, add intents, add language handling
2. `server/ai/actions.js` — Fix bugs, add 27 action handlers
3. `server/ai/scanner.js` — Add 40 KB entries
4. `server/routes/chatbot.js` — Pass user context to NLP
5. `client/src/components/Chatbot.jsx` — 15 UI enhancements
6. `client/src/utils/chatbotEngine.js` — Role-specific actions, smart suggestions
7. `client/src/utils/chatbotKnowledge.js` — All Assamese content + response variations
8. `server/middleware/rateLimiter.js` — (only if `ipKeyGenerator` fix needed)
9. `server/models/ChatbotLog.js` — (no change needed, already good)
10. New: `server/data/knowledgeBase.json` — Admin-editable KB
11. New: `server/data/customIntents.json` — Custom intent mappings
12. New: `server/utils/dateParser.js` — Natural language date parsing
