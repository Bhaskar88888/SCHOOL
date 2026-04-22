# School ERP PHP Post-Fix Runtime Report

Date: 2026-04-17

This report supersedes the earlier blocker-focused runtime report in cases where the project was changed after that report was written.

## What Was Rechecked

- Fresh-install path using disposable MariaDB plus PHP built-in server
- Login flow through the real `index.php` form
- Public page checks
- Protected page route smoke pass
- Authenticated API sweep from the latest `php-server.stderr.log`

## Items Confirmed Fixed

### 1. Login CSRF bootstrap is fixed

- [index.php](./index.php) now loads `includes/csrf.php`.
- Latest runtime sequence showed:
  - `GET /index.php -> 200`
  - `POST /index.php -> 302`
  - `GET /dashboard.php -> 200`

This means the entry login flow is no longer blocked by `Class "CSRFProtection" not found`.

### 2. Privacy page is fixed

- Latest runtime check showed `GET /privacy-policy.php -> 200`.
- The public privacy page no longer fatals during render.

### 3. `.env.php` exposure on the built-in server is fixed

- Latest runtime check showed `GET /.env.php -> 403`.
- The config file is no longer directly exposed in the built-in server smoke pass.

### 4. Fresh-install schema blockers were addressed

The install/migration path was expanded so the clean database can move much further than before. The updated schema work included:

- missing module tables in [setup.sql](./setup.sql)
- missing upgrade bootstrap tables in [setup_complete.sql](./setup_complete.sql)
- messaging schema alignment in [patch_schema.sql](./patch_schema.sql)
- corrected default superadmin seed password hash in [setup.sql](./setup.sql)

### 5. `send_sms()` redeclare fatal was fixed

- The duplicate helper definition was removed as a runtime blocker by updating [includes/sms_service.php](./includes/sms_service.php).

## Latest Protected Page Smoke Pass

These top-level routes returned `200` in the latest post-fix run:

- `archive.php`
- `attendance.php`
- `audit.php`
- `canteen.php`
- `chatbot.php`
- `classes.php`
- `communication.php`
- `dashboard.php`
- `exams.php`
- `export.php`
- `fee.php`
- `forgot_password.php`
- `homework.php`
- `hostel.php`
- `hr.php`
- `import_data.php`
- `leave.php`
- `library.php`
- `messages.php`
- `notices.php`
- `notifications.php`
- `payroll.php`
- `privacy-policy.php`
- `profile.php`
- `remarks.php`
- `reset_password.php`
- `routine.php`
- `salary-setup.php`
- `staff-attendance.php`
- `students.php`
- `transport.php`
- `users.php`

Special cases from the same pass:

- [`.env.php`](./.env.php) returned `403`
- `complaints.php` returned `302` to `communication.php`

## Remaining Confirmed Runtime Defects

### 1. Hostel API still does not match the hostel schema

Confirmed fatal from latest runtime log:

- [tmp/runtime_audit/logs/php-server.stderr.log](./tmp/runtime_audit/logs/php-server.stderr.log)

Failing code:

- [api/hostel/index.php:14](./api/hostel/index.php)
- [api/hostel/index.php:17](./api/hostel/index.php)
- [api/hostel/index.php:26](./api/hostel/index.php)

Problem:

- the code expects columns such as `room_number`, `block`, `type`, `monthly_fee`, and `check_in_date`
- the current installer schema uses different hostel column names such as `room_no` and `allocated_date`

Observed runtime error:

- `Unknown column 'r.room_number' in 'order clause'`

Impact:

- hostel API reads are still broken on a fresh install
- some hostel create/allocation flows are also at risk because the insert/update code expects a different column layout than the schema provides

### 2. Notices API has an ambiguous `is_active` filter

Confirmed fatal from latest runtime log:

- [tmp/runtime_audit/logs/php-server.stderr.log](./tmp/runtime_audit/logs/php-server.stderr.log)

Failing code:

- [api/notices/index.php:23](./api/notices/index.php)
- [api/notices/index.php:27](./api/notices/index.php)

Problem:

- `$whereExtra` appends `AND is_active = 1`
- the query also joins `users`
- the unqualified `is_active` becomes ambiguous

Observed runtime error:

- `Column 'is_active' in where clause is ambiguous`

Impact:

- notices listing can fatal during authenticated API usage

### 3. `api/auth/create-staff.php` is not robust to missing or non-JSON payloads

Confirmed warnings from latest runtime log:

- [tmp/runtime_audit/logs/php-server.stderr.log](./tmp/runtime_audit/logs/php-server.stderr.log)

Failing code:

- [api/auth/create-staff.php:21](./api/auth/create-staff.php)
- [api/auth/create-staff.php:22](./api/auth/create-staff.php)
- [includes/validator.php:146](./includes/validator.php)

Problem:

- the endpoint reads `$data = get_post_json()`
- it then dereferences `$data['email']` and `$data['password']` directly
- when the request body is missing or not JSON, PHP warnings are emitted before a clean validation error response is returned

Observed runtime warnings:

- `Undefined array key "email"`
- `Undefined array key "password"`
- `strlen(): Passing null to parameter #1 ($string) of type string is deprecated`

Impact:

- malformed requests are not handled cleanly
- this is a robustness bug even if the intended frontend sends JSON

## API Sweep Notes

The latest authenticated API sweep produced useful evidence, but it is still not a final per-endpoint certification report.

Important interpretation notes:

- `GET /api/health.php -> 200` was confirmed repeatedly
- `POST /index.php -> 302` and authenticated `GET /dashboard.php -> 200` were confirmed
- several `405` results are normal because the harness intentionally probed some POST-only endpoints with `GET`

Examples of expected `405` responses rather than bugs:

- `api/auth/change-password.php`
- `api/auth/create-staff.php` on `GET`
- `api/chatbot/chat.php` on `GET`
- `api/fee/notify.php` on `GET`
- `api/import/index.php` on `GET`
- `api/notifications/mark_all_read.php` on `GET`

## Current Overall Status

The core blocker set from the earlier report was materially improved:

- install path is much healthier
- login works again
- privacy page works again
- `.env.php` is no longer exposed in the built-in-server audit
- the SMS helper redeclare fatal was removed

But the project is not yet clean. The latest collected runtime data still shows at least these real module defects:

- hostel schema/code mismatch
- notices API ambiguous SQL filter
- create-staff validation/request-shape robustness issue

## Raw Evidence

- Updated runtime log: [tmp/runtime_audit/logs/php-server.stderr.log](./tmp/runtime_audit/logs/php-server.stderr.log)
- Earlier pre-fix report: [MODULE_UI_RUNTIME_REPORT_2026_04_17.md](./MODULE_UI_RUNTIME_REPORT_20  26_04_17.md)
- Earlier code audit: [CODE_AUDIT_REPORT_2026_04_17.md](./CODE_AUDIT_REPORT_2026_04_17.md)
