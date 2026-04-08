# School ERP Flaw Remediation Plan

Date: 2026-04-06
Project: `school-erp`
Source inputs:
- [SCHOOL_ERP_AUDIT_REPORT_2026-04-05.md](./SCHOOL_ERP_AUDIT_REPORT_2026-04-05.md)
- validated flaw list review on 2026-04-06

## Executive Summary

Validated status of the 18-item flaw list:

- `15` confirmed
- `2` partially correct
- `1` rejected

Current objective:
- close the remaining confirmed and partial flaws
- avoid spending time on the rejected one
- sequence changes to reduce production risk

## Validation Matrix

| # | Flaw | Status | Action |
|---|------|--------|--------|
| 1 | Transport access control too broad | Fixed | No further action except regression coverage |
| 2 | `markedBy` missing from `TransportAttendance` | Fixed | No further action except regression coverage |
| 3 | Notification client endpoints wrong | Fixed | No further action except regression coverage |
| 4 | `/api/students` huge payload | Confirmed | Implement pagination + projection |
| 5 | `/api/library/dashboard` huge payload | Confirmed | Split dashboard summary from detail lists |
| 6 | `/api/attendance/report/monthly` slow/heavy | Confirmed | Rewrite aggregation strategy |
| 7 | No pagination on list endpoints | Confirmed | Add shared pagination policy |
| 8 | No response limits on large endpoints | Confirmed | Add list caps + explicit `limit` handling |
| 9 | Backend tests use live dev DB | Confirmed | Add isolated test DB config |
| 10 | Mongoose `new: true` deprecation warnings | Confirmed | Replace with `returnDocument: 'after'` |
| 11 | Export routes lack role authorization | Confirmed | Add role and ownership checks |
| 12 | `/remarks/student/:id` lacks access check | Confirmed | Add ownership/role scope checks |
| 13 | Demo credentials shown on login page | Confirmed | Remove from production UI |
| 14 | JWT stored in `localStorage` | Confirmed | Move to httpOnly cookie or safer session strategy |
| 15 | `ipKeyGenerator` import doesn’t exist | Rejected | Do nothing; current package exports it |
| 16 | Canteen `available` vs `isAvailable` mismatch | Partial | Fix AI/chatbot query path |
| 17 | Payroll `netPay` vs PDF `netSalary` mismatch | Confirmed | Standardize field usage |
| 18 | `ParentDashboard` and `ConductorPanel` have no routes | Partial | Decide whether to add routes or remove dead pages |

## Delivery Strategy

Recommended execution order:

1. Security and authorization
2. Functional correctness
3. Performance and scalability
4. Infrastructure and test isolation
5. Cleanup and dead-code decisions

Reason:
- security flaws are highest risk
- correctness flaws affect trust and output quality
- performance work is safer once behavior is correct
- infra cleanup should follow once the runtime behavior is stable

## Workstream 1: Security And Authorization

### A. Lock down export routes

Issues:
- `#11`

Affected files:
- [export.js](./server/routes/export.js)
- [auth.js](./server/middleware/auth.js)
- [roleCheck.js](./server/middleware/roleCheck.js)
- [accessScope.js](./server/utils/accessScope.js)

Implementation:
- define allowed roles per export family:
  - students: `superadmin`, `accounts`, optionally `teacher` with scoped access
  - attendance: `superadmin`, `teacher`, `accounts`, `hr`
  - fees: `superadmin`, `accounts`
  - exams: `superadmin`, `teacher`
  - library: `superadmin`, `teacher`, `staff`, `hr`
  - staff: `superadmin`, `hr`, `accounts`
  - bulk export: `superadmin` only
- for teacher/student/parent-visible exports, apply ownership filters instead of only role checks
- reject requests lacking authorization with `403`
- add export size limits and required filters where appropriate

Acceptance criteria:
- student cannot export all students
- parent cannot export unrelated records
- teacher can export only classes they can access
- accounts can export fee reports but not staff directory unless explicitly allowed

Tests:
- add negative authorization tests for each export family
- add one positive path per allowed role

### B. Fix remarks access control

Issues:
- `#12`

Affected files:
- [remarks.js](./server/routes/remarks.js)
- [accessScope.js](./server/utils/accessScope.js)

Implementation:
- restrict `GET /api/remarks/student/:id`
- allow:
  - `superadmin`
  - teacher only if they can access the student’s class
  - student only for self
  - parent only for linked child
