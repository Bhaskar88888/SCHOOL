# Prisma Migration Missing Features Report
**Date:** 7 April 2026  
**Scope:** Features that existed in Mongoose models but are not fully re-implemented after Prisma migration.

This report focuses on **functionality lost by deletions** during migration, not on new Prisma features. Items are grouped by confidence.

---

## ✅ Confirmed Missing (Not Re-Implemented)

1) **Attendance date normalization at model level**
   - Mongoose pre-validate normalized `date` to UTC day and converted empty `subject` to `null`.
   - Now only enforced in attendance routes, not on all inserts/updates (e.g., scripts/tests/any future code using adapters).
   - Risk: duplicate attendance rows or mixed date granularity if created outside routes.

2) **Complaint type enum validation**
   - Mongoose restricted `Complaint.type` to a fixed list (e.g., `teacher_to_parent`, `driver_to_admin`, etc.).
   - Prisma middleware validates only `status`, `raisedByRole`, `assignedToRole`.
   - Risk: invalid complaint type values stored.

3) **User employmentType enum validation**
   - Mongoose enforced enum (`permanent`, `contractual`, `part-time`, `visiting`, `''`).
   - Prisma middleware does not validate `employmentType`.
   - Risk: inconsistent HR data.

4) **HostelFeeStructure billingCycle enum validation**
   - Mongoose enforced (`monthly`, `quarterly`, `half-yearly`, `annual`).
   - Prisma middleware does not validate `billingCycle`.
   - Risk: invalid billing cycle values.

5) **ChatbotLog message/response length limits**
   - Mongoose enforced `maxlength` (message 500, response 2000).
   - Prisma does not enforce any length limits.
   - Risk: oversized log payloads and storage bloat.

6) **Password reset fields still selectable unless guarded**
   - Mongoose used `select: false` for `passwordResetTokenHash` and `passwordResetExpiresAt`.
   - Prisma cannot hide by default; we only strip them in `toLegacyUser()`.
   - Risk: any route returning raw Prisma user objects can leak these fields.

---

## ⚠️ Partial / Only Enforced in Routes (Not Global)

1) **Attendance date normalization**
   - Enforced in `server/routes/attendance.js`, but not in adapters/middleware.
   - Any non-route insert bypasses it.

2) **Password hashing**
   - Prisma middleware now hashes `User.password`, but if a raw Prisma client is used directly outside the shared `config/prisma.js`, hashing is bypassed.
   - All code should import the shared Prisma client.

3) **FeePayment normalization**
   - Restored via Prisma middleware, but only when writes go through `config/prisma.js`.

---

## ✅ Already Re-Implemented (For Reference)

These were deleted but have already been restored in Prisma:
- Password hashing (via Prisma middleware).
- Payroll auto-calculation (total earnings/deductions/net pay).
- FeePayment normalization (date/paymentDate/originalAmount sync).
- TTL cleanup for AuditLog and ChatbotLog (via scheduler job).
- Key indexes for AuditLog, ChatbotLog, FeePayment.
- Enum validation for most operational status fields.

---

## Recommended Next Actions

1) Add enum validation for `Complaint.type`, `User.employmentType`, and `HostelFeeStructure.billingCycle`.
2) Enforce ChatbotLog message/response length limits (middleware-level validation).
3) Add Attendance normalization to Prisma middleware (global).
4) Ensure **all** code paths import Prisma from `server/config/prisma.js` only.

