# Bhaskar Final Plan

Date: 2026-04-06
Project: `school-erp`
Focus: verify earlier claims, inspect the offline chatbot, and define a fix + expansion roadmap

## 1. What I verified

This document is based on direct code inspection of:

- `server/routes/chatbot.js`
- `server/ai/nlpEngine.js`
- `server/ai/actions.js`
- `server/ai/scanner.js`
- `server/models/ChatbotLog.js`
- `server/middleware/rateLimiter.js`
- `server/models/Canteen.js`
- `client/src/components/Chatbot.jsx`
- `client/src/utils/chatbotEngine.js`
- `client/src/utils/chatbotKnowledge.js`
- `client/src/App.jsx`
- `client/src/components/Sidebar.jsx`
- `client/src/pages/LoginPage.jsx`
- `client/src/pages/ImportDataPage.jsx`

I also checked current localhost availability from this session:

- `http://localhost:5000/api/health` -> not reachable from this session
- `http://localhost:3000` -> not reachable from this session

So the earlier statement that both services are running is not confirmed right now.

## 2. Earlier claims: correct vs incorrect

### Confirmed correct

1. `ParentDashboard.jsx` exists but is not routed in `client/src/App.jsx`.
2. `ConductorPanel.jsx` exists but is not routed in `client/src/App.jsx`.
3. The chatbot canteen menu intent is broken because `actions.js` queries `available` and `itemName`, while the model uses `isAvailable` and `name`.
4. `server/package.json` still says `"main": "index.js"` while the actual entry is `server.js`.
5. `client/src/components/Navbar.jsx` still hardcodes `http://localhost:5000/api/notifications`.
6. `client/src/pages/ImportDataPage.jsx` still exposes a default student password field.
7. The project still uses many `alert()` calls in the frontend.
8. Twilio credentials are empty in `server/.env`.

### Confirmed incorrect or stale

1. Export routes do not lack authorization anymore.
   They now use `auth` and `roleCheck(...)` in `server/routes/export.js`.
2. `GET /api/remarks/student/:id` is not open anymore.
   It now checks `canUserAccessStudent(...)` in `server/routes/remarks.js`.
3. Sidebar menu is not shown to all roles anymore.
   `client/src/components/Sidebar.jsx` filters by `item.roles.includes(user?.role)`.
4. Route-level role checks are not broadly missing in the frontend.
   `client/src/App.jsx` uses `ProtectedRoute allowedRoles={...}` on many pages.
5. `ipKeyGenerator` is not missing from `express-rate-limit`.
   With the installed version `8.3.1`, it is exported.
6. Login page is not always showing demo credentials.
   It only shows them when `REACT_APP_SHOW_DEMO_HINTS === 'true'`.

## 3. Cross-check of `CHATBOT_ERRORS_AND_MISSING_FEATURES.md`

I also reviewed `CHATBOT_ERRORS_AND_MISSING_FEATURES.md` and normalized its chatbot-specific claims before merging them here.

### Status of the 14 claimed chatbot errors

- `11` are confirmed from code inspection
- `1` needs runtime verification before changing behavior
- `1` is rejected
- `1` is a product decision, not a bug by itself

### Confirmed additions from that report

1. The client silently falls back to local chatbot logic when the server request fails.
2. The chatbot modal uses fixed `w-96`, so it is likely to overflow on narrow phones.
3. The fallback language map in `client/src/components/Chatbot.jsx` only includes English.
4. Quick actions are role-blind in `client/src/utils/chatbotEngine.js`.
5. The curated manual knowledge base in `server/ai/scanner.js` is still very small.
6. The follow-up handler in `server/ai/nlpEngine.js` only covers a tiny number of cases.

### Needs runtime verification

1. The entity mapping in `server/ai/nlpEngine.js` currently reads `entity.option`.
   Bundled `@nlpjs` code suggests `utteranceText` may be a safer field to use or fallback to, but this should be verified against actual `manager.process(...)` output before changing behavior.

### Rejected

1. The report claimed `addNamedEntityText(...)` is being called with a broken signature.
   Static inspection does not establish that as a current bug.

