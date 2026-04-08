# Chatbot Working Plan From Bhaskar Final Plan

Date: 2026-04-06
Project: `school-erp`
Primary references:
- `BHASKAR_FINAL_PLAN.md`
- `CHATBOT_ERRORS_AND_MISSING_FEATURES.md`

## Objective

Turn the current chatbot into a safer, consistent, offline-capable school ERP assistant by:

1. fixing the confirmed chatbot problems
2. replacing weak scraped knowledge with a large curated knowledge base
3. unifying the frontend and backend chatbot behavior
4. adding high-value offline intents and guided workflows

## Deliverables

1. Curated offline knowledge base under `server/ai/kb/`
2. Scanner support for curated knowledge loading
3. Secure chatbot request flow with user scope
4. Unified chatbot behavior across UI and server
5. Role-aware quick actions and follow-up suggestions
6. Expanded offline intents for student, parent, teacher, admin, canteen, and transport use cases

## Problem-to-Workstream Mapping

### Workstream 1: Security and scope

Problems addressed:

- chatbot bypasses role-based data protection
- context shared under `anonymous`
- sensitive password guidance exposed
- parent and student data scope risk

Required changes:

- pass `req.user.id` and `req.user` into NLP processing
- enforce role and ownership checks in chatbot actions
- remove weak credential guidance from chatbot and KB
- keep logs and history aligned to the real user/session

Files:

- `server/routes/chatbot.js`
- `server/ai/nlpEngine.js`
- `server/ai/actions.js`
- `server/models/ChatbotLog.js`
- `client/src/pages/ImportDataPage.jsx`
- `client/src/utils/chatbotKnowledge.js`

Definition of done:

- student cannot ask for unrelated student or payroll data
- parent can only ask for linked child data
- teacher scope follows class ownership
- chatbot no longer exposes a default password workflow

### Workstream 2: Knowledge base replacement

Problems addressed:

- KB generated from raw UI string scraping
- curated KB too small
- low-quality fallback answers
- weak policy coverage

Required changes:

- load curated KB from `server/ai/kb/curatedKnowledgeBase.json`
- keep scraped UI text as low-priority supplement, not primary knowledge
- expand curated content across major ERP modules
- version the generated aggregate KB as before

Files:

- `server/ai/scanner.js`
- `server/ai/kb/curatedKnowledgeBase.json`

Definition of done:

- curated entries become the main knowledge source
- chatbot can answer common policy and workflow questions without relying on random JSX text
- KB size is materially larger than the previous manual seed set

### Workstream 3: Backend chatbot correctness

Problems addressed:

- canteen menu field mismatch
- incomplete follow-up logic
- incomplete analytics storage
- entity mapping needs runtime verification and hardening

Required changes:

- fix `available` vs `isAvailable`
- fix `itemName` vs `name`
- persist `responseTime`
- add runtime-safe entity extraction fallback
- extend follow-up handling beyond two narrow cases

Files:

- `server/ai/actions.js`
- `server/ai/nlpEngine.js`
- `server/routes/chatbot.js`

Definition of done:

- canteen menu returns real items
- analytics show actual response timing
- entity extraction is tolerant to `option` or `utteranceText`
- follow-up prompts are useful for the top intents

### Workstream 4: Frontend chatbot unification

Problems addressed:

- frontend and backend are two different chatbot brains
- local fallback is silent
- server chat history not used
- fallback language support is incomplete
- mobile layout is weak

Required changes:

- use backend as the primary source for welcome, suggestions, and responses
- label offline fallback clearly in the UI
- load server history on open
- make fallback language map complete
- fix chatbot width on mobile

Files:

- `client/src/components/Chatbot.jsx`
- `client/src/utils/chatbotEngine.js`
- `client/src/utils/chatbotKnowledge.js`

Definition of done:

- user can tell whether a response is live or fallback
- refreshed sessions still show server history
- mobile layout stays inside viewport
- local fallback still works, but it is clearly secondary

### Workstream 5: Role-aware UX

Problems addressed:

- quick actions are role-blind
- chatbot exposes routes instead of guided actions
- suggestions are generic