- return `403` for unrelated access

Acceptance criteria:
- unrelated student gets `403`
- unrelated parent gets `403`
- teacher cannot read students outside assigned classes

Tests:
- add role-scope integration tests

### C. Remove hardcoded demo credentials from UI

Issues:
- `#13`

Affected files:
- [LoginPage.jsx](./client/src/pages/LoginPage.jsx)

Implementation:
- remove demo credential text from the login screen
- if a non-production demo mode is required, gate it behind `REACT_APP_SHOW_DEMO_HINTS === 'true'`

Acceptance criteria:
- production build shows no real credentials
- optional demo hint only appears in explicit demo mode

Tests:
- add a simple render test that the login page does not show live credentials by default

### D. Replace `localStorage` token storage

Issues:
- `#14`

Affected files:
- [AuthContext.jsx](./client/src/contexts/AuthContext.jsx)
- [api.js](./client/src/api/api.js)
- backend auth/login/logout flow in [auth.js](./server/routes/auth.js)
- possibly CORS/session handling in [server.js](./server/server.js)

Implementation options:

Preferred:
- move JWT to `httpOnly`, `secure`, `sameSite` cookie
- use `withCredentials: true` in Axios
- stop reading/writing tokens from `localStorage`

Fallback:
- if cookies are not feasible immediately, store token only in memory and refresh via a short-lived session endpoint

Acceptance criteria:
- frontend no longer uses `localStorage` for auth token
- authenticated requests still work after login
- logout clears session cookie

Tests:
- backend login response sets cookie
- protected routes work using cookie auth
- frontend auth flow still passes build/tests

## Workstream 2: Functional Correctness

### E. Fix canteen availability mismatch in AI/chatbot

Issues:
- `#16` partial

Affected files:
- [actions.js](./server/ai/actions.js)
- [Canteen.js](./server/models/Canteen.js)

Implementation:
- replace `CanteenItem.find({ available: true })` with `CanteenItem.find({ isAvailable: true })`
- also filter out items with `quantityAvailable <= 0` if “available menu” means purchasable

Acceptance criteria:
- AI menu queries return active canteen items
- out-of-stock items are not shown as available unless intentionally desired

Tests:
- add one unit or integration test for canteen AI action

### F. Fix payroll PDF field mismatch

Issues:
- `#17`

Affected files:
- [Payroll.js](./server/models/Payroll.js)
- [pdf.js](./server/routes/pdf.js)
- any UI/AI references to both `netPay` and `netSalary`

Implementation:
- standardize on `netPay`
- update payslip PDF route to render `payroll.netPay`
- keep backward-compatible fallback only if legacy records exist

Acceptance criteria:
- generated payslip shows actual net pay
- no `N/A` for valid payroll records

Tests:
- add PDF route test using seeded payroll data

### G. Decide fate of orphan pages

Issues:
- `#18` partial

Affected files:
- [App.jsx](./client/src/App.jsx)
- [ParentDashboard.jsx](./client/src/pages/ParentDashboard.jsx)
- [ConductorPanel.jsx](./client/src/pages/ConductorPanel.jsx)
- possibly [Dashboard.jsx](./client/src/pages/Dashboard.jsx)

Decision required:
- Option 1: add explicit routes and wire navigation
- Option 2: merge their behavior into existing `/dashboard` or `/transport`
- Option 3: remove dead pages if redundant

Recommended:
- inspect whether these pages provide unique UX
- if redundant, delete them
- if valuable, route them with role-based redirects

Acceptance criteria:
- no orphan page components remain
- role navigation is consistent and intentional

Tests:
- route coverage test for role-specific navigation

## Workstream 3: Performance And Scalability

### H. Add shared pagination and response caps

Issues:
- `#4`, `#7`, `#8`

Affected files:
- [student.js](./server/routes/student.js)
- [library.js](./server/routes/library.js)
- [complaints.js](./server/routes/complaints.js)
- [notices.js](./server/routes/notices.js)
- [notifications.js](./server/routes/notifications.js)
- [attendance.js](./server/routes/attendance.js)
- shared utility in [pagination.js](./server/utils/pagination.js)

Implementation:
- standardize query params:
  - `page`
  - `limit`
  - `sort`
  - `search`
- define hard caps, for example:
  - default `limit=25`
  - max `limit=100`
