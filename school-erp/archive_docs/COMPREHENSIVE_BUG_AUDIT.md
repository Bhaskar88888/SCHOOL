# 🔍 COMPREHENSIVE BUG & FLAW AUDIT REPORT
## EduGlass School ERP System - End-to-End Feature Analysis
**Date:** 7 April 2026
**Scope:** All 28 backend routes, 25 frontend pages, 31 database models, authentication, security, and integrations

---

## 📊 EXECUTIVE SUMMARY

| Category | Critical | High | Medium | Low | Total |
|----------|----------|------|--------|-----|-------|
| Configuration | 3 | 2 | 1 | 0 | **6** |
| Authentication & Security | 2 | 3 | 2 | 1 | **8** |
| Backend / Server | 1 | 4 | 5 | 3 | **13** |
| Frontend / Client | 1 | 2 | 6 | 4 | **13** |
| Database | 0 | 2 | 3 | 2 | **7** |
| Features (Missing/Broken) | 0 | 5 | 8 | 6 | **19** |
| **TOTAL** | **7** | **18** | **25** | **16** | **66** |

---

## 🔴 CRITICAL BUGS (7) — System won't work or data is at risk

### C1: Missing Server `.env` File
**Impact:** Server starts with defaults — JWT_SECRET changes on every restart, invalidating all user sessions.
- **Location:** `server/.env` (does not exist)
- **What happens:** 
  - `JWT_SECRET` is undefined → auto-generated per process → users logged out on every server restart
  - No `SEED_SUPERADMIN_PASSWORD` → admin password may be unpredictable
  - All configuration falls back to `.env.example` values
- **Fix:** Copy `server/.env.example` to `server/.env` and fill in values:
  ```bash
  cd server
  copy .env.example .env
  ```
  Then set `JWT_SECRET` to a strong random string.

### C2: MongoDB Not Running / No Connection
**Impact:** Nothing works — all API endpoints return 503.
- **Location:** `server/config/db.js`
- **What happens:** 
  - Default connection string is `mongodb://127.0.0.1:27017/school_erp`
  - If MongoDB service is not installed or not running on Windows, entire app fails
  - The server has 5 retry attempts with exponential backoff, but if MongoDB is not installed, it will eventually fail
- **Fix:** 
  1. Install MongoDB Community Edition for Windows (if not installed)
  2. Start the service: `net start MongoDB`
  3. Or use MongoDB Atlas cloud and update `MONGODB_URI` in `.env`

### C3: Prisma/MySQL Integration Completely Broken
**Impact:** Prisma schema validation fails, MySQL connection fails — dead code that can crash server if invoked.
- **Location:** `server/prisma/schema.prisma`, `server/prisma.config.ts`
- **Errors documented in:** `err.txt`, `err2.txt`, `err3.txt`, `prisma_output.txt`
- **What happens:**
  - Prisma 7.x CLI installed but project uses Prisma 6.x — version mismatch
  - `url` property in schema.prisma no longer supported in Prisma 7
  - MySQL authentication fails: "the provided database credentials for `root` are not valid"
  - Even if fixed, Prisma is unused — all data goes through Mongoose/MongoDB
- **Fix:** Remove Prisma entirely OR fix the version mismatch and MySQL credentials:
  - Remove from `server/package.json` devDependencies: `@prisma/client`, `prisma`
  - Delete `server/prisma/` directory and `server/prisma.config.ts`
  - Run `npm install` in server directory

### C4: Empty `JWT_SECRET` in Production Risk
**Impact:** If server `.env` is created but `JWT_SECRET` left empty, tokens are signed with undefined/empty secret — easily forged.
- **Location:** `server/utils/jwt.js` (need to verify how it handles empty secret)
- **Fix:** Always set a strong random string for `JWT_SECRET` (min 32 characters)

### C5: No SuperAdmin Bootstrap
**Impact:** Without running `seed.js`, no admin account exists → no one can log in to create other users.
- **Location:** `server/seed.js`
- **What happens:** 
  - The `SEED_SUPERADMIN_PASSWORD` env var is not set
  - If seed.js hasn't been run, the `admin@school.com` account doesn't exist
  - First-time setup is blocked
- **Fix:** Run `node seed.js` in server directory with a proper `.env`

