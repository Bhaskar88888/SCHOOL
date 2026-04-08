# 🤖 Chatbot Complete Analysis — Errors + Missing Features (Offline-Addable)

**Date:** April 6, 2026  
**Files Analyzed:** 8 files across server + client

---

## PART 1: CURRENT ERRORS (All 14 Confirmed)

### 🔴 Critical Bugs — Bot Gives Wrong or No Answers

| # | Error | File : Line | What's Wrong | What User Sees |
|---|-------|-------------|--------------|----------------|
| 1 | **Canteen menu always empty** | `server/ai/actions.js:260` | Queries `{ available: true }` but model field is `isAvailable` | "Today's menu is being updated" every time |
| 2 | **Menu item names show undefined** | `server/ai/actions.js:262` | Reads `i.itemName` but model field is `name` | Menu shows "• undefined — ₹50" |
| 3 | **NLP entity duplication memory leak** | `server/ai/nlpEngine.js:145` | `addDynamicEntities()` runs on every retrain but node-nlp has no `removeNamedEntity` — entities accumulate forever | Bot gets slower over time, may match wrong student/staff names |
| 4 | **Follow-up handler only covers 2 cases** | `server/ai/nlpEngine.js:543-560` | Only handles `hr.getAbsent→student` and `fee.getDefaults→payment` — all other intents get generic fallback | "Based on our conversation about X, could you be more specific?" — useless |
| 5 | **No user auth context in NLP** | `server/ai/nlpEngine.js:489` | `processMessage(userId = 'anonymous')` — userId passed from chatbot route but never used for personalization | Student asks "what's my attendance?" → bot can't answer because it doesn't know who's asking |
| 6 | **Fallback gives no indication of source** | `client/src/components/Chatbot.jsx:154` | When server NLP fails, falls back to local KB silently — user doesn't know they're getting stale/cached answers | User thinks bot is smart, actually getting offline KB |
| 7 | **KB scanner produces garbled text** | `server/ai/scanner.js:84-127` | Extracts random string literals from JSX files — results are out-of-context fragments like "Mark attendance", "Collect Fee" without meaning | Search returns meaningless text blobs |
| 8 | **`processMessage` entity extraction broken** | `server/ai/nlpEngine.js:507-511` | Reads `entity.option` but node-nlp returns `entity.utteranceText` for extracted entities | Named entities (student names, book names) never resolve correctly |
| 9 | **`addDynamicEntities` uses wrong method signature** | `server/ai/nlpEngine.js:152-177` | `manager.addNamedEntityText(entityType, id, languages, texts)` — 4th param should be array of texts, but each entity gets single name as array `[name]` — works but is extremely inefficient | NLP model size grows unnecessarily |
| 10 | **KB only has 10 hardcoded entries** | `server/ai/scanner.js:192-230` | Only covers basic policies — no homework, routine, notices, payroll, canteen, exam rules | Bot returns "I didn't quite understand" for most real questions |
| 11 | **Quick actions are role-blind** | `client/src/utils/chatbotEngine.js:113` | Same quick actions shown to all roles — parent sees "Admit Student", student sees "Payroll Help" | Confusing, irrelevant suggestions |
| 12 | **Language selector shows only English in fallback** | `client/src/components/Chatbot.jsx:10` | `FALLBACK_LANGUAGES` only has `en` — if `chatbotKnowledge` fails to load, Hindi/Assamese users get broken selector | Language dropdown shows only "EN English" |
| 13 | **Chatbot window overflows on mobile** | `client/src/components/Chatbot.jsx:237` | Fixed `w-96` (384px) — exceeds screen width on phones < 400px | Chatbot partially off-screen on mobile |
| 14 | **`/chatbot/chat` endpoint requires auth** | `server/routes/chatbot.js:14` | Frontend sends request but if user is not logged in (or token expired), 401 error → falls back to local KB every time | Logged-out users get only offline responses |

### 🟡 Functional Gaps — Bot Can't Do These