### Product decision, not a bug by itself

1. `POST /api/chatbot/chat` requires authentication.
   For an internal ERP assistant, that is valid unless guest chat is explicitly required.

## 4. What already works in the chatbot

These parts are already present and should be preserved while fixing the broken parts:

- authenticated chatbot API with rate limiting
- multilingual NLP setup for English, Hindi, and Assamese
- dynamic entity loading from MongoDB for students, staff, classes, vehicles, and books
- chatbot query logging with TTL cleanup in `ChatbotLog`
- server-side chat history endpoint
- admin analytics endpoint
- local slash commands such as `/help`, `/lang`, and `/clear`
- quick-action and suggestion UI
- typing indicator and chat toggle UI

## 5. Real chatbot problems found

## Critical

### 1. Chatbot bypasses role-based data protection

Current design:

- `POST /api/chatbot/chat` only checks that the user is authenticated.
- The NLP actions in `server/ai/actions.js` do not receive `req.user` scope.
- The chatbot can query student, payroll, attendance, transport, and staff data without intent-level authorization.

Impact:

- A logged-in user can ask the chatbot for data outside their role.
- This is the biggest chatbot risk in the codebase.

### 2. Conversation context is shared under `anonymous`

Current design:

- `processMessage(message, language = 'en', userId = 'anonymous')`
- `server/routes/chatbot.js` calls `processMessage(trimmedMessage, selectedLanguage)` without passing `req.user.id`

Impact:

- All users share one fallback context key.
- Follow-up handling can mix conversation state across users.
- Analytics and personalization are weaker than they appear.

### 3. Sensitive password guidance is embedded in chatbot content

Found in:

- `server/ai/actions.js`
- `server/ai/scanner.js`
- `client/src/utils/chatbotKnowledge.js`
- `client/src/pages/ImportDataPage.jsx`

Impact:

- The chatbot and UI normalize weak/default-password workflows.
- This is a security and operational hygiene problem.

### 4. Canteen menu intent is functionally broken

Mismatch:

- model: `isAvailable`, `name`
- chatbot query: `available`, `itemName || name`

Impact:

- Menu results can be empty even when data exists.

## High

### 5. Frontend chatbot and backend chatbot are two different brains

Current design:

- UI sends normal messages to backend `/api/chatbot/chat`
- UI still uses local `chatbotEngine.js` for:
  - welcome message
  - slash commands
  - quick actions
  - suggestions
  - fallback replies

Impact:

- Inconsistent answers
- Inconsistent history
- Inconsistent language behavior
- Hard to debug and improve

### 6. Chat history exists on the server but the client does not use it

Current design:

- `GET /api/chatbot/history` exists
- `client/src/components/Chatbot.jsx` only reads local history from `chatbotEngine.js`

Impact:

- Real backend conversation history is not shown
- Cross-device or refresh continuity is lost

### 7. Analytics are incomplete because `responseTime` is never stored

Current design:

- `ChatbotLog` schema includes `responseTime`
- analytics endpoint aggregates average response time
- route never saves `responseData.responseTime`

Impact:

- Analytics look richer than the actual stored data

### 8. Knowledge base is generated by raw UI string scraping

Current design in `server/ai/scanner.js`:

- scans pages and components
- pulls string literals
- pulls comments
- pulls labels and placeholders
- writes all of it into `knowledgeBase.json`

Impact:

- stale or internal text can become searchable
- low-quality answers
- duplicated content
- security leakage risk from copied strings

## Medium

### 9. `dangerouslySetInnerHTML` is used in chat message rendering

The component attempts sanitization first, but the rendering model is still fragile.

Impact:

- unnecessary XSS risk surface
- formatting bugs
- harder to maintain

### 10. Quick actions and suggestions are not role-aware

Current design:

- fixed quick actions from local static knowledge
- not filtered by user role

Impact:

- students can see admin-style prompts
- canteen users can see exam prompts
- not friendly enough

### 11. The chatbot exposes operational paths instead of guided actions

Examples:

- “Go to `/students`”
- “Go to `/attendance`”

Impact:

- not very friendly
- not task-driven
- weak UX for non-technical staff

### 12. Follow-up logic is too shallow

Current design:

- hardcoded follow-up conditions in `handleFollowUp(...)`
- only a few intent combinations

Impact:

- conversation feels brittle
- limited natural continuity

### 13. The client silently hides when the server answer is a fallback

Current design:

- `client/src/components/Chatbot.jsx` catches server errors
- it then uses `chatbotEngine.js` locally without telling the user the answer is offline fallback content

Impact:

- users cannot distinguish live data answers from static fallback answers
- debugging and trust both get worse

### 14. The chatbot modal is not mobile-safe

Current design:

- the chatbot container uses fixed `w-96`

Impact:

- the modal can overflow small screens
- mobile usage suffers even before content quality is fixed

### 15. Fallback language support is incomplete

Current design:

- `FALLBACK_LANGUAGES` in `client/src/components/Chatbot.jsx` only defines English

Impact:

- if the imported language map fails, Hindi and Assamese degrade badly

### 16. The curated knowledge base is too small

Current design:

- `server/ai/scanner.js` defines only a small set of manual knowledge entries
- most additional content comes from JSX scraping, not curated support content

Impact:

- coverage is weak for real school workflows
- many answers depend on low-quality scraped text instead of approved content

## 6. Root cause summary

The chatbot is not one system. It is four partial systems:

1. backend NLP engine
2. backend scanned knowledge base
3. frontend local keyword chatbot
4. frontend chat UI

Because of that split:

- authorization is weak
- answers are inconsistent
- analytics are incomplete
- history is fragmented
- maintenance cost is high

## 7. Fix plan

## Phase 0: Stabilize and secure first
Timeline: 1-2 days

### A. Lock down data access

Change:

- pass `req.user` into chatbot processing
- add role-aware authorization per intent
- add student/parent/teacher data-scope checks inside chatbot actions

Files:

- `server/routes/chatbot.js`
- `server/ai/nlpEngine.js`
- `server/ai/actions.js`

Definition of done:

- student cannot retrieve payroll
- parent cannot retrieve unrelated student details
- teacher can only access their own class scope where applicable

### B. Fix context isolation

Change:

- call `processMessage(trimmedMessage, selectedLanguage, req.user.id, req.user)`
- store context by `userId` and optional `sessionId`

Files:

- `server/routes/chatbot.js`
- `server/ai/nlpEngine.js`
- `server/models/ChatbotLog.js`

Definition of done:

- follow-up questions are isolated per user
- logs can be tied to real user sessions

### C. Remove password leakage

Change:

- remove `student@123` from chatbot answers
- remove it from scanned/manual KB entries
- remove visible default-password guidance from import UI

Files:

- `server/ai/actions.js`
- `server/ai/scanner.js`
- `client/src/utils/chatbotKnowledge.js`
- `client/src/pages/ImportDataPage.jsx`

Definition of done:

- chatbot does not reveal or normalize default credentials

### D. Fix broken canteen intent

Change:

- query `isAvailable: true`
- render `name`

Files:

- `server/ai/actions.js`

Definition of done:

- “canteen menu” returns real menu items

## Phase 1: Make server the single source of truth
Timeline: 2-3 days

### A. Simplify frontend chatbot

Change:

- keep frontend as UI only
- move quick actions, suggestions, welcome text, and help text to backend
- stop using local fallback knowledge except as last-resort offline mode

Files:

- `client/src/components/Chatbot.jsx`
- `client/src/utils/chatbotEngine.js`
- `client/src/utils/chatbotKnowledge.js`
- `server/routes/chatbot.js`

Definition of done:

- backend drives the answer content
- frontend only renders messages and sends actions

### B. Use real chat history

Change:

- load `/api/chatbot/history` on open
- merge history with current session cleanly
- add clear-history endpoint if needed

Files:

- `client/src/components/Chatbot.jsx`
- `server/routes/chatbot.js`

Definition of done:

- refreshing the page preserves recent conversation

### C. Save analytics correctly

Change:

- store `responseTime`
- optionally store `sessionId`, intent confidence, and fallback type

Files:

- `server/routes/chatbot.js`
- `server/models/ChatbotLog.js`

Definition of done:

- analytics endpoint reports meaningful averages

## Phase 2: Rebuild the knowledge base properly
Timeline: 3-5 days

### A. Replace raw scraping with curated documents

Stop treating UI strings and comments as trusted knowledge.

New KB structure:

- `title`
- `module`
- `audience`
- `language`
- `intentTags`
- `answer`
- `sourceType`
- `approved`
- `version`
- `lastReviewedAt`

### B. Split knowledge by type

Create separate collections or files for:

1. policy knowledge
2. user help guides
3. workflow instructions
4. live operational queries
5. FAQ fallback

### C. Add approval workflow

Only curated and approved KB entries should be searchable.

Suggested structure:

- `server/ai/kb/policies/*.json`
- `server/ai/kb/help/*.json`
- `server/ai/kb/faqs/*.json`

### D. Remove low-quality scan sources

Stop indexing:

- comments
- arbitrary string literals
- placeholder-only text
- security-sensitive literals

## Phase 3: Make the chatbot friendly
Timeline: 3-4 days

### A. Role-aware greeting

Examples:

- admin: “What would you like to manage today?”
- teacher: “Need attendance, homework, or marks help?”
- parent: “Need fee, attendance, or result information for your child?”
- student: “Need timetable, homework, or result help?”

### B. Role-aware quick actions

Examples:

- superadmin: users, imports, reports, audit
- teacher: attendance, homework, marks, remarks
- student: my attendance, my fees, my results, notices
- parent: child attendance, child fees, notices, complaints
- canteen: menu, wallet, sales
- conductor: route, attendance, student manifest

### C. Better answer structure

Every answer should optionally return:

- short answer
- action buttons
- related suggestions
- permission note if access is denied
- source or module reference

### D. Friendlier language

Replace route-style language with plain workflows.

Bad:

- “Go to `/students`”

Better:

- “Open Student Admission from the left menu, then choose Admit Student.”

## Phase 4: Improve intent design
Timeline: 4-6 days

### A. Create an intent registry

Each intent should define:

- `id`
- `allowedRoles`
- `requiredEntities`
- `handler`
- `fallbackPrompt`
- `auditLevel`

### B. Add high-value intents first

Priority intents:

- my attendance
- my fees
- my results
- child attendance
- child fee history
- teacher class summary
- today’s notices
- canteen menu
- wallet balance
- bus route info
- complaint status

### C. Add confidence handling

If NLP confidence is low:

- do not guess
- show top 3 intent options
- ask one clarifying question

## Phase 5: UX and safety improvements
Timeline: 2-3 days

### A. Remove `dangerouslySetInnerHTML`

Use structured rendering:

- paragraphs
- bullet arrays
- badges
- action chips

### B. Add feedback loop

Per answer:

- helpful
- not helpful
- wrong data
- needs human support

### C. Add human escalation path

Examples:

- create complaint
- open notices
- contact admin
- contact class teacher

### D. Add retry and empty-state handling

Examples:

- server unavailable
- no records found
- insufficient permission
- entity not recognized

## 8. Offline feature backlog to add after the fixes

The chatbot errors report includes a large offline-only idea list. That list is useful, but it should be converted into a prioritized backlog instead of being treated as one immediate implementation batch.

### Highest-value offline capabilities missing today

These can all be added without external AI APIs:

- homework queries
- timetable and routine queries
- notices queries
- complaint status queries
- self-service student queries such as my attendance, my fees, and my results
- parent child-specific queries
- leave balance and leave guidance
- library overdue and borrowed-book queries
- hostel and transport self-service queries
- role-specific dashboard summary queries

### Highest-value offline UX additions

- helpful / not helpful feedback buttons
- conversation search
- pin or save important replies
- export conversation to text or PDF
- message timestamps
- copy response action
- responsive mobile width
- dark mode support
- keyboard shortcuts
- role-aware quick actions

