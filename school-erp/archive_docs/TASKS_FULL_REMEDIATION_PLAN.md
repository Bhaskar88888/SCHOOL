# Full Remediation Task Plan
**Date:** 7 April 2026  
**Goal:** Fix all documented migration gaps and bug audits (migration, security, correctness, performance, infra, UI).

This plan is the execution order. I will implement in phases, one phase at a time, and verify after each phase.

---

## Status Update (7 April 2026)
- Phases 0–6 completed.
- Integration tests: `npm test` (server) passing.
- Full E2E audit: 138/138 checks passing.

## Phase 0 — Baseline & Guardrails
1) Lock Prisma as the only ORM entry point (no direct PrismaClient usage).
2) Ensure all routes import `server/config/prisma.js`.
3) Verify `.env` for `DATABASE_URL`, `JWT_SECRET`, `SEED_SUPERADMIN_PASSWORD`.
4) Create quick smoke test checklist (login, health, critical endpoints).

## Phase 1 — Critical Security & Data Integrity
1) Export route authorization (role + ownership checks).
2) Remarks access checks (student/parent/teacher scope).
3) Fix CORS/auth token storage (httpOnly cookie).
4) Fix CSV injection in export.
5) Sanitize audit log sensitive fields.
6) Fix IDORs (wallet access, payslips, library/transport access).
7) Race conditions: RFID payments, hostel allocation, canteen stock.
8) Fix payroll PDF `netPay`/`netSalary`.
9) Fix jsPDF import issue.
10) Fix rate limiter import.

## Phase 2 — ORM Consistency & Migration Gaps
1) Eliminate `_id` vs `id` mismatches in all Prisma queries.
2) Standardize all remaining routes to Prisma.
3) Add remaining enum validations + length guards (if any still missing).
4) Ensure model-level normalization applies globally.

## Phase 3 — Core Feature Correctness
1) Student import: transaction + orphan user cleanup.
2) Fee/attendance/reporting integrity checks.
3) Fix complaint enum types in code paths.
4) Ensure parent/student scoping everywhere.

## Phase 4 — Performance & Scalability
1) Pagination + response caps for all list endpoints.
2) Library dashboard summary vs detail split.
3) Attendance monthly report aggregation rewrite.
4) Add compression and response size guardrails.

## Phase 5 — Frontend Stability
1) Fix API response shape mismatches (pagination changes).
2) Fix missing page routes or delete dead pages.
3) Remove alert() and add consistent toasts.
4) Role-aware UI access matrix enforcement.
5) Loading states for major pages.

## Phase 6 — Infra & Testing
1) Isolate test DB config.
2) Add regression tests for RBAC + export + remarks.
3) Add smoke tests for critical endpoints.
4) Run E2E audit and fix failures.

---

## Execution Notes
- I will implement **one phase at a time**, report progress, and only move on after verification.
- If a change is risky or requires a product decision, I will stop and ask before continuing.
