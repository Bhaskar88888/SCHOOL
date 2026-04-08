# 🔍 COMPREHENSIVE REMAINING BUGS AUDIT - Round 2
**Date:** 7 April 2026
**After:** 10 critical fixes applied
**Total Remaining Bugs:** 83

---

## 📊 SEVERITY BREAKDOWN

| Severity | Count | Impact |
|----------|-------|--------|
| 🔴 Critical | 12 | System crashes, data corruption, security breaches |
| 🟠 High | 28 | Features broken, security vulnerabilities |
| 🟡 Medium | 35 | Degraded UX, edge case failures |
| 🟢 Low | 8 | Code quality, minor issues |

---

## 🔴 CRITICAL BUGS (12)

### C1: Mixed ORM — Mongoose vs Prisma (12 files)
**Files:** `canteen.js`, `complaints.js`, `payroll.js`, `routine.js`, `salarySetup.js`, `leave.js`, `export.js`, `hostel.js`, `transport.js`, `library.js`, `tally.js`, `dashboard.js`

**Problem:** Some routes use Mongoose models, others use Prisma. Creates data inconsistency, `_id` vs `id` mismatches, and query failures.

**Impact:** Dashboard may crash, library access broken, payroll null pointer errors, data integrity compromised.

**Fix:** Standardize on ONE ORM (Mongoose or Prisma). If keeping Mongoose, remove all Prisma imports. If migrating to Prisma, update all 12 files.

---

### C2: `_id` vs `id` Property Access on Prisma Results
**Files:** 
- `library.js` (lines 61-62)
- `notices.js` (lines 77-78)
- `transport.js` (line 56)

**Problem:** Prisma returns `id` but code accesses `_id` → `undefined`

**Impact:** Student/parent access to library and notices completely broken — always returns empty data.

**Fix:** Change `item._id` to `item.id` OR use `withLegacyId()` utility.

---

### C3: Race Condition in RFID Payments
**File:** `canteen.js` (lines 146-183)

**Problem:** Balance check and deduction are NOT atomic. Two concurrent requests can both pass balance check and overdraw wallet.

**Fix:**
```javascript
const student = await Student.findOneAndUpdate(
  { rfidTagHex: rfidTagHex.toLowerCase(), 'canteenWallet.balance': { $gte: totalAmount } },
  { $inc: { 'canteenWallet.balance': -totalAmount } },
  { new: true }
);
if (!student) return res.status(400).json({ msg: 'Insufficient balance' });
```

---

### C4: Race Condition in Room Allocation
**File:** `hostel.js` (lines 183-188)

**Problem:** Capacity check and bed increment not atomic → room can be overfilled.

**Fix:**
```javascript
const room = await HostelRoom.findOneAndUpdate(
  { _id: roomId, occupiedBeds: { $lt: capacity } },
  { $inc: { occupiedBeds: 1 } },
  { new: true }
);
if (!room) return res.status(400).json({ msg: 'Room is full' });
```

---

### C5: Non-Atomic Stock Deduction in Sales
**File:** `canteen.js` (lines 39-49)

**Problem:** Stock deduction loop not in transaction → if sale saves but stock fails, inventory inconsistent.

**Fix:** Wrap entire sale creation + stock deduction in MongoDB session/transaction.

---

### C6: CSV Injection Vulnerability
**File:** `utils/export.js` (lines 207-216)

**Problem:** Cell values starting with `=`, `+`, `-`, `@` not sanitized → malicious Excel formulas execute when opened.

**Fix:**
```javascript
function sanitizeCellValue(value) {
  const str = String(value);
  if (/^[=+\-@]/.test(str)) {
    return "'" + str; // Prefix with single quote to prevent formula execution
  }
  return str;
}
```

---

### C7: Sensitive Data in Audit Logs
**File:** `audit.js` (lines 36-37)

**Problem:** `req.body` captured as-is → passwords, tokens stored in plaintext in audit trail.

**Fix:** Sanitize sensitive fields:
```javascript
const sensitiveFields = ['password', 'token', 'confirmPassword', 'oldPassword', 'newPassword'];
const sanitizedBody = { ...req.body };
sensitiveFields.forEach(field => {
  if (sanitizedBody[field]) sanitizedBody[field] = '***REDACTED***';
});
```