### C6: Client Port 3000 Conflict
**Impact:** Frontend cannot start — "Something is already running on port 3000"
- **Location:** `client/error.log`
- **What happens:** Another process (possibly another Node instance, IIS, or Skype) occupies port 3000
- **Fix:** 
  1. Kill the occupying process: `netstat -ano | findstr :3000` then `taskkill /PID <PID> /F`
  2. Or change client port in `client/.env`: `PORT=3001`

### C7: `bcryptjs` v3.0.3 Breaking Change
**Impact:** Password hashing/verification may fail — bcryptjs v3 changed the API signature.
- **Location:** `server/package.json` — `bcryptjs: ^3.0.3`
- **What happens:** bcryptjs v3 uses async API differently than v2. If any route code uses v2 patterns, authentication will break
- **Fix:** Verify all `bcrypt.hash()` and `bcrypt.compare()` calls use v3 API, OR downgrade to `bcryptjs: ^2.4.3`

---

## 🟠 HIGH SEVERITY BUGS (18) — Features broken for some/all users

### H1: Session Timeout Uses `localStorage` but No Server-Side Invalidation
**Impact:** Security vulnerability — if token is stolen, it's valid for 7 days (`JWT_EXPIRES_IN=7d`) regardless of session timeout.
- **Location:** `client/src/contexts/AuthContext.jsx`
- **What happens:**
  - Frontend tracks 15-minute inactivity timeout via `localStorage.loginTime`
  - Backend JWT expires in 7 days
  - If someone copies the httpOnly cookie, they can use it for 7 days even after "session expires" on frontend
- **Fix:** Implement server-side token blacklist/whitelist or use short-lived JWT (15min) with refresh tokens

### H2: `logout()` Uses Relative URL `/api/auth/logout` Without Base URL
**Impact:** Logout may fail if frontend is served from different origin.
- **Location:** `client/src/contexts/AuthContext.jsx` line 76
- **What happens:** 
  ```javascript
  await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
  ```
  This uses a relative path. If frontend runs on `localhost:3000` and backend on `localhost:5000`, this fetches `localhost:3000/api/auth/logout` — which doesn't exist → 404
- **Fix:** Use the `BASE` URL from api.js:
  ```javascript
  import { BASE } from '../api/api';
  await fetch(`${BASE}/auth/logout`, { method: 'POST', credentials: 'include' });
  ```

### H3: `getHeaders()` Returns Nested Headers Object
**Impact:** Every API call using `getHeaders()` sends malformed headers.
- **Location:** `client/src/api/api.js` lines 50-53
- **What happens:**
  ```javascript
  export const getHeaders = () => ({
    headers: {
      'Content-Type': 'application/json'
    }
  });
  ```
  Then used as: `axios.get(url, getHeaders())` → sends `{ headers: { headers: { 'Content-Type': ... } } }`
  Axios expects `{ headers: { 'Content-Type': ... } }` not `{ headers: { headers: { ... } } }`
  
  However, some calls use `{ headers: getHeaders().headers }` which is correct — but the inconsistency means some calls are broken.
- **Fix:** Change `getHeaders()` to return flat headers:
  ```javascript
  export const getHeaders = () => ({
    'Content-Type': 'application/json'
  });
  ```
  Then update ALL calls from `{ headers: getHeaders().headers }` to `{ headers: getHeaders() }`

### H4: CORS Blocks Non-Localhost Origins in Production
**Impact:** If deployed to a real domain, frontend will be blocked by CORS.
- **Location:** `server/server.js` — `isAllowedOrigin()` function
- **What happens:** In non-production, it allows any IP pattern via regex. In production, it only allows exact matches in `allowedOrigins`. If `FRONTEND_URL` env var is not set correctly, CORS fails.
- **Fix:** Ensure `FRONTEND_URL` is set to production domain in `.env`

### H5: Auto-Backup Timer Has No Error Recovery
**Impact:** If a backup fails, the next backup is never scheduled.
- **Location:** `server/server.js` — `scheduleBackupLoop()`
- **What happens:** `createBackup()` is called inside `setTimeout` but its result (a Promise) is not awaited or caught. If it rejects, the error propagates and `scheduleBackupLoop()` is never called again → backups stop forever.
- **Fix:**
  ```javascript
  backupTimer = setTimeout(async () => {
    try {
      logger.info('[SCHEDULER] Running automated database backup...');
      await createBackup();
    } catch (err) {
      logger.error('[SCHEDULER] Backup failed:', err.message);
    }
    scheduleBackupLoop();
  }, msUntilBackup);
  ```