- return pagination metadata:
  - `items`
  - `page`
  - `limit`
  - `total`
  - `totalPages`

Acceptance criteria:
- no major list endpoint returns full dataset by default
- client pages keep working against paginated responses

Tests:
- route tests for default limit
- route tests for max limit cap

### I. Redesign `/api/students`

Issues:
- `#4`

Affected files:
- [student.js](./server/routes/student.js)
- [StudentsPage.jsx](./client/src/pages/StudentsPage.jsx)

Implementation:
- paginate results
- reduce populated fields for list view
- move full student detail to `GET /api/students/:id`
- add indexed filter fields if missing: `classId`, `section`, `academicYear`, `name`, `admissionNo`

Acceptance criteria:
- default student list response stays small
- detail view still shows full record when needed

### J. Redesign `/api/library/dashboard`

Issues:
- `#5`

Affected files:
- [library.js](./server/routes/library.js)
- [LibraryPage.jsx](./client/src/pages/LibraryPage.jsx)

Implementation:
- split into:
  - `GET /api/library/dashboard` for counts and summary cards
  - `GET /api/library/books` paginated
  - `GET /api/library/transactions` paginated
- update frontend to fetch summary and detail separately

Acceptance criteria:
- dashboard endpoint is summary-only
- books and transactions are loaded independently

### K. Rewrite monthly attendance report

Issues:
- `#6`

Affected files:
- [attendance.js](./server/routes/attendance.js)
- [AttendancePage.jsx](./client/src/pages/AttendancePage.jsx)

Implementation:
- replace per-student `countDocuments` loop with aggregation pipeline
- aggregate monthly totals in one query grouped by student
- only fetch requested class or scoped teacher classes
- paginate report rows if needed

Acceptance criteria:
- response time materially improves for current dataset
- no per-student N+1 count pattern remains

## Workstream 4: Infrastructure And Testing

### L. Isolate backend tests from dev DB

Issues:
- `#9`

Affected files:
- [api.integration.test.js](./server/tests/api.integration.test.js)
- [package.json](./server/package.json)
- `.env.example`
- test bootstrap in [server.js](./server/server.js)

Implementation:
- add `MONGODB_URI_TEST`
- make test runner fail if test DB is not configured
- optionally use a separate database name like `school_erp_test`
- keep fixture cleanup, but stop using the primary dev DB

Acceptance criteria:
- `npm test` never mutates the normal dev database
- tests remain reproducible

### M. Remove Mongoose deprecation warnings

Issues:
- `#10`

Affected files:
- multiple route/model files using `findOneAndUpdate(..., { new: true })`

Implementation:
- replace `new: true` with `returnDocument: 'after'`
- prioritize hot paths and test helpers first

Acceptance criteria:
- backend test run produces no Mongoose deprecation warnings

## Workstream 5: Cleanup And Guardrails

### N. Add route-level authorization regression coverage

Purpose:
- prevent repeat of flaws `#11` and `#12`

Implementation:
- extend [api.integration.test.js](./server/tests/api.integration.test.js)
- add negative tests for:
  - export routes
  - remarks route
  - student/parent ownership routes
  - transport routes

### O. Add performance smoke checks

Purpose:
- keep payload size and latency visible

Implementation:
- create a lightweight script that logs:
  - route
  - payload bytes
  - duration
- fail CI or local verification when thresholds are exceeded

## Recommended Execution Schedule

### Phase 1: Immediate

Target:
- `#11`, `#12`, `#13`, `#17`

Reason:
- these are high-risk and relatively low-effort

### Phase 2: High Priority

Target:
- `#14`, `#16`, `#18`

Reason:
- session storage is important but touches both client and server
- canteen AI and orphan routes are smaller cleanup items

### Phase 3: Performance

Target:
- `#4`, `#5`, `#6`, `#7`, `#8`

Reason:
- these need coordinated client/server changes

### Phase 4: Infrastructure

Target:
- `#9`, `#10`

Reason:
- lower direct user impact, but important for long-term maintenance

## Suggested Owner Checklist

For each flaw:

1. write failing test
2. implement smallest safe fix
3. verify role/ownership behavior
4. verify build/test still pass
5. update report status from `Open` to `Fixed`

## Final Recommendation

Do not start with all flaws in one branch.

Use this sequence:

1. security branch
2. correctness branch
3. performance branch
4. infra branch

This keeps risk contained and makes regressions easier to isolate.
