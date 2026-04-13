# TEAM LEAD PROJECT REPORT - School ERP PHP v3.0

**Date:** April 12, 2026
**Prepared By:** Team Lead
**Status:** MASTER DOCUMENT - Single Source of Truth for All Agents
**Project Root:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\`

---

## SECTION 1: COMPLETE ARCHITECTURE MAP

### 1.1 Directory Structure

```
school-erp-php/
|-- index.php                    # Login page
|-- dashboard.php                # Main dashboard after login
|-- *.php                        # Frontend pages (students.php, fee.php, etc.)
|
|-- includes/
|   |-- db.php                   # PDO database connection + helpers (db_query, db_fetch, etc.)
|   |-- auth.php                 # Authentication + session management + role helpers
|   |-- csrf.php                 # CSRFProtection class + csrf_middleware()
|   |-- helpers.php              # Utility functions (generate_auto_id, calculate_grade, format_currency, etc.)
|   |-- env_loader.php           # Loads .env.php and defines constants
|   |-- api_response.php         # Standardized API response functions (api_response, api_success, api_error, etc.)
|   |-- validator.php            # Input validation class (Validator::required, Validator::email, etc.)
|   |-- header.php               # Top header HTML component
|   |-- sidebar.php              # Sidebar navigation with role-based menu items
|   |-- data.php                 # Module page data loader (loads $classes, $teachers, $students, $staff based on flags)
|   |-- audit_logger.php         # AuditLogger class for audit trail
|   |-- rate_limiter.php         # RateLimiter class (file-based rate limiting)
|   |-- sms_service.php          # SMSService singleton (Twilio integration)
|
|-- config/
|   |-- env.php                  # Legacy compatibility layer (loads env_loader.php)
|
|-- api/
|   |-- students/index.php       # Student CRUD (GET/POST/PUT/DELETE)
|   |-- students/enhanced.php    # Enhanced student endpoints
|   |-- students/import.php      # Student import
|   |-- students/export.php      # Student export
|   |-- fee/index.php            # Fee CRUD (GET/POST/PUT/DELETE)
|   |-- fee/enhanced.php         # Fee structures, defaulters, collection reports, receipts
|   |-- exams/index.php          # Exam CRUD + results saving
|   |-- exams/enhanced.php       # Analytics, report cards, bulk results
|   |-- attendance/index.php     # Attendance mark/view/reports
|   |-- users/index.php          # User management CRUD
|   |-- auth/login.php           # (Not found - login handled in index.php)
|   |-- auth/logout.php          # Session destroy + redirect
|   |-- auth/register.php        # User registration (admin only)
|   |-- auth/change-password.php # Password change
|   |-- auth/forgot_password.php # Password reset request
|   |-- auth/reset_password.php  # Password reset completion
|   |-- auth/create-staff.php    # Staff creation
|   |-- classes/index.php        # Class CRUD
|   |-- classes/enhanced.php     # Enhanced class endpoints
|   |-- dashboard/stats.php      # Dashboard statistics + charts data
|   |-- profile/index.php        # User profile + password change
|   |-- notifications/list.php   # Notification list
|   |-- complaints/index.php     # Complaint CRUD
|   |-- complaints/enhanced.php  # Enhanced complaint endpoints
|   |-- transport/index.php      # Transport management
|   |-- transport/enhanced.php   # Enhanced transport
|   |-- hostel/index.php         # Hostel management
|   |-- hostel/enhanced.php      # Enhanced hostel
|   |-- library/index.php        # Library management
|   |-- library/dashboard.php    # Library dashboard
|   |-- library/scan_isbn.php    # ISBN scanning
|   |-- payroll/index.php        # Payroll management
|   |-- hr/index.php             # HR/Staff management
|   |-- homework/index.php       # Homework management
|   |-- homework/enhanced.php    # Enhanced homework
|   |-- notices/index.php        # Notices management
|   |-- routine/index.php        # Class routine management
|   |-- leave/index.php          # Leave applications
|   |-- leave/enhanced.php       # Enhanced leave
|   |-- canteen/index.php        # Canteen management
|   |-- canteen/enhanced.php     # Enhanced canteen
|   |-- remarks/index.php        # Remarks management
|   |-- remarks/enhanced.php     # Enhanced remarks
|   |-- audit/index.php          # Audit log viewer
|   |-- chatbot/chat.php         # AI chatbot
|   |-- chatbot/bootstrap.php    # Chatbot bootstrap
|   |-- chatbot/history.php      # Chatbot history
|   |-- chatbot/analytics.php    # Chatbot analytics
|   |-- export/index.php         # Data export
|   |-- export/excel.php         # Excel export
|   |-- export/pdf.php           # PDF export
|   |-- export/tally.php         # Tally export
|   |-- import/index.php         # Data import
|   |-- import/templates.php     # Import templates
|   |-- pdf/generate.php         # PDF generation
|   |-- health.php               # Health check
|   |-- bus-routes/index.php     # Bus routes
|   |-- staff-attendance/index.php # Staff attendance
|   |-- salary-setup/index.php   # Salary setup
|   |-- archive/index.php        # Archive
|
|-- assets/
|   |-- css/style.css            # Main stylesheet (~886 lines)
|   |-- js/main.js               # Global JS (apiGet, apiPost, showToast, modal helpers, chatbot)
|
|-- .env.php                     # Environment configuration (git-ignored)
|-- .env.example                 # Template for .env.php
|-- .htaccess                    # Apache security + rewrite rules
```

### 1.2 Request Flow

```
User clicks button in browser
  --> JavaScript function calls apiGet/apiPost/apiDelete (assets/js/main.js)
  --> fetch() to /api/MODULE/index.php
  --> PHP file requires includes/auth.php (which loads db.php, env_loader.php)
  --> require_auth() checks session
  --> Method routing (GET/POST/PUT/DELETE)
  --> Database query via db_fetch/db_fetchAll/db_query/db_insert
  --> json_response() returns JSON
  --> JavaScript processes response and updates DOM
