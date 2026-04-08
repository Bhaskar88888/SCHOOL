# Post‑Migration Bug & Missing Feature Report
**Date:** 7 April 2026  
**Scope:** Backend modules after Prisma/MySQL migration (server).

This report is based on code inspection and migration diffs. Tests were not executed.

---

## 🔴 Blocking / Setup Bugs

1) **Prisma DB push cannot run without `DATABASE_URL`**
   - `npx prisma db push` fails with `P1012: Environment variable not found: DATABASE_URL`.
   - Root cause: `prisma.config.ts` skips env loading, so `.env` is ignored unless `DATABASE_URL` is set.
   - Impact: Schema sync and DB startup are blocked.

---

## 🟠 Migration Risk: Model‑level logic still bypassable

These features were restored in Prisma middleware but can be bypassed if any code uses a raw Prisma client or non‑shared instance:

1) Password hashing
2) Attendance date normalization
3) FeePayment normalization
4) Payroll auto‑calculation
5) Enum validation for critical fields
6) ChatbotLog length limits

**Impact:** Data corruption or security issues if any module creates its own PrismaClient instead of importing `server/config/prisma.js`.

---

## 🟡 Known Module Gaps (Need Verification)

These were previously present in Mongoose but are now **route‑only** or depend on middleware that some modules might bypass:

1) **Attendance date normalization**
   - In routes and middleware; if attendance created by scripts/tests, normalization can still be skipped if not using shared Prisma instance.

2) **Password reset fields exposure**
   - Prisma middleware now strips `passwordResetTokenHash`/`passwordResetExpiresAt` for User results unless explicitly selected.
   - Any module returning raw Prisma user data without using shared client may leak fields.

---

## ✅ Restored / Fixed Items (No Longer Missing)

These migration losses have been re‑implemented:

1) Password hashing (Prisma middleware)
2) Payroll totals calculation (Prisma middleware)
3) FeePayment date/originalAmount normalization (Prisma middleware)
4) TTL cleanup for AuditLog/ChatbotLog (Scheduler)
5) Indexes for AuditLog/ChatbotLog/FeePayment (schema.prisma)
6) Enum validation for major status fields

---

## Recommended Next Actions

1) Set `DATABASE_URL` in `server/.env` and rerun Prisma `db push`.
2) Verify all modules import Prisma from `server/config/prisma.js`.
3) Run full E2E audit script to confirm no runtime regressions.

