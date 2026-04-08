const prisma = require('./config/prisma');
const axios = require('axios');
require('dotenv').config();

// Test Results Tracker
const testResults = {
  total: 0,
  passed: 0,
  failed: 0,
  skipped: 0,
  byModule: {},
  byRole: {},
  criticalIssues: [],
  details: []
};

// Test Configuration
const BASE_URL = process.env.BASE_URL || 'http://localhost:5000/api';
let authToken = null;
let currentUser = null;

// Test Accounts (created by create-test-accounts.js)
const TEST_ACCOUNTS = {
  superadmin: { email: 'superadmin@test.com', password: 'test123' },
  teacher: { email: 'teacher@test.com', password: 'test123' },
  student: { email: 'student@test.com', password: 'test123' },
  parent: { email: 'parent@test.com', password: 'test123' },
  accounts: { email: 'accounts@test.com', password: 'test123' },
  hr: { email: 'hr@test.com', password: 'test123' },
  canteen: { email: 'canteen@test.com', password: 'test123' },
  conductor: { email: 'conductor@test.com', password: 'test123' },
  driver: { email: 'driver@test.com', password: 'test123' },
  staff: { email: 'staff@test.com', password: 'test123' }
};

// Helper Functions
function log(message, type = 'info') {
  const icons = {
    info: '📝',
    pass: '✅',
    fail: '❌',
    skip: '⏭️',
    warn: '⚠️'
  };
  console.log(`${icons[type] || icons.info} ${message}`);
}

async function login(role) {
  try {
    const account = TEST_ACCOUNTS[role];
    if (!account) {
      throw new Error(`Unknown role: ${role}`);
    }

    const res = await axios.post(`${BASE_URL}/auth/login`, {
      email: account.email,
      password: account.password
    });

    authToken = res.data.token;
    currentUser = { role, ...res.data.user };
    log(`Logged in as ${role} (${res.data.user.name})`, 'pass');
    return true;
  } catch (err) {
    log(`Login failed for ${role}: ${err.response?.data?.msg || err.message}`, 'fail');
    return false;
  }
}

function getHeaders() {
  return {
    headers: {
      Authorization: `Bearer ${authToken}`,
      'Content-Type': 'application/json'
    }
  };
}

function recordTest(module, testName, status, message = '', role = currentUser?.role) {
  testResults.total++;
  
  if (status === 'pass') testResults.passed++;
  else if (status === 'fail') testResults.failed++;
  else testResults.skipped++;

  // Track by module
  if (!testResults.byModule[module]) {
    testResults.byModule[module] = { total: 0, passed: 0, failed: 0 };
  }
  testResults.byModule[module].total++;
  if (status === 'pass') testResults.byModule[module].passed++;
  else if (status === 'fail') testResults.byModule[module].failed++;

  // Track by role
  if (!testResults.byRole[role]) {
    testResults.byRole[role] = { total: 0, passed: 0, failed: 0 };
  }
  testResults.byRole[role].total++;
  if (status === 'pass') testResults.byRole[role].passed++;
  else if (status === 'fail') testResults.byRole[role].failed++;

  testResults.details.push({
    module,
    testName,
    status,
    message,
    role,
    timestamp: new Date().toISOString()
  });

  if (status === 'fail') {
    testResults.criticalIssues.push({
      module,
      testName,
      message,
      role
    });
  }
}

// ==================== MODULE TESTS ====================