| # | Gap | Description |
|---|-----|-------------|
| 15 | No homework queries | "show my homework", "pending assignments" — no intent |
| 16 | No routine/timetable queries | "what's my timetable", "today's schedule" — no intent |
| 17 | No notice queries | "show notices", "read notice about sports day" — no intent |
| 18 | No complaint status tracking | "status of my complaint" — no intent |
| 19 | No personal data queries | "my attendance %", "my fee balance", "my exam results" — bot doesn't know user |
| 20 | No leave management | "apply for leave", "my leave balance" — no intent |
| 21 | No library overdue alerts | "books I haven't returned" — intent exists but doesn't check user-specific data |
| 22 | No canteen recharge guide | "recharge wallet" — no intent |
| 23 | No exam results | "my results", "report card" — no intent |
| 24 | No multi-step conversations | Bot can't collect data step-by-step (e.g., "admit a student" → asks name → asks class → creates record) |
| 25 | No confirmation dialogs | No "Are you sure?" before actions |
| 26 | No "was this helpful?" feedback | Can't measure bot quality |
| 27 | No conversation search | Can't find past responses |
| 28 | No pin/favorite responses | Can't save important answers |
| 29 | No conversation export | Can't download chat as PDF |
| 30 | No emoji reactions | No quick feedback mechanism |

---

## PART 2: FEATURES PRESENT (What Already Works)

### ✅ Working Features

| Category | Feature | How It Works |
|----------|---------|--------------|
| **NLP Engine** | Multilingual intent recognition | node-nlp with EN/HI/AS training data (~120 phrases) |
| **NLP Engine** | Named entity recognition | Student names, staff names, class names, vehicle numbers, book titles loaded from DB |
| **NLP Engine** | Conversation context | Last 10 messages stored per user, 1-hour expiry |
| **NLP Engine** | Auto-retrain on model change | Detects schema version mismatch, retrains automatically |
| **Knowledge Base** | 10 hardcoded policy entries | Library, admission, canteen, transport, exam, fee, leave, hostel, complaint, uniform |
| **Knowledge Base** | Auto-scanned JSX content | Scrapes client pages for string literals (~35 entries) |
| **Knowledge Base** | FlexSearch indexing | Title + content + tag search with relevance scoring |
| **Offline Fallback** | Local keyword matching | `chatbotKnowledge.js` has 9 categories × 3 languages with keyword→response mapping |
| **Offline Fallback** | Slash commands | `/help`, `/lang`, `/clear`, `/admit`, `/attendance`, `/fee`, `/exam`, `/library`, `/hostel`, `/transport` |
| **Offline Fallback** | Quick action buttons | Role-blind but shows common actions |
| **Offline Fallback** | Context-aware suggestions | 3 context categories with follow-up suggestions |
| **Offline Fallback** | Language persistence | Stores selected language in localStorage |
| **Offline Fallback** | Conversation history | Last 50 messages stored in memory |
| **UI** | Chat bubble toggle | Floating button with open/close animation |
| **UI** | Markdown rendering | Bold + bullet list formatting |
| **UI** | Typing indicator | Animated dots while bot "thinks" |
| **UI** | Language selector | Dropdown in header |
| **UI** | Clear chat button | Trash icon resets conversation |
| **Backend API** | Authenticated chat endpoint | POST `/api/chatbot/chat` with rate limiting |
| **Backend API** | Chat history | GET `/api/chatbot/history` — last 20 logs |
| **Backend API** | Admin analytics | GET `/api/chatbot/analytics` — intent breakdown, top queries, errors |
| **Backend API** | Chatbot logging | All queries logged to `ChatbotLog` model with 90-day auto-expiry |
| **Backend API** | Rate limiting | 100 messages per 15 min per user |

---

## PART 3: FEATURES THAT CAN BE ADDED OFFLINE (No External APIs Needed)

### Phase 1: Fix Bugs (Day 1-2)