Required changes:

- role-filter quick actions
- add guided action language instead of route-only instructions
- tailor suggestions by role and current context

Files:

- `client/src/utils/chatbotEngine.js`
- `client/src/components/Chatbot.jsx`
- `server/ai/actions.js`

Definition of done:

- students see student actions
- parents see parent actions
- staff sees staff actions
- responses feel procedural instead of technical

## Offline capability roadmap

These features can be delivered without external AI APIs. They rely on the local codebase, MongoDB, and curated knowledge.

### Phase A: Core self-service intents

1. `attendance.my`
2. `attendance.history`
3. `fee.my`
4. `exam.my`
5. `exam.results`
6. `homework.list`
7. `homework.pending`
8. `routine.view`
9. `notice.list`
10. `complaint.status`
11. `library.my`
12. `library.overdue`
13. `hostel.my`
14. `transport.my`
15. `leave.balance`

### Phase B: Role-specific operational intents

1. `dashboard.stats`
2. `teacher.classSummary`
3. `accounts.collectionSummary`
4. `canteen.salesSummary`
5. `transport.routeSummary`
6. `hr.staffDirectory`
7. `payroll.my`
8. `remarks.my`
9. `notifications.unread`
10. `notices.priority`

### Phase C: Guided workflow intents

1. complaint filing guide
2. leave application guide
3. fee collection guide
4. admission guide
5. homework publishing guide
6. library issue guide
7. hostel allocation guide
8. transport reassignment guide
9. certificate request guide
10. result publishing guide

## Knowledge-base growth plan

The curated KB should grow in this order:

### Tier 1: Must-have policy and workflow coverage

- students
- attendance
- fee
- exams
- library
- homework
- routine
- notices
- complaints
- canteen
- hostel
- transport
- leave
- payroll

### Tier 2: Parent and student support content

- fee due explanation
- attendance explanation
- report card explanation
- homework help
- complaint escalation
- hostel rules
- transport route change
- certificate process

### Tier 3: Admin and operations support content

- user management
- imports
- archive and restore
- audit expectations
- dashboard summary definitions
- notification policy

## Execution order

### Sprint 1

1. finalize curated KB loading
2. remove default password guidance
3. pass user scope into NLP processing
4. fix canteen field mismatch
5. persist `responseTime`

### Sprint 2

1. make backend the primary chatbot brain
2. load server-side chat history in UI
3. add fallback labeling
4. fix fallback language support
5. fix mobile width

### Sprint 3

1. add self-service intents
2. add role-aware quick actions
3. improve follow-up logic
4. add clarifying questions for low-confidence replies

### Sprint 4

1. add guided workflow intents
2. add feedback controls
3. add conversation search and export
4. expand curated KB further based on analytics

## Verification checklist

### Security

- parent cannot access unrelated child data in chat
- student cannot access staff or payroll data
- teacher scope is limited correctly

### Correctness

- canteen menu returns real active items
- wallet lookup works only for authorized users
- chat history is tied to the real user
- analytics show non-zero response times

### UX

- mobile chatbot stays on screen
- fallback responses are labeled
- role-aware quick actions change by user role
- help and suggestions remain available when the server is down

### Knowledge

- curated KB loads successfully
- aggregate KB file still generates
- policy answers come from curated content first

## File list for implementation

Server:

- `server/ai/scanner.js`
- `server/ai/kb/curatedKnowledgeBase.json`
- `server/ai/nlpEngine.js`
- `server/ai/actions.js`
- `server/routes/chatbot.js`
- `server/models/ChatbotLog.js`

Client:

- `client/src/components/Chatbot.jsx`
- `client/src/utils/chatbotEngine.js`
- `client/src/utils/chatbotKnowledge.js`
- `client/src/pages/ImportDataPage.jsx`

## Final recommendation

Do not try to implement every possible chatbot feature in one pass.

Build in this order:

1. secure it
2. unify it
3. make its knowledge reliable
4. add high-value self-service intents
5. then expand UX and intelligence features

That path addresses the actual problems from `BHASKAR_FINAL_PLAN.md` without turning the chatbot into another unstable subsystem.