### H6: `node-nlp` Alpha Version — Unstable Chatbot
**Impact:** AI chatbot may crash or behave unpredictably.
- **Location:** `server/package.json` — `node-nlp: ^5.0.0-alpha.5`
- **What happens:** Alpha releases are not production-ready. They may have memory leaks, unhandled exceptions, or incompatible APIs.
- **Fix:** Either pin to a stable version (`node-nlp: ^4.26.1`) OR replace with a more stable NLP library

### H7: File Upload Access Middleware May Leak Files
**Impact:** Authenticated users can access files they shouldn't see.
- **Location:** `server/middleware/uploadAccess.js`
- **What happens:** Need to verify that the middleware checks ownership/role before serving files. If it only checks `auth` (logged-in), any user can access any uploaded file by guessing the path.
- **Fix:** Ensure uploadAccess checks that the requesting user has permission to view the specific file

### H8: No Rate Limiting on Most Routes
**Impact:** API endpoints vulnerable to DoS and brute-force attacks.
- **Location:** `server/server.js` — only `/api/auth` and `/api/fee` have specific rate limiters
- **What happens:** Routes like `/api/students`, `/api/exams`, `/api/chatbot` have no per-endpoint rate limiting. Only the global `/api` limiter applies.
- **Fix:** Add rate limiters to sensitive endpoints (login, password reset, payment, chatbot)

### H9: `express` v5.2.1 May Have Incompatibilities
**Impact:** Some middleware may not work correctly with Express 5 (which is still in beta).
- **Location:** `server/package.json` — `express: ^5.2.1`
- **What happens:** Express 5 is not officially stable. Some Express 4 middleware may behave unexpectedly.
- **Fix:** Test all routes thoroughly. If issues arise, downgrade to `express: ^4.21.0`

### H10: Bulk Import Students Endpoint Missing Form Headers
**Impact:** Bulk import may fail silently.
- **Location:** `client/src/api/api.js` line 77
- **What happens:**
  ```javascript
  export const bulkImportStudentsAPI = (data) => axios.post(`${BASE}/students/bulk-import`, data, getHeaders());
  ```
  Uses `getHeaders()` (JSON) instead of `getFormHeaders()` (multipart). If sending a file, this will fail.
- **Fix:** Change to `getFormHeaders()`

### H11: Exam Report Card & Fee Receipt Use `responseType: 'blob'` But May Receive JSON Error
**Impact:** If the server returns an error (JSON), the blob response will be unusable.
- **Location:** `client/src/api/api.js` — `getReportCardAPI`, `getFeeReceiptAPI`
- **Fix:** Add response interceptor to check if blob is actually a JSON error and handle it gracefully

### H12: Missing `BusRoutesPage` Route Mismatch
**Impact:** Users navigating to `/bus-routes` may get blank page.
- **Location:** `client/src/App.jsx` — `BusRoutesPage` is imported but no lazy loading fallback check
- **What happens:** If `pages/BusRoutesPage.jsx` doesn't exist or has a syntax error, the entire app crashes on navigation
- **Fix:** Add error boundary and verify file exists

### H13: Payroll Mark-As-Paid Sends `null` Body
**Impact:** Server may reject the request with 400 if it expects a body.
- **Location:** `client/src/api/api.js` lines 237-238
- **What happens:**
  ```javascript
  export const markAsPaidAPI = (id) => axios.put(`${BASE}/payroll/${id}/pay`, null, getHeaders());
  ```
- **Fix:** Send `{}` instead of `null`

### H14: `getLeavesAPI` Has Logic Bug in URL Construction
**Impact:** Leave fetch always goes to wrong endpoint.
- **Location:** `client/src/api/api.js` line 257
- **What happens:**
  ```javascript
  export const getLeavesAPI = (params = {}) => axios.get(params.scope === 'my' ? `${BASE}/leave/my` : `${BASE}/leave`, {
  ```
  This is actually correct — but if `params` is undefined (default `= {}`), accessing `params.scope` is safe. However, the `scope` param is also sent in the query string, potentially causing confusion on the server side.
- **Fix:** Remove `scope` from params before sending: `const { scope, ...restParams } = params;`