```

### 1.3 Authentication Flow

```
1. User submits login form (index.php POST)
2. RateLimiter::check() validates rate limits
3. is_account_locked() checks lockout status
4. db_fetch("SELECT * FROM users WHERE email = ? AND is_active = 1")
5. password_verify($password, $user['password'])
6. login_user_enhanced($user) - sets session variables
7. session_regenerate_id(true) - prevents session fixation
8. audit_log('LOGIN', 'auth', 'User logged in successfully')
9. Redirect to dashboard.php
```

### 1.4 Role-Based Access Control

**Role normalization chain:**
- `normalize_role_name()` converts aliases (e.g., "accountant" -> "accounts", "super admin" -> "superadmin")
- `role_matches($currentRole, $allowedRoles)` - superadmin always passes; others need exact match
- `require_role(['superadmin','admin','teacher'])` - used in API endpoints and pages

**Nav items in sidebar.php** have a `roles` array - items are filtered per logged-in user.

### 1.5 Database Schema (Key Tables)

Based on code analysis, the following tables exist:
- **users** - id, name, email, password, role, employee_id, department, designation, phone, avatar, is_active, created_at, login_attempts, locked_until, reset_token, reset_token_expiry
- **students** - id, name, admission_no, class_id, section, roll_number, dob, gender, phone, email, parent_name, parent_phone, parent_email, father_name, mother_name, guardian_name, address, transport_required, hostel_required, is_active, user_id, parent_user_id, created_at, updated_at
- **classes** - id, name, section, teacher_id, capacity, is_active
- **fees** - id, student_id, fee_type, total_amount, amount_paid, balance_amount, payment_method, receipt_no, paid_date, due_date, month, year, remarks, collected_by, fee_structure_id, created_at
- **attendance** - id, student_id, class_id, date, status, subject, note, remarks, marked_by, teacher_id, sms_sent
- **exams** - id, name, class_id, subject, exam_date, start_time, end_time, max_marks, pass_marks, description
- **exam_results** - id, exam_id, student_id, marks_obtained, total_marks, grade, status, entered_by, percentage
- **notifications** - id, target_user, title, message, is_read, created_at
- **notifications_enhanced** - id, recipient_id, sender_id, title, message, type, is_read, created_at
- **complaints** - id, title, description, category, priority, status, submitted_by, assigned_to, type, target_user_id, assigned_to_role, raised_by_role, resolved_at, created_at
- **fee_structures** - id, class_id, fee_type, amount, academic_year, term, due_date, late_fee, description
- **library_books** - id, ... (various book fields)
- **audit_logs** - id, user_id, action, module, description, ip_address, created_at
- **audit_logs_enhanced** - id, user_id, action, module, record_id, old_value, new_value, ip_address, user_agent, created_at
- **counters** - name, year, sequence (for auto ID generation)
- **leave_applications** - id, ... (various leave fields)
- **homework** - id, ... (various homework fields)
- **notices** - id, ... (various notice fields)
- **hostel_rooms** - id, ... (various hostel fields)
- **transport_routes**, **transport_vehicles** - transport management
- **canteen_items**, **canteen_sales** - canteen management

---

## SECTION 2: EVERY SINGLE BUG DOCUMENTED

### BUG-001: Missing CSRF Protection on ALL API Endpoints
- **File:** All files in `/api/` directory (65+ files)
- **Priority:** P0 (Critical)
- **Security Issue:** Cross-Site Request Forgery attacks possible on all POST/PUT/DELETE operations
- **Current State:** `includes/csrf.php` exists but is NEVER included in any API endpoint
- **Fix:** Add to EVERY API endpoint that handles POST/PUT/DELETE:
  ```php
  require_once __DIR__ . '/../../includes/csrf.php';
  // At top of file after auth requires
  ```
  And for non-GET requests:
  ```php
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      csrf_middleware();
  }
  ```
- **Dependencies:** All API endpoints, frontend forms using POST
- **Verification:** Test POST requests from external origin - should be rejected without CSRF token
- **NOTE:** The `apiGet`/`apiPost` helpers in `assets/js/main.js` do NOT send CSRF tokens. These must be updated to include `X-CSRF-TOKEN` header.

### BUG-002: Missing `calculate_grade()` Import in Exams Enhanced API
- **File:** `api/exams/enhanced.php` (lines ~120, ~161, ~225)
- **Priority:** P0 (Critical - Fatal Error)
- **Current Code:** `calculate_grade()` is called but `includes/helpers.php` is already included (line 9). However, `includes/helpers.php` does NOT have `require_once __DIR__ . '/db.php'` at top - it relies on other files to load it first.
- **Analysis:** Actually `helpers.php` functions (`calculate_grade`, `generate_auto_id`, etc.) call `db_query()` without requiring db.php. The file `api/exams/enhanced.php` includes `db.php` at line 7 and `helpers.php` at line 9, so db functions are available. The real issue is that `calculate_grade` in helpers.php is a plain function that does not depend on db. This bug is a **FALSE POSITIVE** in the original audit - helpers.php IS included at line 9 of enhanced.php.
- **Status:** VERIFY - May not be a real bug, but ensure helpers.php is always included where calculate_grade is used.

### BUG-003: Exam List Returns Empty Array in Enhanced API
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\exams\enhanced.php` (line ~100)
- **Priority:** P1 (High)
- **Current Code:**
  ```php
  // Regular exam list (existing functionality)
  // ... existing code ...
  json_response(['exams' => []]);
  ```
