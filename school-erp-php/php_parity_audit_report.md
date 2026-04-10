# PHP Migration Parity Audit

Date: 2026-04-10

Scope:
- Compared the current PHP app in `school-erp-php` against the Node.js app in `school-erp`.
- Focused on module parity, route parity, and PHP-only breakage.
- Audit reflects the current workspace state.

## Executive Summary

The PHP project is no longer a thin prototype. Most major modules now have pages and API directories, including users, archive, import/export, PDF, chatbot, salary setup, and staff attendance.

The main problem is no longer "missing files". It is "mismatch between claimed features and what will actually run".

The highest-risk issues are:

1. The PHP code assumes a much richer database schema than `setup.sql` creates.
2. The new admin pages (`users.php`, `archive.php`, `import_data.php`) are structurally broken.
3. The "PDF" endpoints return printable HTML downloads, not PDF files.
4. Archive and audit are still materially below Node parity.
5. Tally and import/export are only partial parity matches.

## High-Severity Findings

### 1. Schema drift will break multiple PHP modules at runtime

The base schema in `setup.sql` defines only the old/simple tables:
- `users` starts at `setup.sql:9`
- `students` starts at `setup.sql:33`
- `attendance` starts at `setup.sql:53`
- `fees` starts at `setup.sql:67`
- `exams` starts at `setup.sql:87`
- `notifications` starts at `setup.sql:314`
- `audit_logs` starts at `setup.sql:324`

But the PHP code now expects many columns and tables that are not created there:

- `api/users/index.php:23,32,46,161` expects `PAGINATION_DEFAULT`, `employee_id`, `department`, `designation`.
- `api/import/index.php:132,225` inserts `students.parent_phone` and `users.employee_id/department/designation`.
- `api/attendance/index.php:42,78,103` expects `students.parent_phone`, `attendance.subject`, and `attendance.sms_sent`.
- `api/archive/index.php:19,36,55,73,93,131` expects `archived_students`, `archived_staff`, `fees.is_active`, and `exams.is_archived`.
- `api/chatbot/analytics.php:15-59` expects `chatbot_logs`.

Impact:
- Fresh installs from `setup.sql` will fail on users, imports, archive, chatbot analytics/history, attendance SMS flow, and several enhanced modules.
- Even if some modules load, inserts/queries will fail as soon as they touch the newer fields.

### 2. The new admin pages are not wired like the rest of the PHP app

`users.php`, `archive.php`, and `import_data.php` include `header.php` and `sidebar.php` directly at the top, but they do not render a full page shell like working pages such as `dashboard.php`.

Examples:
- `users.php:13-14` includes header/sidebar directly
- `archive.php:13-14` includes header/sidebar directly
- `import_data.php:13-14` includes header/sidebar directly

They also include a footer file that does not exist:
- `users.php:303`
- `archive.php:193`
- `import_data.php:353`

They also rely on JavaScript helpers that are not defined in the browser:
- `users.php:174` calls `role_label(user.role)` in JavaScript
- `archive.php:113` calls `role_label(r.role)` in JavaScript

And they do not load the normal shared JavaScript shell used by the working pages (`assets/js/main.js`).

Impact:
- These pages are likely to render incorrectly or fatal on include.
- Even if they render, the table code will hit undefined browser functions.

### 3. The "PDF" feature is still HTML export, not PDF generation

`api/pdf/generate.php` serves HTML with `.html` filenames, not PDF bytes:

- `api/pdf/generate.php:140-141` fee receipt is `text/html` and downloads `receipt_*.html`
- `api/pdf/generate.php:247,273` payslip is HTML with a print button
- `api/pdf/generate.php:346-347` report card is `text/html` and downloads `report_card_*.html`
- `api/pdf/generate.php:476-477` transfer certificate is `text/html`

Impact:
- This is not parity with the Node backend, which returns actual PDF responses for receipts and related exports.
- Any UI/tests expecting a PDF MIME type or `.pdf` download will fail.

### 4. Archive is functionally incomplete and currently depends on nonexistent storage

The PHP archive page is much narrower than the Node page:
- PHP page only exposes students/staff/fees/exams tabs at `archive.php:25-28`
- Node page includes old attendance as well at `ArchivePage.jsx:128-132`
- Node page also exposes export and detail modal behavior at `ArchivePage.jsx:106,217`

The PHP backend then hard-depends on archive tables that are not in the schema:
- `api/archive/index.php:19,36-37` uses `archived_students`
- `api/archive/index.php:55,73-74` uses `archived_staff`