---

### C8: Race Condition in ID Generation
**Files:** `autoIdGenerator.js`, `generateId.js`, `idGenerator.js`

**Problem:** Under high concurrency, `findOneAndUpdate` with upsert can generate duplicate IDs.

**Fix:**
1. Add unique indexes to all ID fields in Mongoose schemas
2. Implement retry logic on collision:
```javascript
async function generateStudentIdWithRetry(retries = 3) {
  for (let i = 0; i < retries; i++) {
    const id = await generateStudentId();
    const exists = await Student.findOne({ studentId: id });
    if (!exists) return id;
  }
  throw new Error('Failed to generate unique student ID');
}
```

---

### C9: jsPDF Import Incorrect
**File:** `pdf.js` (lines 8-11)

**Problem:** `require('jspdf').jsPDF` may be `undefined` → PDF generation always fails.

**Fix:**
```javascript
const jsPDF = require('jspdf'); // Default export IS the jsPDF class
```

---

### C10: Broken Import in rateLimiter.js
**File:** `rateLimiter.js` (line 1)

**Problem:** `ipKeyGenerator` not exported in express-rate-limit v7+ → runtime crash.

**Fix:**
```javascript
const { rateLimit } = require('express-rate-limit'); // Remove ipKeyGenerator
```

---

### C11: Student Import Creates Orphan Users
**File:** `student.js` (lines 492-553)

**Problem:** User created first, then Student. If Student creation fails, orphan User left in database.

**Fix:** Wrap in MongoDB transaction:
```javascript
const session = await mongoose.startSession();
session.startTransaction();
try {
  await User.create([userData], { session });
  await Student.create([studentData], { session });
  await session.commitTransaction();
} catch (err) {
  await session.abortTransaction();
  throw err;
} finally {
  session.endSession();
}
```

---

### C12: Complaint Type Not in Enum
**File:** `complaints.js` (line 72)

**Problem:** Sets `type = 'conductor_to_admin'` but enum only has `conductor_to_parent`, `teacher_to_admin` → MongoDB validation error.

**Fix:** Add `conductor_to_admin` to Complaint model's `type` enum.

---

## 🟠 HIGH SEVERITY BUGS (28)

### Security (10 bugs)

| # | File | Issue | Impact |
|---|------|-------|--------|
| H1 | `canteen.js` | IDOR in wallet access — any user can query any student's wallet | Privacy breach |
| H2 | `exams.js` | Any teacher can update/delete ANY exam result | Data tampering |
| H3 | `homework.js` | Any teacher can edit/delete ANY homework | Data tampering |
| H4 | `attendance.js` | Teachers can update records from other classes | Data tampering |
| H5 | `pdf.js` | Anyone can generate payslips/certificates for any student/staff | Privacy breach |
| H6 | `auth.js` | Password reset token leaked in response if misconfigured | Account takeover |
| H7 | `import.js` | Plain-text passwords in import response | Credential exposure |
| H8 | `tally.js` | XML injection — user data not escaped | Malformed XML/XSS |
| H9 | `uploadAccess.js` | URL-encoded paths may bypass path traversal checks | File access breach |
| H10 | `auth.js` | No rate limiting on login endpoint | Brute-force attack |

### Data Integrity (8 bugs)

| # | File | Issue | Impact |
|---|------|-------|--------|
| H11 | `student.js` | No transaction for bulk import | Orphan user records |
| H12 | `fee.js` | Payment hard-deleted without audit trail | Financial data loss |
| H13 | `class.js` | Class deletion doesn't cascade | Orphan homework/exams/attendance |
| H14 | `class.js` | Subject replacement deletes ALL subjects | Data loss on edit |
| H15 | `leave.js` | No leave balance check | Unlimited leave requests |
| H16 | `leave.js` | No date validation | Past dates, inverted ranges |
| H17 | `complaints.js` | Notification creation lacks error handling | Failed complaints |
| H18 | Multiple | `_id` mapped from `id` incorrectly | Access scope failures |

### Broken Features (10 bugs)

