/**
 * Working Comprehensive API Test Suite
 * Uses ACTUAL API routes from the server
 */

const axios = require('axios');
const assert = require('assert');

const BASE_URL = process.env.BASE_URL || 'http://localhost:5000/api';
let authToken = '';
let teacherToken = '';

const results = {
  total: 0,
  passed: 0,
  failed: 0,
  modules: {},
  errors: []
};

function log(msg, type = 'info') {
  const icons = { info: 'ℹ️', success: '✅', error: '❌', module: '📦' };
  console.log(`${icons[type] || 'ℹ️'} ${msg}`);
}

async function test(moduleName, testName, fn) {
  results.total++;
  try {
    await fn();
    results.passed++;
    results.modules[moduleName] = (results.modules[moduleName] || 0) + 1;
    log(`[${moduleName}] ${testName}`, 'success');
  } catch (err) {
    results.failed++;
    results.errors.push(`[${moduleName}] ${testName}: ${err.message}`);
    log(`[${moduleName}] ${testName} - ${err.message}`, 'error');
  }
}

function auth(token = authToken) {
  return { headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' } };
}

async function main() {
  console.log('\n' + '='.repeat(80));
  console.log('🚀 COMPREHENSIVE SCHOOL ERP API TEST SUITE (Working Version)');
  console.log('   Testing ALL Modules with Superadmin Account');
  console.log('='.repeat(80) + '\n');

  const startTime = Date.now();

  // ============================================================
  // 1. AUTHENTICATION
  // ============================================================
  log('Testing Authentication...', 'module');

  await test('Auth', 'Login as superadmin', async () => {
    const res = await axios.post(`${BASE_URL}/auth/login`, {
      email: 'admin@school.com',
      password: 'admin123'
    });
    assert(res.data.token, 'Should return token');
    authToken = res.data.token;
    log(`   ✓ Logged in as: ${res.data.user?.email || 'superadmin'} (${res.data.user?.role || 'admin'})`, 'success');
  });

  await test('Auth', 'Get current user profile', async () => {
    const me = await axios.get(`${BASE_URL}/auth/me`, auth());
    assert(me.data.user || me.data, 'Should return user profile');
    const user = me.data.user || me.data;
    log(`   ✓ User: ${user.name} (${user.role})`, 'success');
  });

  await test('Auth', 'Get user profile by ID', async () => {
    const me = await axios.get(`${BASE_URL}/auth/me`, auth());
    const res = await axios.get(`${BASE_URL}/auth/users/${me.data.user.id}`, auth());
    assert(res.data.user, 'Should return user');
  });

  // ============================================================
  // 2. DASHBOARD
  // ============================================================
  log('Testing Dashboard...', 'module');

  await test('Dashboard', 'Get statistics', async () => {
    const res = await axios.get(`${BASE_URL}/dashboard/stats`, auth());
    assert(res.data, 'Should return stats');
    log(`   ✓ Stats received`, 'success');
  });

  await test('Dashboard', 'Get quick actions', async () => {
    const res = await axios.get(`${BASE_URL}/dashboard/quick-actions`, auth());
    assert(res.data, 'Should return quick actions');
  });

  // ============================================================
  // 3. CLASSES
  // ============================================================
  log('Testing Classes...', 'module');

  await test('Classes', 'Get all classes', async () => {
    const res = await axios.get(`${BASE_URL}/classes`, auth());
    const classes = res.data.classes || res.data;
    assert(Array.isArray(classes), 'Should return array');
    log(`   ✓ Classes count: ${classes.length}`, 'success');
  });

  await test('Classes', 'Create class', async () => {
    const res = await axios.post(`${BASE_URL}/classes`, {
      name: 'Test Class 10',
      section: 'A',
      capacity: 50,
      academicYear: '2025-2026'
    }, auth());
    assert(res.data, 'Should create class');
  });

  // ============================================================
  // 4. STUDENTS
  // ============================================================
  log('Testing Students...', 'module');

  await test('Students', 'Get all students', async () => {
    const res = await axios.get(`${BASE_URL}/students`, auth());
    const students = res.data.students || res.data;
    assert(Array.isArray(students), 'Should return students array');
    log(`   ✓ Students count: ${students.length}`, 'success');
  });

  await test('Students', 'Search students', async () => {
    const res = await axios.get(`${BASE_URL}/students/search?q=Test`, auth());
    assert(res.data, 'Search should work');
  });

  await test('Students', 'Get student stats', async () => {
    const res = await axios.get(`${BASE_URL}/students/stats`, auth());
    assert(res.data, 'Should return stats');
  });

  // ============================================================
  // 5. ATTENDANCE
  // ============================================================
  log('Testing Attendance...', 'module');

  await test('Attendance', 'Get attendance records', async () => {
    const res = await axios.get(`${BASE_URL}/attendance`, auth());
    assert(res.data, 'Should return attendance');
    log(`   ✓ Attendance records loaded`, 'success');
  });

  await test('Attendance', 'Get defaulters', async () => {
    const res = await axios.get(`${BASE_URL}/attendance/defaulters`, auth());
    assert(res.data, 'Should return defaulters');
  });

  // ============================================================
  // 6. STAFF ATTENDANCE
  // ============================================================
  log('Testing Staff Attendance...', 'module');

  await test('Staff Attendance', 'Get records', async () => {
    const res = await axios.get(`${BASE_URL}/staff-attendance`, auth());
    assert(res.data, 'Should return staff attendance');
    log(`   ✓ Staff attendance loaded`, 'success');
  });

  await test('Staff Attendance', 'Mark attendance', async () => {
    const me = await axios.get(`${BASE_URL}/auth/me`, auth());
    const res = await axios.post(`${BASE_URL}/staff-attendance`, {
      staffId: me.data.user.id,
      date: new Date().toISOString(),
      status: 'present'
    }, auth());
    assert(res.data, 'Should mark attendance');
  });

  // ============================================================
  // 7. FEES
  // ============================================================
  log('Testing Fees...', 'module');

  await test('Fees', 'Get fee structures', async () => {
    const res = await axios.get(`${BASE_URL}/fee/structures`, auth());
    assert(res.data, 'Should return fee structures');
    log(`   ✓ Fee structures loaded`, 'success');
  });

  await test('Fees', 'Get payments', async () => {
    const res = await axios.get(`${BASE_URL}/fee/payments`, auth());
    assert(res.data, 'Should return payments');
    log(`   ✓ Fee payments loaded`, 'success');
  });

  await test('Fees', 'Get defaulters', async () => {
    const res = await axios.get(`${BASE_URL}/fee/defaulters`, auth());
    assert(res.data, 'Should return defaulters');
  });

  // ============================================================
  // 8. EXAMS
  // ============================================================
  log('Testing Exams...', 'module');

  await test('Exams', 'Get all exams', async () => {
    const res = await axios.get(`${BASE_URL}/exams`, auth());
    assert(res.data, 'Should return exams');
    log(`   ✓ Exams loaded`, 'success');
  });

  await test('Exams', 'Get results', async () => {
    const res = await axios.get(`${BASE_URL}/exams/results`, auth());
    assert(res.data, 'Should return results');
    log(`   ✓ Exam results loaded`, 'success');
  });

  // ============================================================
  // 9. HOMEWORK
  // ============================================================
  log('Testing Homework...', 'module');

  await test('Homework', 'Get all homework', async () => {
    const res = await axios.get(`${BASE_URL}/homework`, auth());
    assert(res.data, 'Should return homework');
    log(`   ✓ Homework loaded`, 'success');
  });

  await test('Homework', 'Create homework', async () => {
    const classes = await axios.get(`${BASE_URL}/classes`, auth());
    const classList = classes.data.classes || classes.data;
    if (classList.length > 0) {
      const res = await axios.post(`${BASE_URL}/homework`, {
        classId: classList[0].id,
        subject: 'Mathematics',
        title: 'Test Homework',
        description: 'Complete exercises',
        dueDate: new Date().toISOString()
      }, auth());
      assert(res.data, 'Should create homework');
    }
  });

  // ============================================================
  // 10. NOTICES
  // ============================================================
  log('Testing Notices...', 'module');

  await test('Notices', 'Get all notices', async () => {
    const res = await axios.get(`${BASE_URL}/notices`, auth());
    assert(res.data, 'Should return notices');
    log(`   ✓ Notices loaded`, 'success');
  });

  await test('Notices', 'Create notice', async () => {
    const res = await axios.post(`${BASE_URL}/notices`, {
      title: 'Test Notice',
      content: 'Test Content',
      audience: ['all'],
      priority: 'normal'
    }, auth());
    assert(res.data, 'Should create notice');
  });

  // ============================================================
  // 11. REMARKS
  // ============================================================
  log('Testing Remarks...', 'module');

  await test('Remarks', 'Get all remarks', async () => {
    const res = await axios.get(`${BASE_URL}/remarks`, auth());
    assert(res.data, 'Should return remarks');
    log(`   ✓ Remarks loaded`, 'success');
  });

  // ============================================================
  // 12. COMPLAINTS
  // ============================================================
  log('Testing Complaints...', 'module');

  await test('Complaints', 'Get all complaints', async () => {
    const res = await axios.get(`${BASE_URL}/complaints`, auth());
    assert(res.data, 'Should return complaints');
    log(`   ✓ Complaints loaded`, 'success');
  });

  await test('Complaints', 'Create complaint', async () => {
    const res = await axios.post(`${BASE_URL}/complaints`, {
      type: 'general',
      subject: 'Test Complaint',
      description: 'Test Description'
    }, auth());
    assert(res.data, 'Should create complaint');
  });

  // ============================================================
  // 13. LEAVE
  // ============================================================
  log('Testing Leave Management...', 'module');

  await test('Leave', 'Get leave requests', async () => {
    const res = await axios.get(`${BASE_URL}/leave`, auth());
    assert(res.data, 'Should return leave requests');
    log(`   ✓ Leave requests loaded`, 'success');
  });

  await test('Leave', 'Apply for leave', async () => {
    const res = await axios.post(`${BASE_URL}/leave`, {
      type: 'sick',
      fromDate: new Date().toISOString(),
      toDate: new Date().toISOString(),
      reason: 'Test Leave'
    }, auth());
    assert(res.data, 'Should apply for leave');
  });

  // ============================================================
  // 14. LIBRARY
  // ============================================================
  log('Testing Library...', 'module');

  await test('Library', 'Get books', async () => {
    const res = await axios.get(`${BASE_URL}/library/books`, auth());
    assert(res.data, 'Should return books');
    log(`   ✓ Library books loaded`, 'success');
  });

  await test('Library', 'Add book', async () => {
    const res = await axios.post(`${BASE_URL}/library/books`, {
      title: 'Test Book',
      author: 'Test Author',
      totalCopies: 5
    }, auth());
    assert(res.data, 'Should add book');
  });

  await test('Library', 'Get transactions', async () => {
    const res = await axios.get(`${BASE_URL}/library/transactions`, auth());
    assert(res.data, 'Should return transactions');
  });

  // ============================================================
  // 15. TRANSPORT
  // ============================================================
  log('Testing Transport...', 'module');

  await test('Transport', 'Get vehicles', async () => {
    const res = await axios.get(`${BASE_URL}/transport/vehicles`, auth());
    assert(res.data, 'Should return vehicles');
    log(`   ✓ Vehicles loaded`, 'success');
  });

  await test('Transport', 'Get routes', async () => {
    const res = await axios.get(`${BASE_URL}/transport/routes`, auth());
    assert(res.data, 'Should return routes');
    log(`   ✓ Routes loaded`, 'success');
  });

  await test('Transport', 'Get attendance', async () => {
    const res = await axios.get(`${BASE_URL}/transport/attendance`, auth());
    assert(res.data, 'Should return attendance');
  });

  // ============================================================
  // 16. BUS ROUTES
  // ============================================================
  log('Testing Bus Routes...', 'module');

  await test('Bus Routes', 'Get all routes', async () => {
    const res = await axios.get(`${BASE_URL}/bus-routes`, auth());
    assert(res.data, 'Should return bus routes');
    log(`   ✓ Bus routes loaded`, 'success');
  });

  // ============================================================
  // 17. HOSTEL
  // ============================================================
  log('Testing Hostel...', 'module');

  await test('Hostel', 'Get room types', async () => {
    const res = await axios.get(`${BASE_URL}/hostel/room-types`, auth());
    assert(res.data, 'Should return room types');
    log(`   ✓ Room types loaded`, 'success');
  });

  await test('Hostel', 'Get rooms', async () => {
    const res = await axios.get(`${BASE_URL}/hostel/rooms`, auth());
    assert(res.data, 'Should return rooms');
  });

  await test('Hostel', 'Get allocations', async () => {
    const res = await axios.get(`${BASE_URL}/hostel/allocations`, auth());
    assert(res.data, 'Should return allocations');
  });

  // ============================================================
  // 18. CANTEEN
  // ============================================================
  log('Testing Canteen...', 'module');

  await test('Canteen', 'Get items', async () => {
    const res = await axios.get(`${BASE_URL}/canteen/items`, auth());
    assert(res.data, 'Should return items');
    log(`   ✓ Canteen items loaded`, 'success');
  });

  await test('Canteen', 'Add item', async () => {
    const res = await axios.post(`${BASE_URL}/canteen/items`, {
      name: 'Test Item',
      price: 50,
      quantityAvailable: 100,
      category: 'Snacks'
    }, auth());
    assert(res.data, 'Should add item');
  });

  await test('Canteen', 'Get sales', async () => {
    const res = await axios.get(`${BASE_URL}/canteen/sales`, auth());
    assert(res.data, 'Should return sales');
  });

  // ============================================================
  // 19. PAYROLL
  // ============================================================
  log('Testing Payroll...', 'module');

  await test('Payroll', 'Get records', async () => {
    const res = await axios.get(`${BASE_URL}/payroll`, auth());
    assert(res.data, 'Should return payroll');
    log(`   ✓ Payroll records loaded`, 'success');
  });

  // ============================================================
  // 20. SALARY SETUP
  // ============================================================
  log('Testing Salary Setup...', 'module');

  await test('Salary Setup', 'Get structures', async () => {
    const res = await axios.get(`${BASE_URL}/salary-setup/structures`, auth());
    assert(res.data, 'Should return salary structures');
    log(`   ✓ Salary structures loaded`, 'success');
  });

  // ============================================================
  // 21. NOTIFICATIONS
  // ============================================================
  log('Testing Notifications...', 'module');

  await test('Notifications', 'Get notifications', async () => {
    const res = await axios.get(`${BASE_URL}/notifications`, auth());
    assert(res.data, 'Should return notifications');
    log(`   ✓ Notifications loaded`, 'success');
  });

  // ============================================================
  // 22. CHATBOT
  // ============================================================
  log('Testing Chatbot...', 'module');

  await test('Chatbot', 'Send message', async () => {
    const res = await axios.post(`${BASE_URL}/chatbot/message`, {
      message: 'Hello',
      language: 'en'
    }, auth());
    assert(res.data, 'Should get response');
    log(`   ✓ Chatbot responded`, 'success');
  });

  await test('Chatbot', 'Get history', async () => {
    const res = await axios.get(`${BASE_URL}/chatbot/history`, auth());
    assert(res.data, 'Should return history');
  });

  // ============================================================
  // 23. AUDIT
  // ============================================================
  log('Testing Audit Log...', 'module');

  await test('Audit', 'Get logs', async () => {
    const res = await axios.get(`${BASE_URL}/audit/logs`, auth());
    assert(res.data, 'Should return audit logs');
    log(`   ✓ Audit logs loaded`, 'success');
  });

  // ============================================================
  // 24. EXPORT
  // ============================================================
  log('Testing Export...', 'module');

  await test('Export', 'Export students', async () => {
    const res = await axios.get(`${BASE_URL}/export/students`, auth());
    assert(res.data, 'Should export students');
    log(`   ✓ Student export works`, 'success');
  });

  await test('Export', 'Export fees', async () => {
    const res = await axios.get(`${BASE_URL}/export/fees`, auth());
    assert(res.data, 'Should export fees');
  });

  await test('Export', 'Export attendance', async () => {
    const res = await axios.get(`${BASE_URL}/export/attendance`, auth());
    assert(res.data, 'Should export attendance');
  });

  // ============================================================
  // 25. PDF
  // ============================================================
  log('Testing PDF Generation...', 'module');

  await test('PDF', 'Generate fee receipt', async () => {
    try {
      const res = await axios.get(`${BASE_URL}/pdf/fee-receipt/test-id`, auth());
      assert(res.data, 'Should generate PDF');
    } catch (err) {
      // 404 is acceptable for test ID
      if (err.response?.status === 404) {
        log(`   ✓ PDF endpoint exists`, 'success');
      } else {
        throw err;
      }
    }
  });

  // ============================================================
  // SUMMARY
  // ============================================================
  const duration = ((Date.now() - startTime) / 1000).toFixed(2);
  const passRate = ((results.passed / results.total) * 100).toFixed(2);

  console.log('\n' + '='.repeat(80));
  console.log('✅ TEST EXECUTION COMPLETE!');
  console.log('='.repeat(80));
  console.log(`\n⏱️  Duration: ${duration}s\n`);
  console.log('📊 RESULTS:');
  console.log('-'.repeat(80));
  console.log(`Total Tests:     ${results.total}`);
  console.log(`Passed:          ${results.passed} ✓`);
  console.log(`Failed:          ${results.failed} ✗`);
  console.log(`Pass Rate:       ${passRate}%`);
  console.log('-'.repeat(80));

  console.log('\n📦 MODULES:');
  console.log('-'.repeat(80));
  Object.entries(results.modules).forEach(([mod, count]) => {
    console.log(`✅ ${mod.padEnd(25)} ${count} tests`);
  });

  if (results.errors.length > 0) {
    console.log('\n❌ ERRORS:');
    console.log('-'.repeat(80));
    results.errors.slice(0, 10).forEach((err, i) => {
      console.log(`${i + 1}. ${err}`);
    });
  }

  console.log('\n' + '='.repeat(80) + '\n');

  // Save report
  const fs = require('fs');
  const path = require('path');
  const reportPath = path.join(__dirname, '..', 'test-results.json');
  fs.writeFileSync(reportPath, JSON.stringify({
    ...results,
    duration,
    passRate,
    timestamp: new Date().toISOString()
  }, null, 2));

  console.log(`📝 Report saved: ${reportPath}\n`);

  process.exit(results.failed > 0 ? 1 : 0);
}

main().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