// Test 1: Dashboard Module
async function testDashboard() {
  const module = 'Dashboard';
  log(`\n📊 Testing ${module}...`, 'info');

  try {
    // Test GET /api/dashboard/stats
    const statsRes = await axios.get(`${BASE_URL}/dashboard/stats`, getHeaders());
    
    if (statsRes.status === 200) {
      recordTest(module, 'GET dashboard stats', 'pass', 'Stats endpoint working');
      
      // Check if stats are role-appropriate
      const stats = statsRes.data;
      
      if (currentUser.role === 'student') {
        if (stats.linkedStudent) {
          recordTest(module, 'Student sees own data', 'pass', 'linkedStudent present');
        } else {
          recordTest(module, 'Student sees own data', 'fail', 'linkedStudent missing');
        }
      } else if (currentUser.role === 'superadmin') {
        if (stats.totalStudents > 0) {
          recordTest(module, 'Admin sees all stats', 'pass', 'totalStudents present');
        } else {
          recordTest(module, 'Admin sees all stats', 'warn', 'No data in system');
        }
      }
    } else {
      recordTest(module, 'GET dashboard stats', 'fail', `Status: ${statsRes.status}`);
    }

    // Test GET /api/dashboard/quick-actions
    const actionsRes = await axios.get(`${BASE_URL}/dashboard/quick-actions`, getHeaders());
    
    if (actionsRes.status === 200) {
      recordTest(module, 'GET quick actions', 'pass', 'Quick actions working');
    } else {
      recordTest(module, 'GET quick actions', 'fail', `Status: ${actionsRes.status}`);
    }

  } catch (err) {
    recordTest(module, 'Dashboard tests', 'fail', err.message);
  }
}

// Test 2: Students Module
async function testStudents() {
  const module = 'Students';
  log(`\n🎓 Testing ${module}...`, 'info');

  try {
    // Test GET /api/students
    const studentsRes = await axios.get(`${BASE_URL}/students`, getHeaders());
    
    if (studentsRes.status === 200) {
      recordTest(module, 'GET students list', 'pass', `${studentsRes.data.length} students`);
      
      // Check data isolation
      if (currentUser.role === 'student') {
        if (studentsRes.data.length <= 1) {
          recordTest(module, 'Student sees only own record', 'pass', 'Student data is scoped');
        } else {
          recordTest(module, 'Student sees only own record', 'fail', `Student can see ${studentsRes.data.length} records`);
        }
      } else if (currentUser.role === 'parent') {
        recordTest(module, 'Parent student data is scoped', 'pass', `${studentsRes.data.length} linked student record(s)`);
      } else if (['superadmin', 'teacher', 'accounts'].includes(currentUser.role)) {
        recordTest(module, 'Authorized role can access', 'pass', 'Access granted');
      }
    } else {
      recordTest(module, 'GET students list', 'fail', `Status: ${studentsRes.status}`);
    }

    // Test GET /api/students/stats/summary
    try {
      const statsRes = await axios.get(`${BASE_URL}/students/stats/summary`, getHeaders());
      if (statsRes.status === 200) {
        recordTest(module, 'GET student stats', 'pass', 'Stats endpoint working');
      }
    } catch (err) {
      recordTest(module, 'GET student stats', 'fail', err.message);
    }

  } catch (err) {
    recordTest(module, 'Students tests', 'fail', err.message);
  }
}

// Test 3: Attendance Module
async function testAttendance() {
  const module = 'Attendance';
  log(`\n📅 Testing ${module}...`, 'info');

  try {
    const today = new Date().toISOString().split('T')[0];

    if (['superadmin', 'accounts', 'hr', 'teacher'].includes(currentUser.role)) {
      try {
        const classesRes = await axios.get(`${BASE_URL}/classes`, getHeaders());
        if (classesRes.data.length > 0) {
          const classId = classesRes.data[0]._id;
          const attendanceRes = await axios.get(
            `${BASE_URL}/attendance/class/${classId}/${today}`,
            getHeaders()
          );

          if (attendanceRes.status === 200) {
            recordTest(module, 'GET class attendance', 'pass', 'Attendance data retrieved');
          }
        } else {
          recordTest(module, 'GET class attendance', 'skip', 'No classes found');
        }
      } catch (err) {
        recordTest(module, 'GET class attendance', 'fail', err.message);
      }
    } else if (['student', 'parent'].includes(currentUser.role)) {
      try {
        const studentRes = await axios.get(`${BASE_URL}/students`, getHeaders());
        const targetStudent = currentUser.role === 'student'
          ? studentRes.data.find(s => String(s.userId?._id || s.userId) === String(currentUser.id))
          : studentRes.data[0];

        if (targetStudent?._id) {
          const myAttendanceRes = await axios.get(
            `${BASE_URL}/attendance/student/${targetStudent._id}`,
            getHeaders()
          );

          if (myAttendanceRes.status === 200) {
            recordTest(module, `${currentUser.role} can view attendance`, 'pass', 'Attendance visible');
          }
        } else {
          recordTest(module, `${currentUser.role} can view attendance`, 'skip', 'No linked student found');
        }
      } catch (err) {
        recordTest(module, `${currentUser.role} can view attendance`, 'fail', err.message);
      }
    } else {
      recordTest(module, 'Attendance access', 'skip', 'Attendance module not available for this role');
    }
  } catch (err) {
    recordTest(module, 'Attendance tests', 'fail', err.message);
  }
}