- **Problem:** The regular exam list endpoint always returns empty array `[]`
- **Fix:** Replace with actual query:
  ```php
  $classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
  if ($classId > 0) {
      $exams = db_fetchAll("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE e.class_id = ? ORDER BY e.exam_date DESC", [$classId]);
  } else {
      $exams = db_fetchAll("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id ORDER BY e.exam_date DESC");
  }
  json_response(['exams' => $exams]);
  ```
- **Verification:** Call `/api/exams/enhanced.php` and verify exams array is populated
- **Note:** The frontend `exams.php` actually uses `/api/exams/index.php` (not enhanced.php), so this bug affects any code that calls the enhanced endpoint for listing.

### BUG-004: Missing Authorization on Fee Enhanced Endpoints
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\fee\enhanced.php` (lines ~58, ~77, ~133, ~153, ~189)
- **Priority:** P1 (High) - Security
- **Current Code:** Actions `defaulters`, `collection-report`, `receipt`, `payments`, `student` have NO `require_role()` checks
- **Fix:** Add `require_role(['superadmin', 'admin', 'accounts'])` at the start of each action block:
  ```php
  if (isset($_GET['action']) && $_GET['action'] === 'defaulters') {
      require_role(['superadmin', 'admin', 'accounts']);
      // ... rest of code
  }
  ```
- **Verification:** Login as student/parent and try accessing `/api/fee/enhanced.php?action=defaulters` - should return 403

### BUG-005: Broken Complaints onclick Handler - JavaScript Syntax Error
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\complaints.php` (line ~124)
- **Priority:** P1 (High)
- **Current Code:**
  ```javascript
  onclick="manage(${c.id}, '${c.status}', ${c.assigned_to||''})"
  ```
- **Problem:** When `c.assigned_to` is null, renders as `manage(1, 'pending', )` - syntax error (missing argument)
- **Fix:** Quote the third parameter:
  ```javascript
  onclick="manage(${c.id}, '${c.status}', '${c.assigned_to||''}')"
  ```
- **Verification:** Open complaints page with unassigned complaint, click Manage button - should work without JS error
- **Also fix the `manage()` function to handle string vs int:**
  ```javascript
  function manage(id, st, assigned) {
      document.getElementById('manageId').value = id;
      document.getElementById('manageStatus').value = st;
      document.getElementById('manageAssign').value = assigned || '';
      openModal('manageModal');
  }
  ```

### BUG-006: Fee Edit Always Resets Discount to 0
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\fee.php` (line ~164)
- **Priority:** P1 (High) - Data Loss
- **Current Code:**
  ```javascript
  if (form.discount) form.discount.value = 0;
  ```
- **Fix:**
  ```javascript
  if (form.discount) form.discount.value = data.discount || 0;
  ```
- **Verification:** Edit a fee with a non-zero discount - discount field should preserve the original value

### BUG-007: Users.js API URL Missing `/index.php`
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\users.php` (lines ~237, ~277, ~293, ~310, ~327)
- **Priority:** P1 (High)
- **Current Code:**
  ```javascript
  const response = await apiGet(`/api/users?${params.toString()}`);
  ```
