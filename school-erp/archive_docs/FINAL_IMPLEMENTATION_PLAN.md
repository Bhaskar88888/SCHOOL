# 🏫 School ERP — FINAL IMPLEMENTATION PLAN
## Security → Correctness → Performance → Production → Hostinger

> **Date:** 2026-04-06
> **Sources:** SCHOOL_ERP_AUDIT_REPORT_2026-04-05.md · SCHOOL_ERP_FLAW_REMEDIATION_PLAN_2026-04-06.md
> FINAL_PRODUCTION_REPORT.md · PRODUCTION_ROADMAP.md · ALL_PROBLEMS_FIXED.md
> FIXES_APPLIED.md · FEATURE_COMPLETION_CHECKLIST.md + full source code review

---

## TABLE OF CONTENTS

1. [What's Already Done](#1-whats-already-done)
2. [Full Flaw Registry — 18 Items](#2-full-flaw-registry--18-items)
3. [Phase 1 — Security & Authorization](#phase-1--security--authorization)
4. [Phase 2 — Functional Correctness](#phase-2--functional-correctness)
5. [Phase 3 — Performance & Scalability](#phase-3--performance--scalability)
6. [Phase 4 — Infrastructure & Testing](#phase-4--infrastructure--testing)
7. [Phase 5 — Production Config](#phase-5--production-config)
8. [Phase 6 — Hostinger Deployment](#phase-6--hostinger-vps-deployment)
9. [Final Summary & Targets](#final-summary--targets)

---

## 1. What's Already Done ✅

| Area | Status |
|------|--------|
| 27 Models, 22 Route files, 150+ endpoints | ✅ Complete |
| JWT auth, RBAC (9 roles), session timeout | ✅ Complete |
| Data isolation middleware (student/parent scope) | ✅ Complete |
| Error boundaries, loading states, toasts | ✅ Complete |
| Rate limiting, Helmet security headers, CORS | ✅ Complete |
| PDF generation (receipts, report cards, payslips) | ✅ Complete |
| Offline AI chatbot (node-nlp, 3-language) | ✅ Complete |
| Winston logging, audit trail, automated DB backups | ✅ Complete |
| Transport bus-level access control | ✅ Fixed (Apr 5 audit) |
| `markedBy` field on TransportAttendance schema | ✅ Fixed (Apr 5 audit) |
| Client notification API endpoints corrected | ✅ Fixed (Apr 5 audit) |
| Backend integration test suite — 7/7 passing | ✅ Complete |
| Client production build passing, tests 5/5 | ✅ Complete |
| SMS service with dev fallback | ✅ Complete |
| Input validation middleware | ✅ Complete |

---

## 2. Full Flaw Registry — 18 Items

| # | Flaw | Remediation Status | Phase |
|---|------|--------------------|-------|
| 1 | Transport access control too broad | ✅ Fixed | Done |
| 2 | `markedBy` missing from TransportAttendance | ✅ Fixed | Done |
| 3 | Notification client endpoints wrong | ✅ Fixed | Done |
| 4 | `/api/students` huge payload (7.9 MB) | Open | Phase 3 |
| 5 | `/api/library/dashboard` huge payload (10.3 MB) | Open | Phase 3 |
| 6 | `/api/attendance/report/monthly` slow 5.2s N+1 | Open | Phase 3 |
| 7 | No pagination on list endpoints | Open | Phase 3 |
| 8 | No response limits on large endpoints | Open | Phase 3 |
| 9 | Backend tests use live dev DB | Open | Phase 4 |
| 10 | Mongoose `new: true` deprecation warnings | Open | Phase 4 |
| 11 | Export routes lack role authorization | Open | Phase 1 |
| 12 | `/remarks/student/:id` no access check | Open | Phase 1 |
| 13 | Demo credentials shown on login page | Open | Phase 1 |
| 14 | JWT stored in `localStorage` | Open | Phase 2 |
| 15 | `ipKeyGenerator` import doesn't exist | ❌ Rejected | Do nothing |
| 16 | Canteen `available` vs `isAvailable` mismatch | Partial | Phase 2 |
| 17 | Payroll `netPay` vs PDF `netSalary` mismatch | Open | Phase 1 |
| 18 | `ParentDashboard` / `ConductorPanel` no routes | Partial | Phase 2 |

---

---

# PHASE 1 — Security & Authorization 🔒

**Goal:** No unauthorized data access. No credentials in UI. No broken PDF fields.
**Flaws closed:** #11, #12, #13, #17
**Estimated time: 2 hours**

---

## 1.1 — Lock Down Export Routes (Flaw #11)

**File:** `server/routes/export.js`

**Problem:** Every export route only checks `auth` — any authenticated user (student, parent, conductor) can export the entire student list, all fee payments, all staff records.

**Fix — add `roleCheck` to every export endpoint:**

```javascript
// Students export — superadmin + accounts only
router.get('/students/pdf',   auth, roleCheck('superadmin', 'accounts'), async ...);
router.get('/students/excel', auth, roleCheck('superadmin', 'accounts'), async ...);

// Attendance export — superadmin, teacher, accounts, hr
router.get('/attendance/pdf',   auth, roleCheck('superadmin','teacher','accounts','hr'), async ...);
router.get('/attendance/excel', auth, roleCheck('superadmin','teacher','accounts','hr'), async ...);

// Fee export — superadmin, accounts only
router.get('/fees/pdf',   auth, roleCheck('superadmin', 'accounts'), async ...);
router.get('/fees/excel', auth, roleCheck('superadmin', 'accounts'), async ...);

// Exam export — superadmin, teacher
router.get('/exams/pdf',         auth, roleCheck('superadmin', 'teacher'), async ...);
router.get('/exam-results/pdf',  auth, roleCheck('superadmin', 'teacher'), async ...);
router.get('/exam-results/excel',auth, roleCheck('superadmin', 'teacher'), async ...);

// Library export — superadmin, teacher, staff, hr
router.get('/library/pdf',   auth, roleCheck('superadmin','teacher','staff','hr'), async ...);
router.get('/library/excel', auth, roleCheck('superadmin','teacher','staff','hr'), async ...);

// Staff export — superadmin, hr, accounts
router.get('/staff/pdf',   auth, roleCheck('superadmin', 'hr', 'accounts'), async ...);
router.get('/staff/excel', auth, roleCheck('superadmin', 'hr', 'accounts'), async ...);

// Bulk export — superadmin only
router.get('/bulk-export', auth, roleCheck('superadmin'), async ...);

// Individual report card — student can access own, parent can access child's
router.get('/students/:id/report-card', auth, async (req, res) => {
  // Add ownership check:
  const student = await Student.findById(req.params.id);
  if (!student) return res.status(404).json({ msg: 'Not found' });
  const { canUserAccessStudent } = require('../utils/accessScope');
  if (!(await canUserAccessStudent(req.user, req.params.id))) {
    return res.status(403).json({ msg: 'Access denied' });
  }
  // ... rest of handler unchanged
});
```

**Also add export size limits** — replace unbounded `Student.find()` with `.limit(1000)` to prevent accidental memory exhaustion on large datasets.

**Acceptance criteria:**
- Student hitting `/api/export/students/pdf` → `403`
- Parent hitting `/api/export/fees/pdf` → `403`
- Teacher hitting `/api/export/staff/pdf` → `403`
- SuperAdmin hitting any export → `200`
- Student hitting `/api/export/students/:ownId/report-card` → `200`

---

## 1.2 — Fix Remarks Access Control (Flaw #12)

**File:** `server/routes/remarks.js`

**Problem:** `GET /api/remarks/student/:id` (L50–57) has NO access check.
Any authenticated user can read any student's private teacher remarks.

```javascript
// CURRENT (broken — no auth check):
router.get('/student/:id', auth, async (req, res) => {
  const remarks = await Remark.find({ studentId: req.params.id })...;
  res.json(remarks);  // ← anyone gets this!
});
```

**Fix:**
```javascript
router.get('/student/:id', auth, async (req, res) => {
  try {
    const { canUserAccessStudent } = require('../utils/accessScope');

    // Superadmin: unrestricted
    // Teacher: only if they can access that student's class
    // Student: only their own
    // Parent: only linked children
    // Others (accounts, canteen, driver, conductor): 403

    const allowed = ['superadmin', 'teacher', 'student', 'parent'];
    if (!allowed.includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const canAccess = await canUserAccessStudent(req.user, req.params.id);
    if (!canAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const remarks = await Remark.find({ studentId: req.params.id })
      .populate('teacherId', 'name')
      .sort('-createdAt');
    res.json(remarks);
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});
```

**Acceptance criteria:**
- Unrelated student → `403`
- Unrelated parent → `403`
- Accounts/canteen/driver roles → `403`
- Teacher of that student's class → `200`
- Student viewing own remarks → `200`
- Parent viewing linked child's remarks → `200`

---

## 1.3 — Remove Demo Credentials From Login Page (Flaw #13)

**File:** `client/src/pages/LoginPage.jsx`

**Problem:** The login page shows real demo credentials (email/password) in the UI — visible to anyone who opens the login page before authenticating.

**Fix:**
```jsx
// REMOVE any hardcoded credentials section like:
// <div>Demo: admin@school.com / admin123</div>

// If demo mode is needed for testing, gate it behind an env var:
{process.env.REACT_APP_SHOW_DEMO_HINTS === 'true' && (
  <div style={{ fontSize: '0.75rem', color: '#888', marginTop: '16px' }}>
    Demo mode — credentials shown for testing only
  </div>
)}
```

**client/.env (dev):**
```env
REACT_APP_SHOW_DEMO_HINTS=true
```

**client/.env.production:**
```env
REACT_APP_SHOW_DEMO_HINTS=false
```

**Acceptance criteria:**
- Production build: no credentials visible on login page
- Dev build: credentials shown only if `REACT_APP_SHOW_DEMO_HINTS=true`

---

## 1.4 — Fix Payroll PDF Field Mismatch (Flaw #17)

**Files:** `server/routes/pdf.js`, `server/models/Payroll.js`

**Problem:** The Payroll model stores `netPay` but the payslip PDF route renders `payroll.netSalary` → field is `undefined` → PDF shows "N/A".

**Fix in `server/routes/pdf.js`** — find all references to `netSalary` in the payslip template:
```javascript
// BEFORE (broken):
netSalary: payroll.netSalary

// AFTER (fixed):
netSalary: payroll.netPay ?? payroll.netSalary ?? 0
// The fallback chain handles any legacy records that stored it under old field name
```

**Also standardize in any UI references** (`PayrollPage.jsx`) — search for `netSalary` and replace with `netPay`:
```javascript
// Throughout PayrollPage.jsx:
payroll.netPay  // always use this
```

**Acceptance criteria:**
- Generated payslip PDF shows actual net pay amount, not "N/A"
- PayrollPage table shows correct net pay column

---

## ✅ Phase 1 Verification

```bash
# Run tests — exports should return 403 for wrong roles
cd server && npm test

# Manual spot checks:
# Login as student → try GET /api/export/students/pdf → must get 403
# Login as parent → try GET /api/remarks/student/<other_student_id> → must get 403
# Open /login in browser → verify no credentials are visible
# Generate a payslip PDF → verify net pay shows a number, not N/A
```

---

---

# PHASE 2 — Functional Correctness 🔧

**Goal:** Auth token secure. Canteen AI works. No orphan pages.
**Flaws closed:** #14, #16, #18
**Estimated time: 2 hours**

---

## 2.1 — Secure JWT Storage: localStorage → Memory + httpOnly Cookie (Flaw #14)

**Files:** `client/src/contexts/AuthContext.jsx`, `client/src/api/api.js`, `server/routes/auth.js`, `server/server.js`

**Problem:** JWT stored in `localStorage` is vulnerable to XSS attacks.
Any injected JavaScript can read `localStorage.getItem('token')` and steal the session.

**Current code in `AuthContext.jsx` (broken):**
```javascript
localStorage.setItem('token', tokenData);   // ← XSS-readable
localStorage.setItem('user', JSON.stringify(userData));
```

**Fix Strategy — In-Memory Token + httpOnly Cookie:**

### Backend change (`server/routes/auth.js` — login endpoint):
```javascript
// After verifying credentials and creating JWT:
const token = jwt.sign({ id: user._id, role: user.role }, process.env.JWT_SECRET, {
  expiresIn: process.env.JWT_EXPIRES_IN || '7d'
});

// Set token as httpOnly cookie (not accessible by JS)
res.cookie('token', token, {
  httpOnly: true,
  secure:   process.env.NODE_ENV === 'production', // HTTPS only in prod
  sameSite: 'strict',
  maxAge:   7 * 24 * 60 * 60 * 1000, // 7 days in ms
});

// Do NOT send token in response body anymore
res.json({ user: { id: user._id, name: user.name, role: user.role, email: user.email } });
```

### Backend change — auth middleware reads cookie too:
```javascript
// server/middleware/auth.js — add cookie fallback:
const token = req.cookies?.token || req.header('Authorization')?.replace('Bearer ', '');
```

### Backend change — enable cookie parsing (`server/server.js`):
```bash
npm install cookie-parser
```
```javascript
const cookieParser = require('cookie-parser');
app.use(cookieParser());
```

### Backend change — logout endpoint (`server/routes/auth.js`):
```javascript
router.post('/logout', auth, (req, res) => {
  res.clearCookie('token', { httpOnly: true, secure: true, sameSite: 'strict' });
  res.json({ msg: 'Logged out' });
});
```

### Frontend change (`AuthContext.jsx`):
```javascript
// REMOVE all localStorage reads/writes for token:
// localStorage.setItem('token', tokenData)  ← DELETE
// localStorage.getItem('token')             ← DELETE

// KEEP user info in localStorage (not sensitive — no credentials)
const login = (userData) => {       // token no longer passed as arg
  setUser(userData);
  localStorage.setItem('user',      JSON.stringify(userData));
  localStorage.setItem('loginTime', Date.now().toString());
};

const handleLogout = async () => {
  await axios.post('/api/auth/logout', {}, { withCredentials: true });
  setUser(null);
  localStorage.removeItem('user');
  localStorage.removeItem('loginTime');
  window.location.href = '/login';
};
```

### Frontend change (`api.js`):
```javascript
// Add withCredentials: true to axios instance so cookies are sent automatically
const axiosInstance = axios.create({
  baseURL: process.env.REACT_APP_API_URL || '/api',
  withCredentials: true,    // ← This sends the httpOnly cookie with every request
});

// REMOVE the Authorization header interceptor that reads localStorage:
// axiosInstance.interceptors.request.use(config => {
//   const token = localStorage.getItem('token');    ← DELETE THIS
//   config.headers.Authorization = `Bearer ${token}`;
//   return config;
// });
```

**Acceptance criteria:**
- After login, token not visible in DevTools → Application → LocalStorage
- Token exists only in DevTools → Application → Cookies as httpOnly
- All API calls still work (cookie sent automatically)
- Logout clears cookie and redirects to login

---

## 2.2 — Fix Canteen AI Query (Flaw #16)

**File:** `server/ai/actions.js`

**Problem:** AI chatbot's "available menu" action queries `{ available: true }` but the Canteen model field is named `isAvailable`.

```javascript
// BEFORE (broken — returns empty array):
const items = await CanteenItem.find({ available: true });

// AFTER (fixed — matches actual schema field):
const items = await CanteenItem.find({
  isAvailable: true,
  quantityAvailable: { $gt: 0 }  // also filter out out-of-stock items
});
```

**Acceptance criteria:**
- Chatbot query "what's available in canteen?" returns actual in-stock items
- Out-of-stock items not shown as available

---

## 2.3 — Resolve Orphan Pages (Flaw #18)

**Files:** `client/src/pages/ParentDashboard.jsx`, `client/src/pages/ConductorPanel.jsx`, `client/src/App.jsx`

**Problem:** These two pages exist as component files but have no routes in `App.jsx` — dead code.

**Decision:** Inspect each page:

**Option A (Recommended) — Merge into existing pages:**
- `ParentDashboard.jsx` → its functionality already exists in `Dashboard.jsx` with `role === 'parent'` branching. **Delete the file.**
- `ConductorPanel.jsx` → its functionality exists in `TransportPage.jsx` with conductor role checks. **Delete the file.**

**Option B — Add routes if unique content exists:**
```jsx
// In App.jsx, inside <Routes>:
<Route path="/parent-dashboard"
  element={<ProtectedRoute allowedRoles={['parent']}><ParentDashboard /></ProtectedRoute>}
/>
<Route path="/conductor-panel"
  element={<ProtectedRoute allowedRoles={['conductor']}><ConductorPanel /></ProtectedRoute>}
/>
```

> Inspect both files first. If they're just copies of existing pages → delete. If they have unique UI → add routes.

**Acceptance criteria:**
- No React component files exist without a corresponding route
- Navigation for parent and conductor roles is consistent and intentional

---

## ✅ Phase 2 Verification

```bash
cd client && npm run build  # Must pass — no broken imports from deleted files
# Manual: Login → open DevTools → check LocalStorage has NO 'token' key
# Manual: Check Cookies tab → 'token' cookie is present, httpOnly flag set
# Manual: Chatbot → ask "show canteen menu" → gets real items back
```

---

---

# PHASE 3 — Performance & Scalability 🚀

**Goal:** All list endpoints paginated. Huge payloads eliminated. N+1 queries gone. Gzip on. Startup fast.
**Flaws closed:** #4, #5, #6, #7, #8 + gzip + AI startup + code splitting
**Estimated time: 3 hours**

---

## 3.1 — Shared Pagination Policy (Flaws #7, #8)

The `server/utils/pagination.js` utility already exists. All list endpoints must use it.

**Standard query params across ALL list endpoints:**
| Param | Default | Max | Description |
|-------|---------|-----|-------------|
| `page` | 1 | — | Page number |
| `limit` | 25 | 100 | Items per page |
| `sort` | `-createdAt` | — | Sort field |
| `search` | — | — | Text search |

**Standard response shape:**
```json
{
  "items": [...],
  "pagination": {
    "total": 450,
    "page": 1,
    "limit": 25,
    "totalPages": 18,
    "hasNext": true,
    "hasPrev": false
  }
}
```

**Apply to:** `student.js`, `library.js`, `complaints.js`, `notices.js`, `notifications.js`, `attendance.js`

---

## 3.2 — Fix Student List Pagination (Flaw #4)

**File:** `server/routes/student.js` — L319–327

```javascript
// BEFORE: Student.find(query) — no limit, returns 7.9 MB
// AFTER:
const { parsePaginationParams } = require('../utils/pagination');
const { page, limit } = parsePaginationParams(req.query);
const skip = (page - 1) * limit;

const [students, total] = await Promise.all([
  Student.find(query)
    .populate('userId',       'name email phone')
    .populate('classId',      'name section')
    .populate('parentUserId', 'name email phone')
    .sort(req.user.role === 'student' ? { updatedAt: -1 } : { name: 1 })
    .skip(skip).limit(limit).lean(),
  Student.countDocuments(query),
]);

res.json({
  students: req.user.role === 'student' ? students.slice(0, 1) : students,
  pagination: { total, page, limit, pages: Math.ceil(total / limit) },
});
```

> Result: **7.9 MB → < 200 KB** per page

---

## 3.3 — Fix Library Dashboard (Flaw #5)

**File:** `server/routes/library.js` — L19–47

```javascript
// AFTER — summary counts + last 20 transactions + capped 200 students:
router.get('/dashboard', auth, async (req, res) => {
  try {
    const [totalBooks, copiesAgg, borrowed, overdue] = await Promise.all([
      LibraryBook.countDocuments(),
      LibraryBook.aggregate([{ $group: { _id: null, total: { $sum: '$totalCopies' }, avail: { $sum: '$availableCopies' } } }]),
      LibraryTransaction.countDocuments({ status: 'BORROWED' }),
      LibraryTransaction.countDocuments({ status: 'BORROWED', dueDate: { $lt: new Date() } }),
    ]);

    let txQuery = {};
    if (['student', 'parent'].includes(req.user.role)) {
      const { getStudentRecordsForUser } = require('../utils/accessScope');
      const linked = await getStudentRecordsForUser(req.user);
      txQuery = { studentId: { $in: linked.map(s => s._id) } };
    }

    const recentTransactions = await LibraryTransaction.find(txQuery)
      .populate('studentId', 'name admissionNo')
      .populate('bookId', 'title author isbn')
      .sort({ createdAt: -1 }).limit(20).lean();

    let students = [];
    if (['superadmin', 'teacher', 'staff', 'hr'].includes(req.user.role)) {
      students = await Student.find().select('name admissionNo classId')
        .populate('classId', 'name section').sort({ name: 1 }).limit(200).lean();
    }

    res.json({
      stats: { totalBooks, totalCopies: copiesAgg[0]?.total ?? 0, availableCopies: copiesAgg[0]?.avail ?? 0, borrowed, overdue },
      recentTransactions,
      students,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error.' });
  }
});
```

> Result: **10.3 MB → ~150 KB**

---

## 3.4 — Fix Monthly Attendance N+1 (Flaw #6)

**File:** `server/routes/attendance.js` — L411–443

```javascript
// REPLACES the Promise.all(students.map(...countDocuments)) loop:
const aggregated = await Attendance.aggregate([
  { $match: query },
  { $group: {
    _id: '$studentId',
    total:   { $sum: 1 },
    present: { $sum: { $cond: [{ $eq: ['$status', 'present'] }, 1, 0] } },
  }},
]);
const attMap = new Map(aggregated.map(r => [String(r._id), r]));

const report = students.map(student => {
  const att = attMap.get(String(student._id)) || { total: 0, present: 0 };
  return {
    student: { _id: student._id, name: student.name, admissionNo: student.admissionNo, class: student.classId },
    attendance: {
      total: att.total, present: att.present,
      absent: att.total - att.present,
      percentage: att.total > 0 ? Math.round((att.present / att.total) * 100) : 0,
    },
  };
});
res.json({ month: currentMonth, year: currentYear, report });
```

**Apply same aggregation fix to `/defaulters` endpoint (L470–501).**

> Result: **5.2s → < 300ms**

---

## 3.5 — Add Gzip Compression

```bash
cd server && npm install compression
```

```javascript
// server/server.js — FIRST middleware in createApp():
const compression = require('compression');
app.use(compression());
```

> All responses shrink 60–80%

---

## 3.6 — Make AI Startup Non-Blocking

**File:** `server/server.js` — `initializeRuntime()`:

```javascript
// CHANGE: only DB blocks startup; AI trains in background after server is up
await connectDB();  // keeps blocking — needed before any request

setImmediate(() => {
  trainChatbot()
    .then(() => { initializeKnowledgeBase(); logger.info('[AI] ✅ Ready.'); })
    .catch(err => logger.error('[AI] Failed:', err.message));
});
```

> Server ready in **~2s** instead of ~30s

---

## 3.7 — React Code Splitting

**File:** `client/src/App.jsx`

```jsx
import React, { Suspense } from 'react';

// Change ALL 27 static page imports to lazy:
const Dashboard    = React.lazy(() => import('./pages/Dashboard'));
const StudentsPage = React.lazy(() => import('./pages/StudentsPage'));
const FeePage      = React.lazy(() => import('./pages/FeePage'));
// ... same for all 27 pages

// Wrap Routes:
<Suspense fallback={<div style={{display:'flex',alignItems:'center',justifyContent:'center',height:'100vh'}}>Loading...</div>}>
  <Routes>
    {/* unchanged */}
  </Routes>
</Suspense>
```

> Initial JS bundle: **~2 MB → ~200 KB**

---

## 3.8 — Wire Frontend Pagination

**StudentsPage.jsx:**
```jsx
const [pagination, setPagination] = useState({ page: 1, pages: 1, total: 0 });
// In fetch: read res.data.students + res.data.pagination
// Add Prev/Next buttons using pagination state
```

**LibraryPage.jsx:**
```jsx
// Read new response shape: res.data.stats, res.data.recentTransactions, res.data.students
```

---

## ✅ Phase 3 Verification

```bash
cd server && npm start
# Check: startup logs show "listening" in < 3s, AI log comes after
curl -H "Authorization: Bearer <token>" "http://localhost:5000/api/students?page=1&limit=25"
# → Response size < 200 KB
curl -H "Authorization: Bearer <token>" "http://localhost:5000/api/library/dashboard"
# → Response size < 200 KB
curl -H "Authorization: Bearer <token>" "http://localhost:5000/api/attendance/report/monthly?..."
# → Response in < 500ms
cd client && npm run build
# → build/static/js/ main chunk < 300 KB (was ~2 MB)
```

---

---

# PHASE 4 — Infrastructure & Testing 🧪

**Goal:** Tests on isolated DB. Zero deprecation warnings. All queries index-backed.
**Flaws closed:** #9, #10
**Estimated time: 1 hour**

---

## 4.1 — Test Database Isolation (Flaw #9)

**`server/.env`** — add:
```env
MONGODB_URI_TEST=mongodb://127.0.0.1:27017/school_erp_test
```

**Install cross-env (Windows-compatible):**
```bash
cd server && npm install --save-dev cross-env
```

**`server/package.json`:**
```json
"test": "cross-env MONGODB_URI=mongodb://127.0.0.1:27017/school_erp_test node --test tests/api.integration.test.js"
```

> Tests never touch the dev database again

---

## 4.2 — Fix Mongoose Deprecation Warnings (Flaw #10)

Find and replace in all `server/routes/*.js`:

```
Find:    { new: true }
Replace: { returnDocument: 'after' }

Find:    { new: true, upsert: true }
Replace: { returnDocument: 'after', upsert: true }
```

**Confirmed affected locations:**
- `transport.js` L89, L144
- `student.js` L389, L526
- `attendance.js` L193

---

## 4.3 — MongoDB Indexes

**File:** `server/scripts/add-indexes.js` (already exists — verify these are included)

```javascript
// Students
{ classId: 1, name: 1 }
{ parentUserId: 1 }
{ name: 'text', admissionNo: 'text' }

// Attendance
{ studentId: 1, date: 1 }
{ classId: 1, date: 1 }
{ studentId: 1, status: 1 }

// LibraryTransactions
{ status: 1, createdAt: -1 }
{ studentId: 1, status: 1 }
{ dueDate: 1, status: 1 }

// FeePayments
{ studentId: 1, paymentDate: -1 }
{ receiptNo: 1 }  // unique, sparse

// ExamResults
{ examId: 1, studentId: 1 }  // unique

// Notifications
{ userId: 1, createdAt: -1 }
{ userId: 1, isRead: 1 }
```

**Run once:**
```bash
cd server && node scripts/add-indexes.js
```

---

## ✅ Phase 4 Verification

```bash
cd server && npm test
# → 7/7 pass, ZERO deprecation warnings, uses school_erp_test DB
mongosh school_erp --eval "db.students.getIndexes()"
# → Shows classId+name, parentUserId, text indexes
```

---

---

# PHASE 5 — Production Config 🔧

**Goal:** App fully configured for production. PM2 ready. Client build with correct URLs.
**Estimated time: 45 minutes**

---

## 5.1 — Server Production `.env`

Create this on the Hostinger VPS (not committed to git):

```env
PORT=5000
NODE_ENV=production
MONGODB_URI=mongodb+srv://USER:PASS@CLUSTER.mongodb.net/school_erp?retryWrites=true&w=majority
JWT_SECRET=<64-char hex: node -e "console.log(require('crypto').randomBytes(48).toString('hex'))">
JWT_EXPIRES_IN=7d
FRONTEND_URL=https://yourdomain.com
SCHOOL_NAME=Your School Name
ENABLE_AUTO_BACKUPS=true
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_PHONE_NUMBER=
```

---

## 5.2 — Client `.env.production`

```env
# Nginx proxies /api → Node on same domain — use relative URL
REACT_APP_API_URL=/api
REACT_APP_SCHOOL_NAME=Your School Name
REACT_APP_SHOW_DEMO_HINTS=false
```

---

## 5.3 — Build React App

```bash
cd client && npm run build
# → client/build/ (what Nginx serves)
```

---

## 5.4 — Express Serves React Build (Fallback)

**`server/server.js`** — add after all API routes:

```javascript
if (process.env.NODE_ENV === 'production') {
  const buildPath = path.join(__dirname, '..', 'client', 'build');
  app.use(express.static(buildPath));
  app.get('*', (req, res) => res.sendFile(path.join(buildPath, 'index.html')));
}
```

---

## 5.5 — PM2 Config

**New file:** `server/ecosystem.config.js`

```javascript
module.exports = {
  apps: [{
    name:               'school-erp',
    script:             'server.js',
    cwd:                '/var/www/school-erp/server',
    instances:          1,
    exec_mode:          'fork',
    watch:              false,
    max_memory_restart: '512M',
    env_production: {
      NODE_ENV: 'production',
      PORT:     5000,
    },
    error_file:      './logs/pm2-error.log',
    out_file:        './logs/pm2-out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    merge_logs:      true,
  }]
};
```

---

---

# PHASE 6 — Hostinger VPS Deployment 🌐

**Goal:** App live at `https://yourdomain.com` with HTTPS, Nginx, PM2.
**Estimated time: 2–3 hours**

---

## Prerequisites

- [ ] Hostinger **KVM VPS** (Ubuntu 22.04 LTS)
- [ ] SSH access: `ssh root@YOUR_VPS_IP`
- [ ] MongoDB Atlas cluster with connection string
- [ ] Domain A record pointing to VPS IP
- [ ] All Phase 1–5 committed and pushed to GitHub

---

## 6.1 — Nginx Config

**New file:** `nginx.conf` (project root)

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript;
    gzip_min_length 1024;

    root /var/www/school-erp/client/build;
    index index.html;

    location /api/ {
        proxy_pass         http://127.0.0.1:5000;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade    $http_upgrade;
        proxy_set_header   Connection 'upgrade';
        proxy_set_header   Host       $host;
        proxy_set_header   X-Real-IP  $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }

    location /uploads/ {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Authorization $http_authorization;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## 6.2 — VPS Setup Commands

```bash
# 1. SSH in
ssh root@YOUR_VPS_IP

# 2. System setup
apt update && apt upgrade -y
apt install -y nginx git curl build-essential

# 3. Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
node -v   # v20.x.x

# 4. PM2
npm install -g pm2

# 5. Clone repo
mkdir -p /var/www && cd /var/www
git clone https://github.com/YOUR_USERNAME/school-erp.git
cd school-erp

# 6. Backend dependencies + env
cd server
npm install --production
nano .env   # paste Phase 5.1 env values

# 7. Build frontend
cd ../client
npm install && npm run build

# 8. Create indexes (one-time)
cd ../server
node scripts/add-indexes.js

# 9. Start with PM2
pm2 start ecosystem.config.js --env production
pm2 save
pm2 startup   # RUN THE PRINTED COMMAND

# 10. Nginx
cp /var/www/school-erp/nginx.conf /etc/nginx/sites-available/school-erp
ln -s /etc/nginx/sites-available/school-erp /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx && systemctl enable nginx

# 11. SSL (free HTTPS)
apt install -y certbot python3-certbot-nginx
certbot --nginx -d yourdomain.com -d www.yourdomain.com
certbot renew --dry-run   # verify auto-renewal works

# 12. Final check
curl https://yourdomain.com/api/health
# → { "status": "ok", "database": "connected" }
```

---

## 6.3 — Deploying Future Updates

```bash
ssh root@YOUR_VPS_IP
cd /var/www/school-erp && git pull origin main

# Backend changed:
cd server && npm install --production && pm2 restart school-erp

# Frontend changed:
cd ../client && npm install && npm run build
# (Nginx picks up new files automatically)
```

---

---

# FINAL SUMMARY & TARGETS

## Phase Overview

| Phase | Flaws Closed | Scope | Time |
|-------|-------------|-------|------|
| **1 — Security** | #11, #12, #13, #17 | Export auth, remarks RBAC, remove credentials, fix payslip | 2h |
| **2 — Correctness** | #14, #16, #18 | JWT → cookie, canteen AI fix, orphan pages | 2h |
| **3 — Performance** | #4, #5, #6, #7, #8 + gzip + AI + split | Pagination, N+1 aggregation, compression, lazy routes | 3h |
| **4 — Infrastructure** | #9, #10 + indexes | Test DB isolation, Mongoose warnings, DB indexes | 1h |
| **5 — Prod Config** | — | PM2, env files, client build, static serve | 45m |
| **6 — Hostinger** | — | VPS, Nginx, SSL, PM2 autostart | 2–3h |
| **TOTAL** | **15 flaws** | | **~11 hours** |

## Performance Before → After

| Metric | Current | After |
|--------|---------|-------|
| `/api/students` response | 7.9 MB | < 200 KB |
| `/api/library/dashboard` | 10.3 MB | < 200 KB |
| `/api/attendance/report/monthly` | 5.2s | < 300ms |
| Server startup time | ~30s | < 3s |
| React initial bundle | ~2 MB | ~200 KB |
| Export routes authorization | ❌ None | ✅ Role-checked |
| Token storage | ❌ localStorage | ✅ httpOnly cookie |
| Demo credentials on login | ❌ Visible | ✅ Hidden in prod |
| Payslip net pay | ❌ N/A | ✅ Correct value |
| HTTPS | ❌ None | ✅ Let's Encrypt |
| Crash recovery | ❌ Manual | ✅ PM2 auto-restart |

## Complete File Change List

| File | Phase | Change |
|------|-------|--------|
| `server/routes/export.js` | 1 | Add roleCheck to all 12 export endpoints |
| `server/routes/remarks.js` | 1 | Add canUserAccessStudent check at L50 |
| `client/src/pages/LoginPage.jsx` | 1 | Remove demo credentials, add env flag gate |
| `server/routes/pdf.js` | 1 | Fix `netSalary` → `netPay` in payslip |
| `server/routes/auth.js` | 2 | Set httpOnly cookie on login, add logout endpoint |
| `server/middleware/auth.js` | 2 | Read token from cookie as fallback |
| `server/server.js` | 2,3 | cookie-parser, compression, non-blocking AI, static serve |
| `client/src/contexts/AuthContext.jsx` | 2 | Remove localStorage token R/W |
| `client/src/api/api.js` | 2 | Add `withCredentials: true`, remove localStorage interceptor |
| `server/ai/actions.js` | 2 | Fix `available` → `isAvailable` |
| `client/src/App.jsx` | 2,3 | Delete or route orphan pages, add React.lazy for all 27 pages |
| `server/routes/student.js` | 3,4 | Add pagination, fix `{ returnDocument: 'after' }` |
| `server/routes/library.js` | 3 | Rewrite dashboard summary + add limits to /books /transactions |
| `server/routes/attendance.js` | 3,4 | Replace N+1 with aggregation (x2), fix returnDocument |
| `server/routes/transport.js` | 4 | Fix `{ returnDocument: 'after' }` |
| `server/.env` | 4 | Add `MONGODB_URI_TEST` |
| `server/package.json` | 4 | Update test script with cross-env |
| `server/scripts/add-indexes.js` | 4 | Verify/add all 15 critical indexes |
| `server/ecosystem.config.js` | 5 | NEW — PM2 config |
| `client/.env.production` | 5 | NEW — `REACT_APP_API_URL=/api`, hide demo hints |
| `nginx.conf` | 6 | NEW — Nginx reverse proxy + gzip + SSL-ready |
| `client/src/pages/StudentsPage.jsx` | 3 | Read paginated response shape |
| `client/src/pages/LibraryPage.jsx` | 3 | Read new dashboard response shape |

---

*Last updated: 2026-04-06*
*Flaws tracked: 18 total | 3 already fixed | 14 open | 1 rejected*
*Status: Ready to Execute — Start with Phase 1*