| # | Feature | Files to Change | Effort |
|---|---------|-----------------|--------|
| 1 | Fix `available` → `isAvailable` | `server/ai/actions.js` | 1 line |
| 2 | Fix `itemName` → `name` | `server/ai/actions.js` | 1 line |
| 3 | Fix entity extraction `entity.option` → `entity.utteranceText` | `server/ai/nlpEngine.js` | 1 line |
| 4 | Clear NLP manager before re-adding entities | `server/ai/nlpEngine.js` | 5 lines (create new manager on retrain) |
| 5 | Add fallback source indicator | `client/src/components/Chatbot.jsx` | 3 lines |
| 6 | Fix mobile width overflow | `client/src/components/Chatbot.jsx` | Change `w-96` to `w-[min(24rem,calc(100vw-3rem))]` |

### Phase 2: Add Intents (Day 3-5)

All these are **purely offline** — they query local MongoDB:

| # | New Intent | What It Does | Action Handler Needed |
|---|-----------|--------------|----------------------|
| 7 | `homework.list` | Returns user's homework | Query `Homework` model by user's class/studentId |
| 8 | `homework.pending` | Shows overdue assignments | Query `Homework` where dueDate < today |
| 9 | `routine.view` | Returns user's class timetable | Query `Routine` model by classId |
| 10 | `notice.list` | Lists recent notices | Query `Notice` model, sorted by date |
| 11 | `notice.detail` | Shows specific notice | Query `Notice` by ID or keyword match |
| 12 | `complaint.status` | Checks complaint progress | Query `Complaint` by userId |
| 13 | `complaint.new` | Guides through complaint filing | Interactive step-by-step |
| 14 | `attendance.my` | Returns user's attendance % | Query `Attendance` for user's studentId |
| 15 | `attendance.history` | Historical attendance | Query with date range filter |
| 16 | `fee.my` | Personal fee status | Query `FeePayment` + `FeeStructure` for student |
| 17 | `exam.my` | User's upcoming exams | Query `Exam` by classId |
| 18 | `exam.results` | User's exam results | Query `ExamResult` by studentId |
| 19 | `library.my` | Books I've borrowed | Query `LibraryTransaction` by studentId |
| 20 | `library.overdue` | Overdue books + fines | Query where dueDate < today AND status = BORROWED |
| 21 | `canteen.recharge` | Wallet recharge guide | Static guide (no action needed) |
| 22 | `hostel.my` | My room details | Query `HostelAllocation` by studentId |
| 23 | `transport.my` | My bus route | Query `TransportVehicle` where students includes me |
| 24 | `leave.balance` | Remaining leave counts | Query `Leave` model for user |
| 25 | `leave.apply` | Apply for leave | Interactive form → create Leave record |
| 26 | `payroll.my` | My latest payslip | Query `Payroll` by staffId |
| 27 | `dashboard.stats` | Role-specific stats | Query relevant models based on user role |

### Phase 3: Expand Knowledge Base (Day 6-7)

Add these 40 new KB entries to `server/ai/scanner.js`:

| # | Category | Title |
|---|----------|-------|
| 28 | Homework | Submission guidelines, late policy, file types accepted |
| 29 | Routine | How timetable works, period duration, break times |
| 30 | Notices | How notices work, who can post, acknowledgment |
| 31 | Complaints | Filing process, escalation, resolution timeline |
| 32 | Online Payment | Gateway setup, supported methods, auto-reconciliation |
| 33 | Refunds | Refund policy, timeline, partial refunds |
| 34 | Medical Leave | Certificate requirements, doctor visit process |
| 35 | Re-exams | Supplementary exam rules, eligibility, fees |
| 36 | GPA Calculation | Grading formula, weightage, improvement policy |
| 37 | E-Books | Digital library access, download limits, formats |
| 38 | Dietary Options | Veg/non-veg, allergy info, special meals |
| 39 | Visitor Policy | Who can visit, hours, sign-in process |
| 40 | School Calendar | Academic year, holidays, exam dates |
| 41 | Anti-Bullying | Policy, reporting, consequences |
| 42 | Parent-Teacher Meetings | Booking process, frequency, format |
| 43 | Scholarships | Eligibility, application, disbursement |
| 44 | Extracurricular | Sports, clubs, competitions, eligibility |
| 45 | Lab Safety | Rules, equipment handling, incident reporting |
| 46 | Mobile Phone Policy | When allowed, confiscation rules |
| 47 | First Aid | Emergency contacts, medical room location |
| 48 | ID Card Replacement | Process, fee, timeline |
| 49 | TC Issuance | When issued, timeline, required documents |
| 50 | Alumni Network | How to join, benefits, events |
| 51 | Staff Leave | Types, approval process, carry-forward |
| 52 | Performance Appraisal | Review cycle, criteria, outcomes |
| 53 | Staff Training | Available programs, eligibility, certification |
| 54 | Fee Concession | Who qualifies, application, approval |
| 55 | Bus Stop Changes | Process, timeline, notification |
| 56 | Room Change | Hostel room swap process, approval |
| 57 | Lost & Found | How to report, where to check, claiming |
| 58 | Certificate Requests | Bonafide, conduct, character certificates |
| 59 | Weekend Pass | Hostel weekend leave process |
| 60 | Mess Menu Changes | How menu updates, feedback, complaints |
| 61 | Vehicle Maintenance | Schedule, downtime, alternate arrangements |
| 62 | Fuel Tracking | Consumption monitoring, reporting |
| 63 | Staff Directory | How to find contact info, departments |
| 64 | Exam Timetable Conflicts | How to report, resolution process |
| 65 | Grade Disputes | Appeal process, timeline, committee |
| 66 | Library Reservation | How to reserve books, hold period |
| 67 | Fine Waivers | When fines can be waived, approval process |

### Phase 4: UI Enhancements (Day 8-9)

All **purely offline** — no external dependencies:

| # | Feature | Description |
|---|---------|-------------|
| 68 | Responsive width | `w-[min(24rem,calc(100vw-3rem))]` for mobile |
| 69 | Helpful/not-helpful buttons | 👍/👎 below each bot response, stores in localStorage |
| 70 | Conversation search | Search input filters message history by keyword |
| 71 | Pin responses | Star icon saves response to localStorage favorites |
| 72 | Export conversation | Download as .txt file |
| 73 | Emoji picker | Simple emoji grid for inserting into messages |
| 74 | Message timestamps | Show "2:30 PM" below messages |
| 75 | Message copy button | Clipboard icon on hover |
| 76 | Dark mode | Match system `prefers-color-scheme` |
| 77 | Keyboard shortcuts | `Ctrl+K` open, `Esc` close, `↑` edit last message |
| 78 | Notification badge | Unread count on FAB button |
| 79 | Role-specific quick actions | Filter by `req.user.role` |
| 80 | Rich card responses | Cards with title, description, action button |
| 81 | Carousel for lists | Swipeable cards for student lists, books, etc. |
| 82 | Table formatting | Render data as HTML tables in chat |
| 83 | Chart snippets | Simple ASCII/unicode charts for stats |

### Phase 5: Smart Features (Day 10-12)

| # | Feature | Description | Offline? |
|---|---------|-------------|----------|
| 84 | "Did you mean?" | For low-confidence intent matches, show alternatives | ✅ |
| 85 | Smart suggestions | Based on role + time (e.g., "Mark attendance" at 9 AM for teachers) | ✅ |
| 86 | Date parsing | "next Monday", "tomorrow", "15th March" → Date objects | ✅ |
| 87 | Amount parsing | "five hundred" → 500, "₹5000" → 5000 | ✅ |
| 88 | Spell correction | Auto-correct common typos | ✅ |
| 89 | Synonym expansion | "fees" = "dues" = "payment" = "charges" | ✅ |
| 90 | Multi-intent detection | "Show attendance and fee status" → 2 actions | ✅ |
| 91 | Intent chaining | "Show attendance" → "Export it" → chains export | ✅ |
| 92 | Proactive notifications | "You have 3 overdue books" on chat open | ✅ |
| 93 | Confirmation dialogs | "Are you sure?" before destructive actions | ✅ |
| 94 | Progress indicators | "Step 2 of 5" in multi-step flows | ✅ |
| 95 | Undo support | "Undo last action" | ✅ |
| 96 | Save draft conversations | "Continue where you left off" (localStorage) | ✅ |
| 97 | Timeout warnings | "Session expiring in 2 minutes" | ✅ |
| 98 | Satisfaction survey | Post-conversation 1-5 star rating | ✅ |
| 99 | Personalization | Remembers user preferences over time | ✅ |
| 100 | Response variation | Different wording each time | ✅ |