- **Problem:** The actual endpoint is `/api/users/index.php`. Without URL rewriting configured in `.htaccess` for this path, all calls return 404.
- **Analysis:** The `.htaccess` has `RewriteRule ^(config|includes|tmp|tests)/ - [F,L]` which blocks includes/ but does NOT have rewrite rules to map `/api/users` to `/api/users/index.php`. However, the frontend uses `/api/users` directly.
- **Fix:** Change all API URLs in users.php from `/api/users` to `/api/users/index.php`:
  ```javascript
  const response = await apiGet(`/api/users/index.php?${params.toString()}`);
  // and
  const response = await apiPost('/api/users/index.php', payload);
  // and
  const response = await apiPost('/api/users/index.php', { _method: 'DELETE', id: userId });
  ```
- **Verification:** Open users page, verify users load correctly without 404 errors in console

### BUG-008: Attendance Student Self-View Uses `student_id=0`
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\attendance.php` (lines ~372, ~380)
- **Priority:** P1 (High)
- **Current Code:**
  ```javascript
  const resStats = await apiGet('/api/attendance/index.php?student_id=0&stats=1');
  const resHist = await apiGet('/api/attendance/index.php?student_id=0');
  ```
- **Problem:** The API (`api/attendance/index.php`) line ~75 checks: `if ($studentId <= 0) { json_response(['error' => 'student_id required'], 400); }`. So passing `student_id=0` returns an error.
- **Fix in attendance.php:** The backend needs to resolve the current user's student ID. The frontend should NOT pass `student_id=0`. Instead, create a dedicated endpoint or modify the API to handle the case where the user is a student/parent without an explicit student_id parameter.

  **Option A - Fix API to auto-resolve:** In `api/attendance/index.php`, before the `$studentId <= 0` check, add:
  ```php
  if ($role === 'student' && empty($studentId)) {
      $studentRecord = db_fetch("SELECT id FROM students WHERE user_id = ? AND is_active = 1", [get_current_user_id()]);
      if ($studentRecord) {
          $studentId = (int) $studentRecord['id'];
      }
  }
  if ($role === 'parent' && empty($studentId)) {
      $studentRecord = db_fetch("SELECT id FROM students WHERE parent_user_id = ? AND is_active = 1 LIMIT 1", [get_current_user_id()]);
      if ($studentRecord) {
          $studentId = (int) $studentRecord['id'];
      }
  }
  ```

  **Option B - Fix frontend to get student_id first:** Query the students API to find the current user's student ID, then pass it.

  **Recommended:** Option A - Fix the API.

- **Verification:** Login as student, open attendance page, verify attendance stats and history load correctly

### BUG-009: Fee DELETE Handler - Potential Null Access
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\fee\index.php` (DELETE handler, ~line 85)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```php
  $fee = db_fetch("SELECT receipt_no FROM fees WHERE id = ?", [$id]);
  db_query("DELETE FROM fees WHERE id = ?", [$id]);
  json_response(['success' => true, 'receipt_no' => $fee['receipt_no'] ?? null]);
  ```
- **Problem:** If fee doesn't exist, `$fee` is null, and `$fee['receipt_no']` triggers PHP warning. Also, no audit log for deletion.
- **Fix:**
  ```php
  $fee = db_fetch("SELECT receipt_no FROM fees WHERE id = ?", [$id]);
  if (!$fee) {
      json_response(['error' => 'Fee record not found'], 404);
  }
  audit_log('DELETE', 'fees', $id, $fee, null, "Receipt: {$fee['receipt_no']}");
  db_query("DELETE FROM fees WHERE id = ?", [$id]);
  json_response(['success' => true, 'receipt_no' => $fee['receipt_no'] ?? null]);
  ```
- **Verification:** Delete non-existent fee ID - should return 404 error

### BUG-010: Exam Delete No Error Handling
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\exams.php` (line ~113)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```javascript
  async function deleteExam(id) {
      if (!confirm('Delete this exam?')) return;
      await fetch(`/api/exams/index.php?id=${id}`, {method:'DELETE'});
      showToast('Exam deleted'); loadExams();
  }
  ```
- **Fix:**
  ```javascript
  async function deleteExam(id) {
      if (!confirm('Delete this exam?')) return;
      const res = await fetch(`/api/exams/index.php?id=${id}`, {method:'DELETE'});
      const data = await res.json();
      if (data.error) {
          showToast(data.error, 'danger');
      } else {
          showToast('Exam deleted');
          loadExams();
      }
  }
  ```
- **Verification:** Try deleting an exam that has results - verify error message is shown if deletion fails

### BUG-011: Profile Password Change Reuses Profile Endpoint
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\profile\index.php` (POST handler)
- **Priority:** P2 (Medium)
- **Problem:** The POST handler updates name/email/phone AND password in one call. If a user changes password without intending to change profile data, the profile data is also re-saved (potentially stale).
- **Current Code in profile.php frontend:** Combines profile fields with password fields in single POST.
- **Fix:** The API already handles this reasonably - it only updates name/email/phone if provided, and separately handles password. However, the frontend should ideally use a dedicated password change endpoint. For now, the current behavior is acceptable but the frontend should be fixed to not send stale profile data.
- **Note:** `api/auth/change-password.php` already exists as a dedicated endpoint. Frontend should use that instead.

