# Smoke Test Checklist
**Date:** 7 April 2026  
**Purpose:** Quick verification after each phase.

## Preconditions
- `server/.env` has `DATABASE_URL`, `JWT_SECRET`, `SEED_SUPERADMIN_PASSWORD`
- Database is reachable
- `server` and `client` dependencies installed

## Backend (Critical)
1. `GET /api/health` returns `200` and database is `connected`
2. `POST /api/auth/login` works for a known admin account
3. `GET /api/dashboard/stats` returns `200` for admin
4. `GET /api/students` returns `200` and a paginated response (after Phase 4)
5. `GET /api/attendance/report/monthly` returns `200` for admin/teacher
6. `GET /api/fee/structures` returns `200` for admin/accounts
7. `GET /api/library/dashboard` returns `200`
8. `GET /api/transport` returns `200`
9. `GET /api/notices` returns `200`
10. `GET /api/chatbot/history` returns `200` (authenticated)

## Frontend (Critical)
1. Login page loads and can sign in
2. Dashboard loads without console errors
3. Student list loads
4. Attendance page loads and can select class/date
5. Fee page loads and lists structures
6. Library page loads and shows summary cards

## Notes
- Track failures with endpoint, role, and error message.
