# School ERP PHP Runtime / Module Report

Date: 2026-04-17

## Scope

- PHP syntax lint across project PHP files except temporary harness leftovers: `147/147` passed.
- Fresh-install schema review from [setup.sql](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup.sql:12) and migration review from [setup_complete.sql](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:281).
- Runtime smoke pass using a disposable MariaDB instance plus PHP built-in server.
- UI route smoke pass from [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:1).

## Confirmed Findings

### 1. Critical: fresh install schema is incomplete and the upgrade script breaks mid-run

[setup.sql](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup.sql:12) only creates five core tables:

- `users`
- `classes`
- `students`
- `attendance`
- `fees`

But [setup_complete.sql](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:281) immediately starts altering or renaming tables that were never created in the base install:

- [exam_results](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:281)
- [hostel_rooms](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:286)
- [hostel_allocations](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:292)
- [transport_vehicles](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:299)
- [bus_routes](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:306)
- [canteen_orders -> canteen_sales](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:314)
- [leave_applications](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:323)
- [payroll](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:328)
- [library_books](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:336)
- [library_issues](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:340)
- [notices](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:344)
- [complaints](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:351)
- [homework](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:360)
- [routine](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/setup_complete.sql:364)

Result: a clean setup cannot reach a consistent schema for exams, hostel, transport, canteen, leave, payroll, library, complaints, homework, or routine.

### 2. Critical: login page is broken by a missing CSRF include

[index.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/index.php:17) calls `CSRFProtection::verifyToken()` and [index.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/index.php:247) renders `CSRFProtection::field()`, but the file never loads `includes/csrf.php`.

Confirmed at runtime in [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:6):

- `Class "CSRFProtection" not found`

Impact: normal login is broken at the entry page.

### 3. High: public privacy page is broken

[privacy-policy.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/privacy-policy.php:100) requires `includes/footer.php`, but that file does not exist.

Confirmed by:

- missing file reference at [privacy-policy.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/privacy-policy.php:100)
- runtime fatal in [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:27)

This page also reuses the authenticated header. [includes/header.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/includes/header.php:36) and [includes/header.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/includes/header.php:37) assume `get_authenticated_user()` returned data, which produced warnings on the public privacy page before the fatal footer include.

### 4. High: `send_sms()` is defined twice and crashes SMS-related modules

The project defines `send_sms()` in both:

- [includes/helpers.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/includes/helpers.php:140)
- [includes/sms_service.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/includes/sms_service.php:176)

`auth.php` already loads helpers, and several modules also load `sms_service.php`, for example:

- [api/attendance/index.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/api/attendance/index.php:3)
- [api/leave/enhanced.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/api/leave/enhanced.php:8)
- [api/transport/enhanced.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/api/transport/enhanced.php:8)
- [api/fee/enhanced.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/api/fee/enhanced.php:9)

Confirmed runtime fatals:

- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:136)
- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:222)
- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:256)
- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:335)
- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:360)

Impact: attendance and at least some leave/transport enhanced flows crash before handling requests.

### 5. High: sensitive config is web-accessible on the PHP built-in server

The smoke pass logged a direct successful request to [`.env.php`](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/.env.php:1):

- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:37)

The Apache configs try to block dot-env files in [.htaccess](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/.htaccess:33) and [.htaccess.xampp](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/.htaccess.xampp:22), but the file still lives inside the web root. That means:

- PHP built-in server exposes secrets directly.
- Any deployment that misses or bypasses `.htaccess` protections exposes DB credentials and secrets.

### 6. Medium: public page/header composition is not separated from authenticated UI

[includes/header.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/includes/header.php:6) assumes an authenticated user object and immediately dereferences it at lines [36](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/includes/header.php:36) and [37](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/includes/header.php:37).

Confirmed warnings:

- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:22)
- [php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:24)

Impact: any public page that reuses this header will emit warnings or break unless it first fakes an authenticated user context.

## UI Smoke Pass Summary

These top-level pages returned `200` in the runtime smoke pass without a fatal error in the server log:

- [attendance.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/attendance.php:1)
- [chatbot.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/chatbot.php:1)
- [dashboard.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/dashboard.php:1)
- [fee.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/fee.php:1)
- [hostel.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/hostel.php:1)
- [library.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/library.php:1)
- [notifications.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/notifications.php:1)
- [payroll.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/payroll.php:1)
- [students.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/students.php:1)
- [transport.php](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/transport.php:1)

Also reached with `200` during the smoke pass:

- `archive.php`
- `canteen.php`
- `classes.php`
- `communication.php`
- `exams.php`
- `homework.php`
- `hr.php`
- `leave.php`
- `messages.php`
- `notices.php`
- `profile.php`
- `remarks.php`
- `routine.php`
- `salary-setup.php`
- `staff-attendance.php`
- `users.php`

## Limits

- The API sweep was interrupted before the final JSON summary was written.
- The authenticated API pass itself needs one more rerun with the session bound to `127.0.0.1` instead of `localhost` to remove a tooling-side cookie mismatch from the audit harness.
- The defects listed above are still confirmed project defects because they came from direct runtime errors and source inspection, not from the interrupted part of the run.

## Raw Evidence

- Runtime log: [tmp/runtime_audit/logs/php-server.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/php-server.stderr.log:1)
- Temp DB log: [tmp/runtime_audit/logs/mysqld.stderr.log](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_audit/logs/mysqld.stderr.log:1)
- Audit harness: [tmp/runtime_smoke.ps1](/c:/Users/Bhaskar%20Tiwari/Desktop/SCHOOL/school-erp-php/tmp/runtime_smoke.ps1:1)