### BUG-012: Hostel NaN Comparison in Capacity Check
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\hostel.php` (~line 148)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```javascript
  rooms.filter(r => parseInt(r.occupants) < parseInt(r.capacity))
  ```
- **Problem:** If `r.capacity` is null/undefined, `parseInt(undefined)` returns `NaN`, and comparisons with NaN always return false.
- **Fix:**
  ```javascript
  rooms.filter(r => (parseInt(r.occupants) || 0) < (parseInt(r.capacity) || 0))
  ```
- **Verification:** Load hostel page with rooms that have null capacity - filtering should work without NaN issues

### BUG-013: Canteen Sales Label Mismatch
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\canteen.php` (~line 264)
- **Priority:** P3 (Low)
- **Current Code:**
  ```javascript
  document.getElementById('saleCount').textContent = 'Rs ' + (data.today_revenue || 0).toFixed(2);
  ```
- **Problem:** Label says "Sales Today" (count) but displays currency (revenue).
- **Fix:** Either rename the label to "Revenue Today" or use the actual sales count.
- **Verification:** Check UI labels match displayed values

### BUG-014: Dashboard Stats API Uses `db_count` for SUM
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\dashboard\stats.php` (lines ~7-8)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```php
  $feeThisMonth = db_count("SELECT COALESCE(SUM(amount_paid),0) FROM fees WHERE MONTH(paid_date)=MONTH(NOW()) AND YEAR(paid_date)=YEAR(NOW())");
  $pendingFee = db_count("SELECT COALESCE(SUM(balance_amount),0) FROM fees WHERE balance_amount > 0");
  ```
- **Problem:** `db_count()` calls `fetchColumn()` which returns the first column. This actually WORKS for SUM queries because SUM returns a single row with a single column. However, using `db_count` for a SUM is semantically misleading. It works but is confusing.
- **Status:** Works correctly but should use `db_fetch()` with column access for clarity. No actual bug.

### BUG-015: Notifications Query Missing Parentheses
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\notifications\list.php` (line ~9)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```php
  $notifications = db_fetchAll("SELECT * FROM notifications WHERE target_user IS NULL OR target_user = ? ORDER BY created_at DESC LIMIT 20", [$user['id']]);
  ```
- **Problem:** Without parentheses, the query logic is: `(target_user IS NULL) OR (target_user = ?)`. This is actually correct behavior (show global notifications + user-specific ones), but should be explicit:
  ```php
  $notifications = db_fetchAll("SELECT * FROM notifications WHERE (target_user IS NULL OR target_user = ?) ORDER BY created_at DESC LIMIT 20", [$user['id']]);
  ```
- **Status:** Minor clarity issue, not a functional bug.

### BUG-016: Fee API PUT Handler Missing Discount and balance_amount
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\fee\index.php` (PUT handler, ~line 72)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```php
  db_query(
      "UPDATE fees SET fee_type=?, total_amount=?, amount_paid=?, payment_method=?, remarks=? WHERE id=?",
      [...]
  );
  ```
- **Problem:** PUT handler does not update `discount`, `due_date`, `month`, `year`, or recalculate `balance_amount`. When editing a fee, these fields are lost.
- **Fix:** Add missing fields and recalculate balance:
  ```php
  $amountPaid = (float) ($data['amount_paid'] ?? 0);
  $discount = (float) ($data['discount'] ?? 0);
  $actualPaid = $amountPaid - $discount;
  $totalAmount = (float) ($data['total_amount'] ?? 0);
  $balance = $totalAmount - $actualPaid;

  db_query(
      "UPDATE fees SET fee_type=?, total_amount=?, amount_paid=?, discount=?, balance_amount=?, payment_method=?, due_date=?, month=?, year=?, remarks=? WHERE id=?",
      [
          sanitize($data['fee_type'] ?? ''),
          $totalAmount,
          $actualPaid,
          $discount,
          $balance,
          sanitize($data['payment_method'] ?? ''),
          $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
          sanitize($data['month'] ?? date('F')),
          (int) ($data['year'] ?? date('Y')),
          sanitize($data['remarks'] ?? ''),
          $id
      ]
  );
  ```
- **Verification:** Edit a fee with discount and updated due date - changes should persist

### BUG-017: API POST Data Sent as FormData but Endpoints Expect JSON
- **File:** `complaints.php` line ~134, and other forms using `Object.fromEntries(new FormData(e.target))`
- **Priority:** P2 (Medium)
- **Current Code in complaints.php:**
  ```javascript
  const res = await apiPost('/api/complaints/index.php', Object.fromEntries(new FormData(e.target)));
  ```
- **Problem:** `apiPost` sends `Content-Type: application/json` with `JSON.stringify(data)`. But `Object.fromEntries(new FormData(e.target))` produces an object with string values. The PHP `get_post_json()` reads raw input and decodes JSON. This should work, but numeric fields (like `assigned_to`) will be strings, causing type mismatch issues.
- **Status:** Works for basic cases but numeric fields may be passed as strings. PHP's loose typing usually handles this. Not a critical bug but worth noting.

### BUG-018: `includes/data.php` Loads All Staff/Students Unconditionally
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\includes\data.php`
- **Priority:** P3 (Low)
- **Current Code:**
  ```php
  $staff = isset($needsStaff) ? db_fetchAll("SELECT id, name FROM users WHERE role != 'student' AND is_active=1 ORDER BY name") : [];
  ```