| # | File | Issue | Impact |
|---|------|-------|--------|
| H19 | `dashboard.js` | Queries non-existent Prisma `complaint` model | Dashboard crash |
| H20 | `payroll.js` | `payroll.staffId._id` throws if null | Payslip generation crash |
| H21 | `notices.js` | Fetches ALL notices into memory | Memory exhaustion |
| H22 | `payroll.js` | Serial processing in batch generation | Timeout for large staff |
| H23 | `actions.js` | Hardcoded Rs 5000 fee estimation | Misleading financial reports |
| H24 | `canteen.js` | `soldTo` field type mismatch (ObjectId → String) | Data inconsistency |
| H25 | `leave.js` | Status not validated on approve | Invalid status values stored |
| H26 | `staffAttendance.js` | Route conflicts `GET /` vs `GET /:date` | 400 errors |
| H27 | `exams.js` | No validation of update fields | Arbitrary field injection |
| H28 | `import.js` | No cleanup on processing failure | Orphan uploaded files |

---

## 🟡 MEDIUM SEVERITY BUGS (35)

### Frontend (10 bugs)

| # | File | Issue | Impact |
|---|------|-------|--------|
| M1 | `ImportDataPage.jsx` | `getHeaders().headers` is undefined — template download sends no auth | Download fails |
| M2 | `NoticesPage.jsx` | Students API returns paginated object, not array — `.map()` crashes | Page crash |
| M3 | `RemarksPage.jsx` | Same as M2 — response shape mismatch | Page crash |
| M4 | `StudentsPage.jsx` | Edit mode resets many fields to defaults | Data loss on edit |
| M5 | `BusRoutesPage.jsx` | Form missing inputs for feePerStudent, totalDistance, description, vehicleId | Incomplete data |
| M6 | `CanteenPage.jsx` | No loading state on checkout | Duplicate submissions |
| M7 | `UsersPage.jsx` | Missing conductor and driver roles in user form | Can't create these users |
| M8 | `ImportDataPage.jsx` | File re-uploaded on import | Inefficient |
| M9 | Multiple pages | `alert()` used instead of toast | Inconsistent UX |
| M10 | `ProtectedRoute.jsx` | May redirect to login prematurely during auth restoration | False logout |

### Frontend Code Quality (1 bug)

| # | File | Issue | Impact |
|---|------|-------|--------|
| M11 | 11+ pages | Unused imports: `useMemo, useCallback, useRef, useContext` | Bundle bloat, lint warnings |

### Backend (24 bugs)

| # | File | Issue | Impact |
|---|------|-------|--------|
| M12 | Multiple routes | No input validation/sanitization | Data corruption |
| M13 | Export, archive, notices | Unbounded queries — fetch ALL records | Memory exhaustion |
| M14 | `chatbot.js` | Fire-and-forget logging | Unhandled promise rejections |
| M15 | `notificationService.js` | No phone validation before SMS | Twilio errors |
| M16 | `db.js` | `connectPromise` never reset on success | Stale promise on reconnect |
| M17 | `logger.js` | Security events logged at WARN level | Missing from error.log |
| M18 | `requestLogger.js` | No request body logging | Hard to debug POST/PUT |
| M19 | `audit.js` | `res.send` not overridden, only `res.json` | Missed audit entries |
| M20 | `audit.js` | `recordId` extraction unreliable | Wrong record IDs in audit |
| M21 | `autoIdGenerator.js` | Generates ID even when role undefined | Wrong IDs |
| M22 | `requestLogger.js` | Double logging errors | Duplicate log entries |
| M23 | `security.js` | Modulo bias in password generation | Reduced entropy |
| M24 | `nlpEngine.js` | Entity accumulation memory leak | Growing memory usage |
| M25 | `nlpEngine.js` | `setInterval` never cleared | Memory leak on hot reload |
| M26 | `scanner.js` | Knowledge base re-indexing on multiple inits | Duplicate entries |
| M27 | `scanner.js` | Synchronous file loading | Blocks event loop |
| M28 | `scanner.js` | `searchKnowledgeBase` returns only one result | Misses relevant info |
| M29 | `actions.js` | `escapeRegex` handles null poorly | Search failures |
| M30 | `prismaCompat.js` | `withLegacyId` doesn't handle circular references | Stack overflow |
| M31 | `prismaCompat.js` | `canteenWallet` overwrites existing data | Data loss |
| M32 | `security.js` | `generateReceiptNumber` can duplicate | Receipt collisions |
| M33 | `nlpEngine.js` | `handleFollowUp` no rate limiting | Abuse potential |
| M34 | `scanner.js` | `getAllFiles` doesn't skip build dirs | Slow scanning |
| M35 | Various | Fire-and-forget async operations | Silent failures |

