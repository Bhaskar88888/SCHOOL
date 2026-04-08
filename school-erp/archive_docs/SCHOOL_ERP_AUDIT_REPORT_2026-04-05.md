# School ERP Audit Report

Date: 2026-04-05
Project: `school-erp`

## Executive Summary

- The main project is operational.
- `client` production build passes.
- `client` test suite passes: 3 suites, 5 tests.
- `server` health, login, and major API route checks pass.
- A real backend integration suite has been added and now runs through `npm test`.

## What Was Verified

### Backend

- `server/npm test` passes `7/7` integration checks.
- Verified login for seeded test users.
- Verified major admin endpoints return `200`:
  - `/api/dashboard/stats`
  - `/api/dashboard/quick-actions`
  - `/api/classes`
  - `/api/classes/stats/summary`
  - `/api/classes/teachers/list`
  - `/api/students`
  - `/api/attendance/report/monthly`
  - `/api/fee/structures`
  - `/api/exams/schedule`
  - `/api/notices`
  - `/api/library/dashboard`
  - `/api/hostel/dashboard`
  - `/api/transport`
  - `/api/bus-routes`
  - `/api/complaints`
  - `/api/homework`
  - `/api/remarks`
  - `/api/archive/students`
  - `/api/notifications`
- Verified student-facing routes also work:
  - `/api/dashboard/stats`
  - `/api/homework/my`
  - `/api/transport`
- Verified transport mutation flow:
  - create bus
  - update bus
  - delete bus

### Frontend

- `client/npm run build` passes.
- `client/npm test -- --watchAll=false` passes.
- Existing frontend test coverage still passes after the API-layer fix.

## Issues Found And Fixed

### 1. Transport access control was too broad

Status: Fixed

Problem:
- Any authenticated user could read bus attendance and student transport history for records outside their ownership.
- Drivers and conductors could also mark attendance on buses not assigned to them.

Fix:
- Added bus-level and student-level access checks in [transport.js](./server/routes/transport.js).
- Restricted attendance reads in [transport.js](./server/routes/transport.js#L204).
- Restricted history reads in [transport.js](./server/routes/transport.js#L220).
- Restricted attendance writes for driver/conductor to assigned buses and assigned students in [transport.js](./server/routes/transport.js#L152).

Verification:
- Added integration checks proving students and parents now receive `403` for unrelated transport records.

### 2. Transport attendance audit trail was silently lost

Status: Fixed

Problem:
- The transport route wrote `markedBy`, but the schema did not define that field.
- Result: the marker user was not actually stored.

Fix:
- Added `markedBy` to [TransportAttendance.js](./server/models/TransportAttendance.js#L3).

Verification:
- Added an integration check that marks attendance and asserts `markedBy` is persisted.

### 3. Client notification helpers pointed to non-existent endpoints

Status: Fixed

Problem:
- The client API layer used `/api/dashboard/notifications` and `/api/dashboard/notifications/read`.
- The real server routes are under `/api/notifications`.

Fix:
- Corrected the client helpers in [api.js](./client/src/api/api.js#L243).

Verification:
- Confirmed `/api/dashboard/notifications` returns `404`.
- Confirmed `/api/notifications`, `/api/notifications/read-all`, and `/api/notifications/unread-count` return `200`.

## Remaining Risks

### 1. Several major endpoints are returning very large unpaginated payloads

Status: Open

Evidence from live verification:
- `/api/students` returned about `7.9 MB`
- `/api/library/dashboard` returned about `10.3 MB`
- `/api/attendance/report/monthly` returned about `1.1 MB` and took about `5.2s`

Likely source locations:
- Student directory returns the full matched dataset in [student.js](./server/routes/student.js#L273)
- Library dashboard returns full books and transactions lists in [library.js](./server/routes/library.js#L19)
- Monthly attendance report loops through every student and counts attendance per student in [attendance.js](./server/routes/attendance.js#L373)

Risk:
- Slow dashboards
- Large browser memory usage
- Expensive mobile/API usage
- Poor scaling as the database grows

Recommendation:
- Add pagination and response limits to large list endpoints.
- Pre-aggregate expensive report data where possible.
- Return summary payloads by default and fetch detail on demand.

### 2. Backend tests currently use the live local development database

Status: Open

Details:
- The new suite creates and removes isolated `apitest.*` fixtures, but it still targets the configured MongoDB instance from `.env`.

Risk:
- Slower local runs
- Potential fixture overlap if multiple runs happen at once

Recommendation:
- Introduce `MONGODB_URI_TEST` or a dedicated disposable test database.

### 3. Mongoose deprecation warnings still appear in the codebase

Status: Open

Details:
- Test runs still log Mongoose warnings for `findOneAndUpdate(..., { new: true })`.
- This is not breaking current behavior, but it should be cleaned up.

Recommendation:
- Replace deprecated `new: true` patterns with `returnDocument: 'after'` in route/model helpers incrementally.

## New Test Coverage Added

- Added backend integration suite at [api.integration.test.js](./server/tests/api.integration.test.js#L1)
- Wired backend test script in [package.json](./server/package.json#L6)
- Refactored server bootstrap to support test startup without shell-only orchestration in [server.js](./server/server.js#L86)
- Hardened DB connect reuse for test/runtime startup in [db.js](./server/config/db.js#L27)

## Files Changed During Audit

- [transport.js](./server/routes/transport.js)
- [TransportAttendance.js](./server/models/TransportAttendance.js)
- [api.js](./client/src/api/api.js)
- [server.js](./server/server.js)
- [db.js](./server/config/db.js)
- [package.json](./server/package.json)
- [api.integration.test.js](./server/tests/api.integration.test.js)

## Final Status

- Main project runs correctly.
- Backend now has automated integration coverage.
- The most important confirmed bugs found during this audit were fixed.
- The main follow-up work is performance hardening for large list/report endpoints and moving tests onto an isolated database.