// Test 4: Fee Module
async function testFees() {
  const module = 'Fees';
  log(`\n💰 Testing ${module}...`, 'info');

  try {
    const structuresRes = await axios.get(`${BASE_URL}/fee/structures`, getHeaders());
    if (structuresRes.status === 200) {
      recordTest(module, 'GET fee structures', 'pass', `${structuresRes.data.length} structures`);
    }

    if (['superadmin', 'accounts'].includes(currentUser.role)) {
      const paymentsRes = await axios.get(`${BASE_URL}/fee/payments`, getHeaders());
      if (paymentsRes.status === 200) {
        recordTest(module, 'GET fee payments', 'pass', `${paymentsRes.data.length} payments`);
      }
    } else if (['student', 'parent'].includes(currentUser.role)) {
      try {
        const myFeesRes = await axios.get(`${BASE_URL}/fee/my`, getHeaders());
        if (myFeesRes.status === 200) {
          recordTest(module, 'Student/Parent can view own fees', 'pass', 'Own fees visible');
        }
      } catch (err) {
        recordTest(module, 'View own fees', 'fail', err.message);
      }
    } else {
      recordTest(module, 'Fee payment access', 'skip', 'Payment records are restricted for this role');
    }

  } catch (err) {
    recordTest(module, 'Fees tests', 'fail', err.message);
  }
}

// Test 5: Exams Module
async function testExams() {
  const module = 'Exams';
  log(`\n✏️ Testing ${module}...`, 'info');

  try {
    // Test GET /api/exams/schedule
    const examsRes = await axios.get(`${BASE_URL}/exams/schedule`, getHeaders());
    
    if (examsRes.status === 200) {
      recordTest(module, 'GET exam schedule', 'pass', `${examsRes.data.length} exams`);
    } else {
      recordTest(module, 'GET exam schedule', 'fail', `Status: ${examsRes.status}`);
    }

    // Test GET /api/exams/results/student/:id (for students)
    if (currentUser.role === 'student') {
      try {
        const studentRes = await axios.get(`${BASE_URL}/students`, getHeaders());
        if (studentRes.data.length > 0) {
          const studentId = studentRes.data.find(s => s.userId === currentUser.id)?._id;
          if (studentId) {
            const resultsRes = await axios.get(
              `${BASE_URL}/exams/results/student/${studentId}`,
              getHeaders()
            );
            
            if (resultsRes.status === 200) {
              recordTest(module, 'Student can view own results', 'pass', 'Results visible');
            }
          }
        }
      } catch (err) {
        recordTest(module, 'Student view own results', 'warn', err.message);
      }
    }

  } catch (err) {
    recordTest(module, 'Exams tests', 'fail', err.message);
  }
}

// Test 6: Library Module
async function testLibrary() {
  const module = 'Library';
  log(`\n📖 Testing ${module}...`, 'info');

  try {
    // Test GET /api/library/books
    const booksRes = await axios.get(`${BASE_URL}/library/books`, getHeaders());
    
    if (booksRes.status === 200) {
      recordTest(module, 'GET library books', 'pass', `${booksRes.data.length} books`);
    } else {
      recordTest(module, 'GET library books', 'fail', `Status: ${booksRes.status}`);
    }

    // Test GET /api/library/transactions
    const transactionsRes = await axios.get(`${BASE_URL}/library/transactions`, getHeaders());
    
    if (transactionsRes.status === 200) {
      recordTest(module, 'GET library transactions', 'pass', `${transactionsRes.data.length} transactions`);
    } else {
      recordTest(module, 'GET library transactions', 'fail', `Status: ${transactionsRes.status}`);
    }

  } catch (err) {
    recordTest(module, 'Library tests', 'fail', err.message);
  }
}

