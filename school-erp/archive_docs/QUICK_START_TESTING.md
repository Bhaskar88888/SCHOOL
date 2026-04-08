# 🚀 Quick Start - Comprehensive Testing

## Run All Tests (Recommended)

### Option 1: Using Batch File (Windows)
```bash
# Double-click this file or run from command line:
run-all-tests.bat
```

### Option 2: Using NPM Scripts
```bash
# From school-erp/server directory:
npm test
```

### Option 3: Direct Node Command
```bash
# From school-erp root directory:
node server/tests/master-test-runner.js
```

---

## Run Individual Test Suites

### Generate Mock Data Only (10,000+ Records)
```bash
cd server
npm run test:seed
# or
node seed-comprehensive-test-data.js
```

### Run API Tests Only (29+ Modules)
```bash
cd server
npm run test:api
# or
node tests/comprehensive-api-test.js
```

### Run Button/Feature Tests Only
```bash
cd server
npm run test:buttons
# or
node tests/button-feature-test.js
```

### Run E2E UI Tests (Playwright)
```bash
# From school-erp root:
npx playwright install
npx playwright test tests/e2e-comprehensive.spec.js
```

---

## What You'll Get

After running the complete test suite:

### 📊 Test Reports
- `final-test-report.json` - Complete detailed report
- `final-test-report.txt` - Human-readable summary
- `test-results.json` - API test results
- `button-feature-test-report.json` - Feature validation results

### 📈 Statistics
- 10,000+ mock records generated
- 29+ modules tested
- 150+ API tests executed
- 200+ features validated
- Comprehensive pass/fail rates

### 🔐 Test Accounts
All passwords: **test123**

| Role | Email |
|------|-------|
| Superadmin | admin@school.com |
| Teacher | test.teacher.0@school.edu |
| Student | test.student.0@school.edu |
| Parent | test.parent.0@school.edu |

---

## Prerequisites

1. **MySQL Running** - Ensure your MySQL server is running
2. **Dependencies Installed** - Run `npm install` in server directory
3. **Database Migrated** - Run `npx prisma migrate dev`
4. **Server Ready** - Server should be able to start on port 5000

---

## Expected Output

```
================================================================================
  EDUGLASS SCHOOL ERP - COMPREHENSIVE TEST SUITE
  Testing ALL Modules with 10,000+ Data Points
================================================================================

✅ Phase 1 Complete: 100,000+ records generated
✅ Phase 2 Complete: 145/150 tests passed
✅ Phase 3 Complete: All features validated

📊 FINAL SUMMARY:
Total Data Records:  100,000+
Total Tests:         350+
Tests Passed:        340+ ✓
Tests Failed:        5-10 ✗
Overall Pass Rate:   95%+
Modules Tested:      29
Features Validated:  200+

✅ ALL TESTS PASSED!
```

---

## Troubleshooting

**Issue:** "Cannot connect to database"
```bash
# Solution: Check .env file and MySQL
cd server
npx prisma generate
```

**Issue:** "Server not running"
```bash
# Solution: Start server in another terminal
cd server
npm start
```

**Issue:** "Tests failing"
```bash
# Solution: Check specific errors in report
type final-test-report.txt
```

---

## Full Documentation

See `COMPREHENSIVE_TESTING_GUIDE.md` for complete documentation.