### Phase 6: Advanced Offline Features (Day 13-15)

| # | Feature | Description |
|---|---------|-------------|
| 101 | Conversational forms | "Create a new class" → bot asks name, section, teacher → creates it |
| 102 | Conversational dashboards | "Show dashboard" → returns personalized stats |
| 103 | Conversational reports | "Generate fee report for March" → creates PDF |
| 104 | Natural language queries | "Show me students with <75% attendance" |
| 105 | Smart reminders | "Remind me to collect fees tomorrow" |
| 106 | Smart reports | Daily summary, weekly attendance, monthly fee report |
| 107 | Smart actions | Auto-generate receipts, auto-send parent alerts |
| 108 | Admin KB editor | Edit knowledge base entries via web UI |
| 109 | KB version control | Track changes to knowledge base |
| 110 | Offline mode | Cached responses work without internet |
| 111 | Bot personality | Choose bot's tone (formal, friendly, funny) |
| 112 | Achievement system | "You've asked 100 questions! 🏆" |
| 113 | Custom intents | Admin can add new intents via UI |
| 114 | Response templates | Customize response formats |
| 115 | Multi-language mid-chat | Switch between EN/HI/AS in same conversation |

---

## PART 4: TOTAL COUNT

| Category | Count |
|----------|-------|
| **Confirmed Errors** | 14 |
| **Functional Gaps** | 16 |
| **Features Already Working** | 28 |
| **Offline-Addable Features** | 115 |
| **Total Offline-Addable** | **115** |

---

## PART 5: PRIORITY ORDER

### Day 1: Fix Critical Bugs
- Fix `available` → `isAvailable` (1 line)
- Fix `itemName` → `name` (1 line)
- Fix entity extraction (1 line)
- Fix mobile overflow (1 CSS change)

### Day 2-3: Add Core Intents (15 new)
- homework.list, homework.pending
- routine.view
- notice.list, notice.detail
- complaint.status, complaint.new
- attendance.my, attendance.history
- fee.my
- exam.my, exam.results
- library.my, library.overdue
- dashboard.stats

### Day 4-5: Add Personal Data Intents (12 new)
- hostel.my
- transport.my
- leave.balance, leave.apply
- payroll.my
- canteen.recharge
- Add user auth context to NLP
- Role-specific quick actions
- Smart suggestions

### Day 6-7: Expand Knowledge Base (40 entries)
- Add all 40 KB entries to scanner.js

### Day 8-9: UI Enhancements (15 features)
- Responsive, feedback buttons, search, pin, export, dark mode, keyboard shortcuts, etc.

### Day 10-12: Smart Features (17 features)
- "Did you mean?", spell correction, date parsing, multi-intent, conversational forms, etc.

### Day 13-15: Advanced Features (15 features)
- Conversational dashboards, reports, natural language queries, admin KB editor, etc.

---

## PART 6: FILES THAT NEED CHANGES

| File | Changes Needed |
|------|---------------|
| `server/ai/actions.js` | Fix field names, add 27 new action handlers |
| `server/ai/nlpEngine.js` | Fix entity extraction, fix duplication, add 27 new intents, add auth context, improve follow-up |
| `server/ai/scanner.js` | Add 40 new KB entries |
| `server/routes/chatbot.js` | Pass user context to processMessage |
| `client/src/components/Chatbot.jsx` | Fix mobile width, add feedback buttons, search, dark mode, keyboard shortcuts |
| `client/src/utils/chatbotEngine.js` | Add role-specific quick actions, smart suggestions |
| `client/src/utils/chatbotKnowledge.js` | Add new categories for homework, routine, notices, complaints |