### Highest-value offline intelligence additions

- clarifying questions for low-confidence matches
- typo handling and synonym expansion
- date parsing such as `tomorrow` or `next Monday`
- multi-intent handling
- intent chaining such as "show attendance" then "export it"
- proactive suggestions based on role and context
- local draft continuation

### Highest-value curated KB additions

Start with approved entries for:

- homework policy
- routine and period schedule rules
- notices and circulars workflow
- complaints workflow
- leave policy
- exam and re-exam rules
- fee concession and refund policy
- transport route change policy
- hostel leave and room change policy
- certificate request process

## 9. Feature expansion roadmap

Do not start “500 features” immediately. First finish Phases 0-2.

After the chatbot is secure and unified, use this feature-capacity roadmap:

### Wave 1: 60 core assistant features

- role-aware greetings
- structured quick actions
- better history
- feedback loop
- clarifying questions
- friendly multilingual answers
- safe workflow guidance
- module-aware suggestions

### Wave 2: 90 personal productivity features

- “my” dashboards
- reminders
- personal summaries
- upcoming tasks
- unread notices
- fee due nudges
- homework reminders
- exam countdowns

### Wave 3: 110 staff workflow features

- teacher class summary
- attendance shortcuts
- marks-entry help
- leave workflow help
- payroll query flows
- complaints triage
- library operations assistant
- hostel allocation assistant

### Wave 4: 120 analytics and operations features

- daily school summary
- pending approvals
- late fee risk summaries
- low attendance alerts
- notice reach summary
- canteen sales snapshots
- transport attendance snapshots
- audit summaries

### Wave 5: 120 advanced assistant features

- guided multi-step workflows
- action confirmation flows
- natural-language search
- saved prompts
- proactive alerts
- multilingual paraphrasing
- document-grounded answers
- role-specific assistant personas

Total roadmap capacity: 500 features across five waves.

## 10. Recommended implementation order

Week 1:

1. secure chatbot access by role and data scope
2. isolate user context properly
3. remove password leakage
4. fix canteen intent
5. store response time and session metadata

Week 2:

6. make backend the single chatbot brain
7. connect real history to UI
8. replace raw KB scraping with curated KB seed files
9. add role-aware quick actions and greetings

Week 3:

10. add high-value intents for student, parent, teacher, admin
11. improve follow-up logic and clarifying questions
12. remove `dangerouslySetInnerHTML`
13. add answer feedback and escalation

Week 4:

14. add analytics dashboard improvements
15. add multilingual content review
16. add curated operational knowledge packs
17. begin Wave 1 feature expansion

## 11. Files that should be changed first

Server:

- `server/routes/chatbot.js`
- `server/ai/nlpEngine.js`
- `server/ai/actions.js`
- `server/ai/scanner.js`
- `server/models/ChatbotLog.js`

Client:

- `client/src/components/Chatbot.jsx`
- `client/src/utils/chatbotEngine.js`
- `client/src/utils/chatbotKnowledge.js`
- `client/src/pages/ImportDataPage.jsx`

Secondary cleanup:

- `client/src/components/Navbar.jsx`
- `client/src/App.jsx`

## 12. Acceptance criteria

The chatbot will be considered fixed enough for real use when all of these are true:

1. no user can retrieve out-of-scope data through chat
2. conversation context is isolated per user/session
3. frontend and backend no longer disagree on answers
4. chat history survives refresh
5. analytics store real response times
6. no passwords or security-sensitive defaults are exposed in KB or UI
7. role-aware quick actions are visible
8. top 10 daily chat intents can be answered reliably
9. canteen, attendance, fee, and result queries work with real data
10. fallback answers come from curated content only

## 13. Bottom line

The chatbot is not ready for trusted production use yet.

The main reason is not UI polish. The main reason is authorization and architecture:

- it can bypass role boundaries
- it mixes user context
- it has two competing answer engines
- it indexes uncurated content

Fix those first. After that, expanding to a much friendlier and much larger assistant is realistic.