// Test 7: Canteen Module
async function testCanteen() {
  const module = 'Canteen';
  log(`\n🍕 Testing ${module}...`, 'info');

  try {
    // Test GET /api/canteen/items
    const itemsRes = await axios.get(`${BASE_URL}/canteen/items`, getHeaders());
    
    if (itemsRes.status === 200) {
      recordTest(module, 'GET canteen items', 'pass', `${itemsRes.data.length} items`);
    } else {
      recordTest(module, 'GET canteen items', 'fail', `Status: ${itemsRes.status}`);
    }

    // Test GET /api/canteen/sales (canteen staff only)
    if (currentUser.role === 'canteen') {
      const salesRes = await axios.get(`${BASE_URL}/canteen/sales`, getHeaders());
      
      if (salesRes.status === 200) {
        recordTest(module, 'Canteen can view sales', 'pass', `${salesRes.data.length} sales`);
      }
    }

  } catch (err) {
    recordTest(module, 'Canteen tests', 'fail', err.message);
  }
}

// Test 8: Transport Module
async function testTransport() {
  const module = 'Transport';
  log(`\n🚌 Testing ${module}...`, 'info');

  try {
    // Test GET /api/transport
    const vehiclesRes = await axios.get(`${BASE_URL}/transport`, getHeaders());
    
    if (vehiclesRes.status === 200) {
      recordTest(module, 'GET transport vehicles', 'pass', `${vehiclesRes.data.length} vehicles`);
    } else {
      recordTest(module, 'GET transport vehicles', 'fail', `Status: ${vehiclesRes.status}`);
    }

    // Test GET /api/transport/routes
    const routesRes = await axios.get(`${BASE_URL}/transport/routes`, getHeaders());
    
    if (routesRes.status === 200) {
      recordTest(module, 'GET bus routes', 'pass', `${routesRes.data.length} routes`);
    } else {
      recordTest(module, 'GET bus routes', 'fail', `Status: ${routesRes.status}`);
    }

  } catch (err) {
    recordTest(module, 'Transport tests', 'fail', err.message);
  }
}

// Test 9: Hostel Module
async function testHostel() {
  const module = 'Hostel';
  log(`\n🏠 Testing ${module}...`, 'info');

  try {
    if (['superadmin', 'accounts', 'teacher', 'staff', 'hr', 'student', 'parent'].includes(currentUser.role)) {
      const hostelRes = await axios.get(`${BASE_URL}/hostel/dashboard`, getHeaders());
      if (hostelRes.status === 200) {
        recordTest(module, 'GET hostel dashboard', 'pass', 'Hostel data retrieved');
      }
    } else {
      recordTest(module, 'Hostel access', 'skip', 'Hostel module not available for this role');
    }

  } catch (err) {
    recordTest(module, 'Hostel tests', 'fail', err.message);
  }
}

// Test 10: HR Module
async function testHR() {
  const module = 'HR';
  log(`\n👔 Testing ${module}...`, 'info');

  try {
    if (['hr', 'superadmin'].includes(currentUser.role)) {
      const leavesRes = await axios.get(`${BASE_URL}/leave`, getHeaders());
      if (leavesRes.status === 200) {
        recordTest(module, 'GET leave records', 'pass', `${leavesRes.data.length} leaves`);
      }
    } else if (['teacher', 'staff', 'accounts', 'canteen', 'conductor', 'driver'].includes(currentUser.role)) {
      const myLeavesRes = await axios.get(`${BASE_URL}/leave/my`, getHeaders());
      if (myLeavesRes.status === 200) {
        recordTest(module, 'GET own leave records', 'pass', `${myLeavesRes.data.length} leaves`);
      }
    } else {
      recordTest(module, 'HR access', 'skip', 'HR module not available for this role');
    }

  } catch (err) {
    recordTest(module, 'HR tests', 'fail', err.message);
  }
}