It also uses soft-archive flags not present in the base schema:
- `api/archive/index.php:93` uses `fees.is_active`
- `api/archive/index.php:131` uses `exams.is_archived`

Impact:
- Archive will not work on a fresh install.
- Even if the page loads, it is not yet at Node parity.

### 5. Audit is still effectively a placeholder

Node has a real audit surface:
- UI fetches logs with filters and pagination in `AuditLogPage.jsx:15-42`
- Backend serves filtered audit data in `server/routes/audit.js:9-45`

The PHP page is still static:
- `audit.php:21` renders the heading
- `audit.php:28` hardcodes "No logs recorded yet."

There is no matching `api/audit` namespace in the PHP app.

Impact:
- Audit logging cannot currently be reviewed in the PHP app the way it can in Node.
- This is both a parity gap and an operational visibility gap.

## Medium-Severity Findings

### 6. Import claims Excel support but only parses CSV

The UI advertises CSV/Excel import:
- `import_data.php:3`
- `import_data.php:38`

The API accepts Excel MIME types:
- `api/import/index.php:35-36`

But the implementation only uses `fgetcsv()`:
- `api/import/index.php:80,85`
- `api/import/index.php:173,178`
- `api/import/index.php:264,269`

Impact:
- `.xlsx` and `.xls` uploads are accepted at the edge but not actually parsed.
- This will look like a supported feature and then fail in use.

### 7. Import data page is still below Node parity

Node import flow supports:
- template download
- upload preview
- student default password input
- results breakdown

References:
- `ImportDataPage.jsx:15-16`
- `ImportDataPage.jsx:176-184`
- `ImportDataPage.jsx:226-241`
- `ImportDataPage.jsx:246-249`

PHP currently has:
- template download at `import_data.php:38`
- one-step upload/import at `import_data.php:55,197-210`
- results summary at `import_data.php:61`

Missing versus Node:
- preview table
- separate upload/preview/import flow
- default password handling for student imports
- richer per-row success/failure feedback

### 8. Users page is narrower than Node and misses the ID card flow

Node users page supports ID card printing with QR:
- `UsersPage.jsx:6`
- `UsersPage.jsx:176`
- `UsersPage.jsx:215-265`

PHP users page provides create/edit/delete/search, but no ID card or QR flow.

Impact:
- This is not a blocker, but it is a clear parity gap against the existing product.

### 9. Tally parity is only partial

Node exposes a dedicated route namespace:
- `server/routes/tally.js:8`
- `server/routes/tally.js:59`
- `server/routes/tally.js:105`

PHP currently exposes Tally under `api/export/tally.php` with an `action` query:
- `api/export/tally.php:14-17`
- `api/export/tally.php:22-45`

Missing or mismatched versus Node:
- no dedicated `/api/tally/export-fees`
- no dedicated `/api/tally/export-payroll`
- no `/api/tally/vouchers` endpoint

Impact:
- Feature intent exists, but the route contract does not match the Node app.
- Any parity testing against Node endpoints will fail.

### 10. Config/bootstrap is duplicated and fragile

Database constants are defined twice:
- `includes/db.php:7-10`
- `config/env.php:13-16`

Also, `api/archive/index.php:14` uses `PAGINATION_DEFAULT` without including `config/env.php`.

Impact:
- Files that include both `db.php` and `config/env.php` risk constant redefinition warnings.
- Archive can fatal on undefined constants depending on error settings.

## Missing or Narrower-Than-Node Areas

These are the main parity gaps still visible after the current PHP expansion:

- Audit UI/API parity is not done.
- Archive is narrower than Node and uses storage that is not provisioned by `setup.sql`.
- Import is narrower than Node and not true Excel import.
- Users page is missing the ID card / QR workflow.
- Tally route contract is not aligned with Node.
- PDF generation is still HTML-print export, not true PDF output.
- Node has a dedicated chatbot `/languages` endpoint; PHP currently exposes `bootstrap`, `chat`, `history`, `analytics`, but not the separate languages endpoint.

## Verification Limits

- I could not run PHP lint locally because `php` CLI is not installed in this environment.
- I did not execute the app against the live database, so this report is based on static comparison and schema/API inspection.

## Recommended Fix Order

1. Bring `setup.sql` in sync with what the PHP code now expects.
2. Repair `users.php`, `archive.php`, and `import_data.php` so they use the same shell structure as the working pages.
3. Decide whether PDF is real PDF or browser-print HTML, then make the route behavior consistent.
4. Rework archive to derive from existing data or create and populate archive tables properly.
5. Add a real audit API/UI path.
6. Align Tally and import/export route contracts with the Node app.