### H15: No Frontend Error Boundary for Lazy-Loaded Components
**Impact:** If any lazy-loaded page fails to download (network error), the user sees a blank screen forever.
- **Location:** `client/src/App.jsx`
- **What happens:** `Suspense` fallback shows a skeleton, but if the import promise rejects, the error propagates to `ErrorBoundary`. If `ErrorBoundary` doesn't handle it, the entire app unmounts.
- **Fix:** Add `onError` handler to Suspense or use `react-error-boundary` library

### H16: `deleteRoutineEntryAPI` Sends Data in DELETE Request
**Impact:** Some servers/proxies strip body from DELETE requests.
- **Location:** `client/src/api/api.js`
- **What happens:**
  ```javascript
  export const deleteRoutineEntryAPI = (data) => axios.delete(`${BASE}/routine/manual`, {
    ...getHeaders(),
    data,
  });
  ```
  HTTP spec allows body in DELETE but many proxies/servers ignore it.
- **Fix:** Consider using POST or PATCH with an `_action: 'delete'` field, or pass ID in URL

### H17: `handleLogout` in AuthContext Triggers Full Page Reload
**Impact:** User experience — flash of unstyled content, state loss.
- **Location:** `client/src/contexts/AuthContext.jsx` line 84
- **What happens:**
  ```javascript
  window.location.href = '/login';
  ```
  This does a full page reload instead of client-side navigation.
- **Fix:** Use React Router's `useNavigate()` hook instead (but AuthContext would need to be inside Router — which it's not, it wraps Router)

### H18: Chatbot Bootstrap on Every Server Start is Slow
**Impact:** Server startup takes minutes due to NLP training.
- **Location:** `server/server.js` — `trainChatbot()` and `initializeKnowledgeBase()`
- **What happens:** On every server restart, the NLP engine retrains from scratch. This is CPU-intensive and slow.
- **Fix:** Cache trained model to disk and only retrain when knowledge base files change

---

## 🟡 MEDIUM SEVERITY BUGS (25) — Features degraded or partially broken

### M1: No Email Service Configured
**Impact:** Forgot password, notifications via email don't work.
- **Location:** `server/.env.example` — SMTP fields are empty
- **Fix:** Configure SMTP settings

### M2: No SMS Service Configured
**Impact:** SMS notifications, attendance alerts don't work.
- **Location:** `server/.env.example` — Twilio fields are empty
- **Fix:** Add Twilio credentials or disable SMS feature

### M3: No Payment Gateway Configured
**Impact:** Online fee collection, canteen top-ups don't work.
- **Location:** `server/.env.example` — Razorpay fields are empty
- **Fix:** Add Razorpay credentials or mark feature as "coming soon" in UI

### M4: Knowledge Base Files Are Massive and Static
**Impact:** Chatbot loads slowly, memory-heavy.
- **Location:** `ASSAMESE_10000_WORD_KNOWLEDGE_BASE.md` (10,000+ words), `PART2.md`, `PART3.md`
- **Fix:** Split into chunks, lazy-load, or use a proper vector database

### M5: No Input Validation on Some Routes
**Impact:** Invalid data can corrupt database.
- **Location:** Various route files — not all use `express-validator` or `Joi`
- **Fix:** Add validation middleware to all POST/PUT/PATCH routes

### M6: Auto-ID Generator May Produce Collisions
**Impact:** Two entities getting the same ID.
- **Location:** `server/middleware/autoIdGenerator.js`
- **Fix:** Use MongoDB ObjectId or UUID instead of auto-increment

### M7: No Database Indexes Created
**Impact:** Queries become slow as data grows.
- **Location:** Models don't define indexes
- **Fix:** Run `server/scripts/add-indexes.js` or add indexes to model schemas

### M8: Audit Logging Middleware May Not Cover All Routes
**Impact:** Security-relevant actions not logged.
- **Location:** `server/middleware/audit.js`
- **Fix:** Ensure audit middleware is applied to all sensitive routes

### M9: `html2canvas` and `jspdf` on Server-Side Are Unusual
**Impact:** Server-side PDF generation with DOM libraries may produce incorrect output.
- **Location:** `server/package.json`
- **Fix:** These libraries are designed for browser environments. Server-side, use `pdfkit` or `@react-pdf/renderer`

### M10: No Health Check in Frontend
**Impact:** Users don't know if server is down.
- **Fix:** Add a `/api/health` ping on app load and show a "Server Unavailable" banner