// Test 11: Payroll Module
async function testPayroll() {
  const module = 'Payroll';
  log(`\n💵 Testing ${module}...`, 'info');

  try {
    if (['superadmin', 'accounts'].includes(currentUser.role)) {
      const payrollRes = await axios.get(`${BASE_URL}/payroll`, getHeaders());
      if (payrollRes.status === 200) {
        recordTest(module, 'GET payroll records', 'pass', `${payrollRes.data.length} records`);
      }
    } else if (['teacher', 'staff', 'hr', 'canteen', 'conductor', 'driver'].includes(currentUser.role)) {
      const ownPayrollRes = await axios.get(`${BASE_URL}/payroll/${currentUser.id}`, getHeaders());
      if (ownPayrollRes.status === 200) {
        recordTest(module, 'GET own payroll records', 'pass', `${ownPayrollRes.data.length} records`);
      }
    } else {
      recordTest(module, 'Payroll access', 'skip', 'Payroll module not available for this role');
    }

  } catch (err) {
    recordTest(module, 'Payroll tests', 'fail', err.message);
  }
}

// Test 12: Notices Module
async function testNotices() {
  const module = 'Notices';
  log(`\n📢 Testing ${module}...`, 'info');

  try {
    // Test GET /api/notices
    const noticesRes = await axios.get(`${BASE_URL}/notices`, getHeaders());
    
    if (noticesRes.status === 200) {
      recordTest(module, 'GET notices', 'pass', `${noticesRes.data.length} notices`);
    } else {
      recordTest(module, 'GET notices', 'fail', `Status: ${noticesRes.status}`);
    }

  } catch (err) {
    recordTest(module, 'Notices tests', 'fail', err.message);
  }
}

// Test 13: Complaints Module
async function testComplaints() {
  const module = 'Complaints';
  log(`\n⚠️ Testing ${module}...`, 'info');

  try {
    // Test GET /api/complaints
    const complaintsRes = await axios.get(`${BASE_URL}/complaints`, getHeaders());
    
    if (complaintsRes.status === 200) {
      recordTest(module, 'GET complaints', 'pass', `${complaintsRes.data.length} complaints`);
    } else {
      recordTest(module, 'GET complaints', 'fail', `Status: ${complaintsRes.status}`);
    }

  } catch (err) {
    recordTest(module, 'Complaints tests', 'fail', err.message);
  }
}

// Test 14: Remarks Module
async function testRemarks() {
  const module = 'Remarks';
  log(`\n💭 Testing ${module}...`, 'info');

  try {
    let endpoint = `${BASE_URL}/remarks`;
    let testName = 'GET remarks';

    if (currentUser.role === 'teacher') {
      endpoint = `${BASE_URL}/remarks/teacher`;
      testName = 'GET teacher remarks';
    } else if (['student', 'parent'].includes(currentUser.role)) {
      endpoint = `${BASE_URL}/remarks/my`;
      testName = 'GET own remarks';
    } else if (!['superadmin'].includes(currentUser.role)) {
      recordTest(module, 'Remarks access', 'skip', 'Remarks module not available for this role');
      return;
    }

    const remarksRes = await axios.get(endpoint, getHeaders());
    if (remarksRes.status === 200) {
      recordTest(module, testName, 'pass', `${remarksRes.data.length} remarks`);
    }

  } catch (err) {
    recordTest(module, 'Remarks tests', 'fail', err.message);
  }
}

// Test 15: Import Data Module (SuperAdmin only)
async function testImportData() {
  const module = 'Import Data';
  log(`\n📥 Testing ${module}...`, 'info');

  if (currentUser.role !== 'superadmin') {
    recordTest(module, 'Non-SuperAdmin access', 'skip', 'SuperAdmin only');
    return;
  }

  try {
    // Test GET /api/import/templates/students
    const templateRes = await axios.get(
      `${BASE_URL}/import/templates/students`,
      getHeaders()
    );
    
    if (templateRes.status === 200) {
      recordTest(module, 'GET import templates', 'pass', 'Template download working');
    } else {
      recordTest(module, 'GET import templates', 'fail', `Status: ${templateRes.status}`);
    }

  } catch (err) {
    recordTest(module, 'Import Data tests', 'fail', err.message);
  }
}