- **Problem:** When `$needsStaff` is set, ALL non-student users are loaded into memory (could be hundreds). Should be paginated or lazy-loaded.
- **Status:** Performance concern, not a functional bug.

### BUG-019: Logout Link Uses Direct URL (No JS, but CSRF bypassed)
- **File:** `includes/header.php` and `includes/sidebar.php`
- **Priority:** P3 (Low)
- **Current Code:**
  ```html
  <a href="<?= BASE_URL ?>/api/auth/logout.php">Logout</a>
  ```
- **Problem:** Logout is a GET request. A malicious site could trigger logout by linking to the logout URL. Should be POST with CSRF protection.
- **Fix:** Convert to form submission or AJAX POST with CSRF token.

### BUG-020: Classes API DELETE Has No Foreign Key Check
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\classes\index.php` (DELETE handler)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```php
  db_query("DELETE FROM classes WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
  ```
- **Problem:** No check if class has enrolled students. Deleting a class with students would orphan them or fail with FK constraint (if enforced).
- **Fix:**
  ```php
  $studentCount = db_count("SELECT COUNT(*) FROM students WHERE class_id = ? AND is_active = 1", [(int) ($_GET['id'] ?? 0)]);
  if ($studentCount > 0) {
      json_response(['error' => "Cannot delete class with $studentCount enrolled students. Reassign or discharge students first."], 400);
  }
  db_query("DELETE FROM classes WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
  ```
- **Verification:** Try deleting a class with enrolled students - should show error

### BUG-021: Fee API Balance Amount Not Calculated on Insert
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\fee\index.php` (POST handler)
- **Priority:** P1 (High) - Data Integrity
- **Current Code:**
  ```php
  $amountPaid = (float) ($data['amount_paid'] ?? $data['total_amount']);
  $discount = (float) ($data['discount'] ?? 0);
  $amountPaid = $amountPaid - $discount; // Apply discount

  $id = db_insert(
      "INSERT INTO fees (student_id, fee_type, total_amount, amount_paid, ...) VALUES (...)",
      [(int) $data['student_id'], ..., (float) $data['total_amount'], $amountPaid, ...]
  );
  ```
- **Problem:** `balance_amount` column is NOT inserted. It should be `total_amount - amount_paid`. If the database doesn't auto-calculate this, the balance will be NULL or 0.
- **Fix:** Calculate and insert balance:
  ```php
  $balanceAmount = (float) $data['total_amount'] - $amountPaid;
  // Add balance_amount to INSERT columns and values
  ```
- **Verification:** Create a new fee payment with partial amount - verify balance_amount is correctly calculated

### BUG-022: `apiGet`/`apiPost` Helpers Do Not Handle HTTP Errors
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\assets\js\main.js` (lines ~65-80)
- **Priority:** P2 (Medium)
- **Current Code:**
  ```javascript
  async function apiGet(url) {
      const response = await fetch(url);
      return response.json();
  }
  async function apiPost(url, data) {
      const response = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
      });
      return response.json();
  }
  ```
- **Problem:** If response is not valid JSON (e.g., PHP error output, 500 page), `response.json()` throws. No error handling for network failures or non-JSON responses.
- **Fix:**
  ```javascript
  async function apiGet(url) {
      try {
          const response = await fetch(url);
          if (!response.ok) {
              const text = await response.text();
              try { return JSON.parse(text); } catch { return { error: text || 'Request failed' }; }
          }
          return await response.json();
      } catch (err) {
          return { error: 'Network error: ' + err.message };
      }
  }
  async function apiPost(url, data) {
      try {
          const response = await fetch(url, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(data)
          });
          if (!response.ok) {
              const text = await response.text();
              try { return JSON.parse(text); } catch { return { error: text || 'Request failed' }; }
          }
          return await response.json();
      } catch (err) {
          return { error: 'Network error: ' + err.message };
      }
  }
  ```
- **Verification:** Call API with invalid URL - should return error object instead of throwing

### BUG-023: Profile API Does Not Update Session After Profile Change
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\profile\index.php` (POST handler)
- **Priority:** P2 (Medium)
- **Problem:** When user updates their name/email in profile, the session variables (`$_SESSION['user_name']`, `$_SESSION['user_email']`) are NOT updated. The header/sidebar will show stale data until re-login.
- **Fix:** After updating the database, update session:
  ```php
  $_SESSION['user_name'] = $name;
  $_SESSION['user_email'] = $email;
  ```