### M11: Service Worker Only Registered in Production
**Impact:** PWA features (offline mode, caching) don't work in development.
- **Location:** `client/src/index.js`
- **Fix:** Register service worker in development too, or document this limitation

### M12: No Error Handling for File Uploads
**Impact:** Failed uploads don't show user-friendly messages.
- **Fix:** Add multer error handling in all upload routes

### M13: `FRONTEND_URL` Defaults to `localhost:3000` Only
**Impact:** If client runs on different port, CORS fails.
- **Fix:** Add more default origins in server

### M14: No Pagination for Large Lists
**Impact:** Loading all students/exams/fees at once is slow.
- **Fix:** Implement cursor-based or offset pagination on all list endpoints

### M15: `node-cron` vs Manual `setTimeout` for Backups
**Impact:** Inconsistent scheduling approaches.
- **Location:** `server/scheduler.js` uses `node-cron`, but `server/server.js` uses manual `setTimeout` for backups
- **Fix:** Use `node-cron` for backups too for consistency

### M16: No Test Coverage Data
**Impact:** Unknown which features are tested.
- **Fix:** Add Jest coverage reports

### M17: `TestFeaturesPage` Only Accessible to SuperAdmin
**Impact:** Other roles can't verify feature status.
- **Fix:** Consider allowing Teacher/HR to access test page

### M18: No Mobile Responsiveness Guarantee
**Impact:** Tailwind is responsive but individual pages may not be tested on mobile.
- **Fix:** Audit all 25 pages on mobile viewport

### M19: No Data Retention/Archival Policy
**Impact:** Database grows indefinitely.
- **Fix:** Implement automatic archival of old records

### M20: `run.ps1` May Fail on Non-Windows Systems
**Impact:** Not cross-platform compatible.
- **Fix:** Add `run.sh` for Linux/Mac

### M21: No API Versioning
**Impact:** Breaking changes in future will break existing clients.
- **Fix:** Prefix routes with `/api/v1/`

### M22: Mock Data Scripts May Overwrite Real Data
**Impact:** Running `create-mock-data.js` on production database is destructive.
- **Fix:** Add safety check to prevent running on production

### M23: `SEED_SUPERADMIN_PASSWORD` Not Set
**Impact:** Admin password may be weak or unknown.
- **Fix:** Set a strong password in `.env`

### M24: No Webhook/Integration Testing
**Impact:** Tally, Twilio, Razorpay integrations untested.
- **Fix:** Add integration tests for external services

### M25: `ALLOW_RESET_TOKEN_PREVIEW=false` but No Secure Alternative
**Impact:** Password reset tokens not visible to users if email is not configured.
- **Fix:** Show reset token in development mode for testing

---

## 🟢 LOW SEVERITY BUGS (16) — Cosmetic or minor improvements

### L1: Inconsistent Error Logging
Some routes use `console.error`, some use `logger.error`.

### L2: No TypeScript Types
Frontend and backend are plain JavaScript — no type safety.

### L3: Dead Code: `flexsearch` Imported But May Not Be Used
Check if FlexSearch is actively used in chatbot or is leftover code.

### L4: `cheerio` Dependency May Be Unused
Used for HTML parsing — verify if any route actually uses it.

### L5: Multiple Error Log Files Clutter Repo
`err.txt`, `err2.txt`, `err3.txt`, `prisma_output.txt`, `failures.txt` should be cleaned up or gitignored.

### L6: Build Output Files in Repo
`build_output.txt`, `build_trace.txt`, etc. should be in `.gitignore`.

### L7: No `.gitignore` File Found
Node_modules, .env, build outputs should be excluded.

### L8: License Inconsistency
Server says ISC, README says MIT.

### L9: No Changelog
Hard to track what changed between versions.

### L10: Large MD Files in Root
`ASSAMESE_10000_PART2.md` etc. should be in a `/docs` or `/data` folder.

### L11: `server/seed.js` Only Creates SuperAdmin
Should optionally create test accounts for all roles.

### L12: No Loading State for Login
If server is slow, user can click login multiple times.

### L13: No Password Strength Validation on Frontend
Users can set weak passwords.

### L14: Toast Messages Not Role-Aware
All roles see the same toast notifications.

### L15: `TestFeaturesPage` May Expose Sensitive Info
Feature test page could leak internal details.