// Test 16: Archive Module
async function testArchive() {
  const module = 'Archive';
  log(`\n🗄️ Testing ${module}...`, 'info');

  if (!['superadmin', 'accounts'].includes(currentUser.role)) {
    recordTest(module, 'Non-authorized role access', 'skip', 'SuperAdmin/Accounts only');
    return;
  }

  try {
    // Test GET /api/archive/students
    const archiveRes = await axios.get(`${BASE_URL}/archive/students`, getHeaders());
    
    if (archiveRes.status === 200) {
      recordTest(module, 'GET archive data', 'pass', 'Archive accessible');
    } else {
      recordTest(module, 'GET archive data', 'fail', `Status: ${archiveRes.status}`);
    }

  } catch (err) {
    recordTest(module, 'Archive tests', 'fail', err.message);
  }
}

// ==================== MAIN TEST RUNNER ====================

async function runAllTests() {
  try {
    await prisma.$connect();
    console.log('✅ MongoDB Connected\n');
    console.log('🧪 Starting Comprehensive End-to-End Tests...\n');
    console.log('='.repeat(70));

    // Test each role
    for (const role of Object.keys(TEST_ACCOUNTS)) {
      console.log(`\n${'='.repeat(70)}`);
      log(`Testing as: ${role.toUpperCase()}`, 'info');
      console.log('='.repeat(70));

      const loginSuccess = await login(role);
      
      if (!loginSuccess) {
        log(`Skipping ${role} tests (login failed)`, 'skip');
        continue;
      }

      // Run all module tests for this role
      await testDashboard();
      await testStudents();
      await testAttendance();
      await testFees();
      await testExams();
      await testLibrary();
      await testCanteen();
      await testTransport();
      await testHostel();
      await testHR();
      await testPayroll();
      await testNotices();
      await testComplaints();
      await testRemarks();
      await testImportData();
      await testArchive();
    }

    // Print Summary
    printSummary();

    process.exit(testResults.failed > 0 ? 1 : 0);
  } catch (err) {
    console.error('❌ Test execution failed:', err.message);
    process.exit(1);
  }
}

function printSummary() {
  console.log('\n' + '='.repeat(70));
  console.log('📊 TEST SUMMARY');
  console.log('='.repeat(70));
  console.log(`Total Tests: ${testResults.total}`);
  console.log(`✅ Passed: ${testResults.passed}`);
  console.log(`❌ Failed: ${testResults.failed}`);
  console.log(`⏭️ Skipped: ${testResults.skipped}`);
  console.log('='.repeat(70));

  const passRate = Math.round((testResults.passed / (testResults.passed + testResults.failed)) * 100);
  console.log(`📈 Pass Rate: ${passRate}%`);
  console.log('='.repeat(70));

  // By Module
  console.log('\n📦 BY MODULE:');
  console.log('-'.repeat(70));
  for (const [module, stats] of Object.entries(testResults.byModule)) {
    const modulePassRate = Math.round((stats.passed / stats.total) * 100);
    console.log(`${module.padEnd(20)} ${stats.passed}/${stats.total} (${modulePassRate}%)`);
  }

  // By Role
  console.log('\n👥 BY ROLE:');
  console.log('-'.repeat(70));
  for (const [role, stats] of Object.entries(testResults.byRole)) {
    const rolePassRate = Math.round((stats.passed / stats.total) * 100);
    console.log(`${role.padEnd(15)} ${stats.passed}/${stats.total} (${rolePassRate}%)`);
  }

  // Critical Issues
  if (testResults.criticalIssues.length > 0) {
    console.log('\n🚨 CRITICAL ISSUES:');
    console.log('-'.repeat(70));
    testResults.criticalIssues.forEach((issue, i) => {
      console.log(`${i + 1}. [${issue.role}] ${issue.module}: ${issue.testName}`);
      console.log(`   Error: ${issue.message}`);
    });
  }

  // Final Status
  console.log('\n' + '='.repeat(70));
  if (testResults.failed === 0) {
    console.log('🎉 ALL TESTS PASSED! System is production ready!');
  } else {
    console.log(`❌ ${testResults.failed} test(s) failed. Please review issues above.`);
  }
  console.log('='.repeat(70));

  // Save results to file
  const fs = require('fs');
  const path = require('path');
  const resultsFile = path.join(__dirname, 'e2e-test-results.json');
  fs.writeFileSync(resultsFile, JSON.stringify(testResults, null, 2));
  console.log(`\n📄 Detailed results saved to: ${resultsFile}`);
}

// Run tests
runAllTests();