---

## 🟢 LOW SEVERITY BUGS (8)

| # | File | Issue | Impact |
|---|------|-------|--------|
| L1 | `auth.js` | Inconsistent response format — uses `res.send()` instead of JSON | Client error handling |
| L2 | `library.js` | Duplicate routes `GET /` and `GET /books` | Confusing API |
| L3 | `library.js` | External API without fallback | Reliability |
| L4 | `RemarksPage.jsx` | Colspan mismatch in empty state | Minor visual glitch |
| L5 | `UsersPage.jsx` | `printUser.name.charAt(0)` blank for empty names | Blank avatar |
| L6 | `StudentsPage.jsx` | `formData` missing fields in edit mode | Incomplete edits |
| L7 | `StudentsPage.jsx` | DOM manipulation instead of refs | Anti-pattern |
| L8 | `idGenerator.js` | Inconsistent ID formats (student `/`, staff `-`) | Parsing issues |

---

## 🎯 PRIORITY FIX ORDER

### Phase 1: Critical Security & Data Integrity (Fix NOW)
1. **C3** — RFID payment race condition (wallet overdrawing)
2. **C6** — CSV injection (malicious formulas)
3. **C7** — Sensitive data in audit logs (password exposure)
4. **H1-H10** — IDOR vulnerabilities and security holes
5. **C9** — jsPDF import (all PDF generation broken)
6. **C10** — rateLimiter broken import (server crash)

### Phase 2: Critical Stability (Fix THIS WEEK)
7. **C1** — Standardize ORM (pick Mongoose OR Prisma)
8. **C2** — Fix `_id` vs `id` property access
9. **C4, C5** — Race conditions in allocations and sales
10. **C8** — ID generation race condition
11. **C11** — Transaction for student import
12. **C12** — Complaint type enum

### Phase 3: High Priority (Fix THIS SPRINT)
13. **H11-H18** — Data integrity issues
14. **H19-H28** — Broken features
15. **M1-M3** — Frontend crashes
16. **M4-M8** — Frontend UX issues

### Phase 4: Medium/Low (Fix WHEN POSSIBLE)
17. **M9-M35** — Backend improvements
18. **L1-L8** — Code quality improvements

---

## 📈 COMPARISON: Before vs After Round 1 Fixes

| Metric | Before Fixes | After Round 1 | After Round 2 Audit |
|--------|--------------|---------------|---------------------|
| **Critical Bugs** | 7 | 0 | 12 |
| **High Bugs** | 18 | 10 | 28 |
| **Medium Bugs** | 25 | 25 | 35 |
| **Low Bugs** | 16 | 16 | 8 |
| **Total** | 66 | 51 | 83 |

**Note:** The count increased because this audit was MUCH more thorough (83 files reviewed vs 10 initially). The original 66 bugs were surface-level; these 83 are deep code-level issues.

---

## 💡 RECOMMENDATIONS

1. **Fix C1 first** — The mixed ORM issue is the root cause of many other bugs (C2, H19, M30, M31). Once standardized, ~15 other bugs become trivial or disappear.

2. **Add database unique indexes** — This alone fixes C8 and prevents data corruption across the system.

3. **Implement access control middleware** — A single `requireOwnership()` or `requireResourceAccess()` middleware can fix H1-H5 in one shot.

4. **Add input validation to ALL routes** — Use the existing `validators.js` middleware consistently.

5. **Wrap financial operations in transactions** — C3, C4, C5, H11 all solved by using MongoDB transactions.

6. **Run `npm audit`** — Check for vulnerable dependencies.

7. **Add ESLint** — Catch unused imports (M11) and other code quality issues automatically.

---

**Report generated:** 7 April 2026
**Next action:** Prioritize and fix Phase 1 bugs (critical security)