- **Verification:** Update profile name, check header shows new name without refresh

### BUG-024: Attendance API `sanitize()` on Date String Unnecessary
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\attendance\index.php` (~line 200)
- **Priority:** P3 (Low)
- **Current Code:**
  ```php
  $date = sanitize($_GET['date'] ?? date('Y-m-d'));
  ```
- **Problem:** `sanitize()` runs `htmlspecialchars(strip_tags(trim()))` on a date string. While this doesn't break dates (dates don't contain HTML chars), it's conceptually wrong. Should use date validation.
- **Fix:**
  ```php
  $date = $_GET['date'] ?? date('Y-m-d');
  $d = DateTime::createFromFormat('Y-m-d', $date);
  if (!$d) { $date = date('Y-m-d'); }
  ```
- **Status:** Works correctly but semantically incorrect. Low priority.

### BUG-025: Complaints PUT Handler Casts `assigned_to` to int Without Validation
- **File:** `c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php\api\complaints\index.php` (PUT handler)
- **Priority:** P3 (Low)
- **Current Code:**
  ```php
  db_query(
      "UPDATE complaints SET status=?, assigned_to=?, resolved_at=? WHERE id=?",
      [$status, (int) ($data['assigned_to'] ?? 0), $resolved, $id]
  );
  ```
- **Problem:** If `assigned_to` is empty string, `(int) ''` = 0. Setting `assigned_to = 0` may not match NULL semantics expected by the application.
- **Fix:**
  ```php
  $assignedTo = isset($data['assigned_to']) && $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null;
  ```
- **Status:** Minor, unlikely to cause issues.

---

## SECTION 3: FIX VERIFICATION CHECKLIST

### Priority P0 (Critical) - Must Fix First
- [ ] BUG-001: CSRF Protection on ALL API endpoints - Status: PENDING
- [ ] BUG-002: `calculate_grade()` import verification - Status: PENDING (likely not a real bug)
- [ ] BUG-003: Exam list returns empty in enhanced.php - Status: PENDING
- [ ] BUG-004: Fee enhanced endpoints missing authorization - Status: PENDING
- [ ] BUG-005: Complaints onclick syntax error - Status: PENDING
- [ ] BUG-006: Fee edit discount reset to 0 - Status: PENDING
- [ ] BUG-007: Users API URL missing `/index.php` - Status: PENDING
- [ ] BUG-008: Attendance student_id=0 returns error - Status: PENDING
- [ ] BUG-021: Fee balance_amount not calculated on insert - Status: PENDING

### Priority P1 (High) - Fix Next
- [ ] BUG-009: Fee DELETE null access warning - Status: PENDING
- [ ] BUG-010: Exam delete no error handling - Status: PENDING
- [ ] BUG-016: Fee PUT handler missing fields - Status: PENDING
- [ ] BUG-020: Classes DELETE no FK check - Status: PENDING
- [ ] BUG-022: apiGet/apiPost no HTTP error handling - Status: PENDING
- [ ] BUG-023: Profile session not updated after change - Status: PENDING

### Priority P2 (Medium) - Fix After High Priority
- [ ] BUG-011: Profile password change endpoint separation - Status: PENDING
- [ ] BUG-012: Hostel NaN comparison - Status: PENDING
- [ ] BUG-013: Canteen label mismatch - Status: PENDING
- [ ] BUG-014: Dashboard stats using db_count for SUM - Status: PENDING (works, cosmetic)
- [ ] BUG-015: Notifications query parentheses - Status: PENDING (works, clarity)
- [ ] BUG-017: FormData vs JSON type mismatch - Status: PENDING (works with loose typing)
- [ ] BUG-024: Attendance sanitize on date - Status: PENDING (works, semantic)
- [ ] BUG-025: Complaints assigned_to int cast - Status: PENDING (minor)

### Priority P3 (Low) - Fix Last
- [ ] BUG-018: data.php loads all users unconditionally - Status: PENDING (performance)
- [ ] BUG-019: Logout link is GET request (CSRF bypass) - Status: PENDING (security hardening)

---

## SECTION 4: FIX AGENT INSTRUCTIONS

### General Rules
1. **Fix ALL bugs in order of priority** (P0 first, then P1, P2, P3)
2. **After each fix, document what was changed** in this report
3. **DO NOT break any existing working functionality** - test each change
4. **Follow the exact code style** of the project (indentation, naming conventions)
5. **Do not introduce new bugs** - test that the fix doesn't break related features
6. **Always use absolute paths** when referencing files

### Code Style Guidelines
- PHP: 4-space indentation, snake_case for functions, camelCase for variables
- JavaScript: 4-space indentation, camelCase for functions/variables
- Use `htmlspecialchars()` for all user-facing output
- Use prepared statements for ALL database queries (already done consistently)
- Use `json_response()` for API responses
- Use `require_auth()` and `require_role()` at top of protected endpoints

### Fix Priority Order
1. **Start with P0 bugs** - These are critical and must be fixed first
2. **Then P1 bugs** - High impact on functionality
3. **Then P2 bugs** - Medium impact, quality improvements
4. **Finally P3 bugs** - Low impact, nice-to-have improvements

### Testing Each Fix
After making a fix:
1. Verify the specific bug is resolved
2. Test related functionality still works
3. Check for JavaScript console errors in browser
4. Check for PHP errors in logs (if debug mode enabled)
5. Test with different user roles (admin, teacher, student, parent)

### Special Instructions for BUG-001 (CSRF)
This is the largest fix affecting 65+ files. Recommended approach:
1. First update `assets/js/main.js` to include CSRF token in apiPost/apiDelete
2. Add CSRF meta tag to all frontend PHP pages
3. Add CSRF verification to each API endpoint
4. Test thoroughly that all forms still work

### Special Instructions for BUG-007 (Users API URL)
This is a straightforward find-and-replace in `users.php`:
- Replace `/api/users` with `/api/users/index.php` in all fetch/apiGet/apiPost calls
- There should be ~5 occurrences

### Special Instructions for BUG-008 (Attendance student_id=0)
The fix should be in the API backend (`api/attendance/index.php`), not the frontend. Add auto-resolution of student ID from session when the user is a student/parent.

---

## SECTION 5: PRODUCTION READINESS CRITERIA

### What "Production-Ready" Means for This Project

#### Code Quality
- [x] All database queries use prepared statements (already done)
- [x] Password hashing with bcrypt (already done)
- [x] Session regeneration on login (already done)
- [ ] CSRF protection on all API endpoints (BUG-001)
- [ ] All API endpoints return consistent JSON format
- [ ] No PHP warnings/notices in error logs
- [ ] No JavaScript console errors
- [ ] Input validation on all user inputs
- [ ] Output escaping on all user-facing data

#### Functionality
- [ ] All buttons work (tested across all pages)
- [ ] All forms submit correctly and show success/error feedback
- [ ] All list views load with pagination
- [ ] Search and filter functionality works on all modules
- [ ] Create/Read/Update/Delete works for all entities
- [ ] Export functionality works (CSV, PDF)
- [ ] Role-based access control works correctly

#### Security
- [ ] CSRF protection enabled (BUG-001)
- [ ] SQL injection prevented (already done via prepared statements)
- [ ] XSS prevented (htmlspecialchars on output)
- [ ] Session cookies secure (check `.env.php` SESSION_COOKIE_SECURE)
- [ ] Rate limiting enabled on auth endpoints (already done)
- [ ] Account lockout after failed attempts (already done)
- [ ] Password reset tokens with expiry (already done)
- [ ] File upload validation
- [ ] Authorization checks on sensitive endpoints (BUG-004)

#### Performance
- [ ] Database indexes on frequently queried columns
- [ ] No N+1 query patterns
- [ ] Lazy loading for large datasets
- [ ] Pagination on all list endpoints
- [ ] Query result caching for static data

#### Responsive Design
- [ ] Sidebar collapses on mobile
- [ ] Tables are scrollable on small screens
- [ ] Forms stack vertically on mobile
- [ ] Modal dialogs fit on small screens
- [ ] All buttons are tappable on mobile

#### Error Handling
- [ ] All API endpoints return proper error codes (400, 401, 403, 404, 500)
- [ ] Frontend shows user-friendly error messages
- [ ] Network failures are handled gracefully
- [ ] Form validation shows inline errors
- [ ] Empty states display when no data exists

### Pre-Production Checklist
- [ ] All P0 bugs fixed and verified
- [ ] All P1 bugs fixed and verified
- [ ] All P2 bugs fixed and verified
- [ ] P3 bugs reviewed and prioritized
- [ ] Database backup/restore tested
- [ ] Environment variables configured for production
- [ ] APP_DEBUG set to false
- [ ] SESSION_COOKIE_SECURE set to true (for HTTPS)
- [ ] Error logging configured
- [ ] Audit logging working correctly
- [ ] All roles tested (superadmin, admin, teacher, student, parent, accounts, librarian, hr, canteen, driver, conductor, staff)
- [ ] Cross-browser testing (Chrome, Firefox, Safari, Edge)
- [ ] Mobile responsiveness verified
- [ ] Load testing with realistic user counts

---

## END OF REPORT

This document is the **single source of truth** for all team members. All fixes should be documented here. Any new bugs discovered during fixing should be added to Section 2.