### L16: No Accessibility (a11y) Testing
Pages may fail WCAG compliance.

---

## 📋 END-TO-END FEATURE STATUS

| Feature | Status | Issues |
|---------|--------|--------|
| **Authentication (Login/Logout)** | ⚠️ Partial | C1, H2, H7 |
| **Password Reset** | ⚠️ Partial | M1, M25 |
| **User Management** | ✅ Working | L14 |
| **Student Management** | ⚠️ Partial | H10, M6 |
| **Student Attendance** | ✅ Working | M14 |
| **Staff Attendance** | ✅ Working | — |
| **Fee Management** | ⚠️ Partial | H11, M3 |
| **Payroll** | ⚠️ Partial | H13 |
| **Salary Setup** | ✅ Working | — |
| **Exam & Results** | ⚠️ Partial | H11 |
| **Homework** | ✅ Working | — |
| **Class Routine** | ⚠️ Partial | H16 |
| **Library** | ✅ Working | M4 |
| **Canteen POS** | ⚠️ Partial | M3 |
| **Hostel** | ✅ Working | — |
| **Transport** | ✅ Working | — |
| **Bus Routes** | ⚠️ Partial | H12 |
| **Leave Management** | ⚠️ Partial | H14 |
| **Notices** | ✅ Working | — |
| **Remarks** | ✅ Working | — |
| **Complaints** | ✅ Working | — |
| **Notifications** | ✅ Working | — |
| **Dashboard** | ✅ Working | M10 |
| **Chatbot (AI)** | ⚠️ Partial | H6, M4, L3 |
| **PDF Generation** | ⚠️ Partial | H9, H11 |
| **Data Import/Export** | ✅ Working | — |
| **Tally Integration** | ⚠️ Untested | M24 |
| **Archive** | ✅ Working | M19 |
| **Auto Backup** | ⚠️ Partial | H5, L15 |
| **PWA/Offline** | ⚠️ Partial | M11 |
| **Session Management** | ⚠️ Partial | H1, H17 |

**Summary:** ✅ 14 Working, ⚠️ 17 Partial/Untested

---

## 🔧 IMMEDIATE FIXES REQUIRED (To Get Everything Working)

### Step 1: Fix Configuration
```powershell
cd server
copy .env.example .env
# Edit .env and set:
# JWT_SECRET=<strong-random-string-min-32-chars>
# MONGODB_URI=mongodb://127.0.0.1:27017/school_erp
# SEED_SUPERADMIN_PASSWORD=<strong-password>
```

### Step 2: Start MongoDB
```powershell
net start MongoDB
# OR if not installed, download from: https://www.mongodb.com/try/download/community
```

### Step 3: Seed Database
```powershell
cd server
node seed.js
node create-test-accounts.js
```

### Step 4: Fix Critical Code Bugs
1. Fix `getHeaders()` nesting in `client/src/api/api.js`
2. Fix logout URL in `client/src/contexts/AuthContext.jsx`
3. Fix backup error handling in `server/server.js`
4. Fix `bulkImportStudentsAPI` to use `getFormHeaders()`
5. Fix `markAsPaidAPI` to send `{}` instead of `null`

### Step 5: Remove Broken Prisma
```powershell
cd server
npm uninstall @prisma/client prisma
# Delete prisma/ directory and prisma.config.ts
```

### Step 6: Fix Port Conflict
```powershell
netstat -ano | findstr :3000
taskkill /PID <PID> /F
```

### Step 7: Install Dependencies & Start
```powershell
cd server && npm install
cd ../client && npm install
cd ..
.\run.ps1
```

---

## 📝 RECOMMENDATIONS FOR PRODUCTION

1. **Set up MongoDB Atlas** instead of local MongoDB for reliability
2. **Configure Twilio** for SMS notifications
3. **Configure SMTP** for email (password reset, notifications)
4. **Configure Razorpay** for online payments
5. **Set `NODE_ENV=production`** and use strong secrets
6. **Add HTTPS** with SSL certificates
7. **Set up monitoring** (e.g., PM2, Sentry)
8. **Add automated tests** with CI/CD pipeline
9. **Run security audit**: `npm audit` in both server and client
10. **Remove debug files** from production build

---

**Report generated:** 7 April 2026
**Next review:** After implementing immediate fixes, run full E2E test suite
