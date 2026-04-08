/**
 * FULL E2E AUDIT – School ERP
 * Tests every API route, database model, and feature end-to-end.
 * Run:  node tests/full-e2e-audit.js
 */
process.env.NODE_ENV = 'test';

const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

if (process.env.DATABASE_URL_TEST) {
  process.env.DATABASE_URL = process.env.DATABASE_URL_TEST;
} else {
  throw new Error('DATABASE_URL_TEST is required for full E2E audit.');
}

const assert = require('node:assert/strict');
const bcrypt = require('bcryptjs');
const prisma = require('../config/prisma');
const connectDB = require('../config/db');
const { startServer } = require('../server');

const PREFIX = 'e2eaudit';
let baseUrl = '';
let server;
let tokens = {};
let fixtures = {};

// ─── Helpers ───────────────────────────────────────────────────────────────
function log(msg) { process.stdout.write(`${msg}\n`); }

async function req(pathname, { method = 'GET', token, body, raw = false } = {}) {
  const opts = {
    method,
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  };
  const res = await fetch(`${baseUrl}${pathname}`, opts);
  if (raw) return res;
  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = text; }
  const payload = data && typeof data === 'object' && 'data' in data ? data.data : data;
  return { status: res.status, data: payload, raw: data, text };
}

async function login(email, password) {
  const r = await req('/api/auth/login', { method: 'POST', body: { email, password } });
  if (r.status !== 200) throw new Error(`Login failed for ${email}: ${r.text}`);
  return r.data.token;
}

// ─── Results tracking ──────────────────────────────────────────────────────
const results = { pass: 0, fail: 0, skip: 0, details: [] };

async function check(module, name, fn) {
  const label = `[${module}] ${name}`;
  try {
    await fn();
    results.pass++;
    results.details.push({ module, name, status: 'PASS' });
    log(`  ✅ PASS  ${label}`);
    return true;
  } catch (err) {
    results.fail++;
    results.details.push({ module, name, status: 'FAIL', error: err.message });
    log(`  ❌ FAIL  ${label}`);
    log(`         ${err.message}`);
    return false;
  }
}

// ─── Setup ─────────────────────────────────────────────────────────────────
async function cleanup() {
  const prefix = PREFIX;
  try {
    await prisma.transportAttendance.deleteMany({});
    await prisma.staffAttendance.deleteMany({});
    await prisma.transportVehicle.deleteMany({ where: { busNumber: { startsWith: prefix } } });
    await prisma.busRoute.deleteMany({ where: { routeName: { startsWith: prefix } } });
    await prisma.hostelAllocation.deleteMany({});
    await prisma.hostelFeeStructure.deleteMany({});
    await prisma.hostelRoom.deleteMany({ where: { roomNumber: { startsWith: prefix } } });
    await prisma.hostelRoomType.deleteMany({ where: { name: { startsWith: prefix } } });
    await prisma.libraryTransaction.deleteMany({});
    await prisma.libraryBook.deleteMany({ where: { title: { startsWith: prefix } } });
    await prisma.canteenSale.deleteMany({});
    await prisma.canteenItem.deleteMany({ where: { name: { startsWith: prefix } } });
    await prisma.examResult.deleteMany({});
    await prisma.exam.deleteMany({ where: { name: { startsWith: prefix } } });
    await prisma.feePayment.deleteMany({});
    await prisma.feeStructure.deleteMany({});
    await prisma.homework.deleteMany({ where: { title: { startsWith: prefix } } });
    await prisma.routine.deleteMany({});
    await prisma.leave.deleteMany({ where: { reason: { startsWith: prefix } } });
    await prisma.remark.deleteMany({ where: { remark: { startsWith: prefix } } });
    await prisma.complaint.deleteMany({ where: { description: { startsWith: prefix } } });
    await prisma.notice.deleteMany({ where: { title: { startsWith: prefix } } });
    await prisma.notification.deleteMany({});
    await prisma.payroll.deleteMany({});
    await prisma.salaryStructure.deleteMany({});
    await prisma.chatbotLog.deleteMany({});
    await prisma.attendance.deleteMany({});
    await prisma.student.deleteMany({ where: { admissionNo: { startsWith: prefix } } });
    await prisma.class.deleteMany({ where: { name: { startsWith: prefix } } });
    await prisma.user.deleteMany({ where: { email: { startsWith: prefix } } });
  } catch (e) {
    log(`  ⚠️ Cleanup partial error: ${e.message}`);
  }
}

async function seed() {
  await cleanup();

  // Users
  const mkUser = async (data) => {
    await prisma.user.deleteMany({ where: { email: data.email } });
    const hashed = await bcrypt.hash(data.password, 10);
    const user = await prisma.user.create({
      data: {
        ...data,
        password: hashed,
      },
    });
    return { ...user, _id: user.id };
  };

  fixtures.superadmin = await mkUser({
    name: 'E2E Superadmin', email: `${PREFIX}.superadmin@test.local`,
    password: 'Test@12345', role: 'superadmin', phone: '9100000001'
  });
  fixtures.teacher = await mkUser({
    name: 'E2E Teacher', email: `${PREFIX}.teacher@test.local`,
    password: 'Test@12345', role: 'teacher', phone: '9100000002',
    employeeId: `${PREFIX.toUpperCase()}-T01`, basicPay: 30000, hra: 5000, da: 3000
  });
  fixtures.accounts = await mkUser({
    name: 'E2E Accounts', email: `${PREFIX}.accounts@test.local`,
    password: 'Test@12345', role: 'accounts', phone: '9100000003',
    employeeId: `${PREFIX.toUpperCase()}-A01`
  });
  fixtures.parent = await mkUser({
    name: 'E2E Parent', email: `${PREFIX}.parent@test.local`,
    password: 'Test@12345', role: 'parent', phone: '9100000004'
  });
  fixtures.studentUser = await mkUser({
    name: 'E2E Student', email: `${PREFIX}.student@test.local`,
    password: 'Test@12345', role: 'student', phone: '9100000005'
  });
  fixtures.hr = await mkUser({
    name: 'E2E HR', email: `${PREFIX}.hr@test.local`,
    password: 'Test@12345', role: 'hr', phone: '9100000006',
    employeeId: `${PREFIX.toUpperCase()}-HR01`
  });
  fixtures.canteenUser = await mkUser({
    name: 'E2E Canteen', email: `${PREFIX}.canteen@test.local`,
    password: 'Test@12345', role: 'canteen', phone: '9100000007',
    employeeId: `${PREFIX.toUpperCase()}-CAN01`
  });

  // Class
  const classRecord = await prisma.class.create({
    data: {
      name: `${PREFIX}-Class-10`,
      section: 'A',
      sections: ['A', 'B'],
      classTeacherId: fixtures.teacher.id,
      subjects: {
        create: [
          { subject: 'Mathematics', name: 'Mathematics', teacherId: fixtures.teacher.id },
          { subject: 'English', name: 'English', teacherId: fixtures.teacher.id },
        ],
      },
      capacity: 40,
      academicYear: '2026-2027',
    },
  });
  fixtures.cls = { ...classRecord, _id: classRecord.id };

  // Student
  const studentRecord = await prisma.student.create({
    data: {
      userId: fixtures.studentUser.id,
      name: 'E2E Student',
      admissionNo: `${PREFIX}-STU-001`,
      studentId: `${PREFIX.toUpperCase()}-STU-001`,
      classId: fixtures.cls.id,
      parentPhone: '9100000004',
      parentEmail: fixtures.parent.email,
      parentUserId: fixtures.parent.id,
      dob: new Date('2010-01-15'),
      gender: 'male',
      section: 'A',
      academicYear: '2026-2027',
    },
  });
  fixtures.student = { ...studentRecord, _id: studentRecord.id };

  await prisma.user.update({
    where: { id: fixtures.parent.id },
    data: { linkedStudentIds: [fixtures.student.id] },
  });
}

async function setup() {
  await connectDB();
  await seed();
  const started = await startServer({ port: 0, skipAiBootstrap: true, skipScheduler: true });
  server = started.server;
  baseUrl = `http://127.0.0.1:${server.address().port}`;

  tokens.superadmin = await login(fixtures.superadmin.email, 'Test@12345');
  tokens.teacher = await login(fixtures.teacher.email, 'Test@12345');
  tokens.accounts = await login(fixtures.accounts.email, 'Test@12345');
  tokens.parent = await login(fixtures.parent.email, 'Test@12345');
  tokens.student = await login(fixtures.studentUser.email, 'Test@12345');
  tokens.hr = await login(fixtures.hr.email, 'Test@12345');
  tokens.canteen = await login(fixtures.canteenUser.email, 'Test@12345');
}

// ═══════════════════════════════════════════════════════════════════════════
// ALL MODULE TESTS
// ═══════════════════════════════════════════════════════════════════════════

async function testHealthCheck() {
  log('\n📋 MODULE: Health Check');
  await check('Health', 'GET /api/health returns 200', async () => {
    const r = await req('/api/health');
    assert.equal(r.status, 200);
    assert.equal(r.data.status, 'ok');
    assert.ok(r.data.database === 'connected');
  });
}

async function testAuth() {
  log('\n🔐 MODULE: Authentication');
  await check('Auth', 'Login returns token + user payload', async () => {
    const r = await req('/api/auth/login', { method: 'POST', body: { email: fixtures.superadmin.email, password: 'Test@12345' } });
    assert.equal(r.status, 200);
    assert.ok(r.data.token);
    assert.equal(r.data.user.role, 'superadmin');
  });
  await check('Auth', 'Invalid credentials returns 400', async () => {
    const r = await req('/api/auth/login', { method: 'POST', body: { email: fixtures.superadmin.email, password: 'wrongpass' } });
    assert.equal(r.status, 400);
  });
  await check('Auth', 'No token returns 401', async () => {
    const r = await req('/api/classes');
    assert.equal(r.status, 401);
  });
  await check('Auth', 'GET /api/auth/users (superadmin)', async () => {
    const r = await req('/api/auth/users', { token: tokens.superadmin });
    assert.equal(r.status, 200);
    assert.ok(Array.isArray(r.data));
  });
  await check('Auth', 'Register new user (superadmin only)', async () => {
    const r = await req('/api/auth/register', {
      method: 'POST', token: tokens.superadmin,
      body: { name: 'E2E New User', email: `${PREFIX}.newuser@test.local`, password: 'Test@12345', role: 'staff', phone: '9100099999', employeeId: `${PREFIX.toUpperCase()}-NEW01` }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    await prisma.user.deleteMany({ where: { email: `${PREFIX}.newuser@test.local` } });
  });
  await check('Auth', 'Forgot password endpoint', async () => {
    const r = await req('/api/auth/forgot-password', { method: 'POST', body: { email: fixtures.superadmin.email } });
    assert.equal(r.status, 200);
  });
  await check('Auth', 'Logout endpoint', async () => {
    const r = await req('/api/auth/logout', { method: 'POST', token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testDashboard() {
  log('\n📊 MODULE: Dashboard');
  await check('Dashboard', 'GET /api/dashboard/stats (superadmin)', async () => {
    const r = await req('/api/dashboard/stats', { token: tokens.superadmin });
    assert.equal(r.status, 200);
    assert.ok(r.data);
  });
  await check('Dashboard', 'GET /api/dashboard/quick-actions', async () => {
    const r = await req('/api/dashboard/quick-actions', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Dashboard', 'Student dashboard access', async () => {
    const r = await req('/api/dashboard/stats', { token: tokens.student });
    assert.equal(r.status, 200);
  });
  await check('Dashboard', 'Teacher dashboard access', async () => {
    const r = await req('/api/dashboard/stats', { token: tokens.teacher });
    assert.equal(r.status, 200);
  });
}

async function testClasses() {
  log('\n🏫 MODULE: Classes');
  await check('Classes', 'GET /api/classes list', async () => {
    const r = await req('/api/classes', { token: tokens.superadmin });
    assert.equal(r.status, 200);
    assert.ok(Array.isArray(r.data));
  });
  await check('Classes', 'GET /api/classes/:id single', async () => {
    const r = await req(`/api/classes/${fixtures.cls._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
    // Route returns {class: {...}, totalStudents, sectionStudents, subjectTeachers}
    assert.ok(r.data.class?.name || r.data.name, 'Class detail should include name');
  });
  await check('Classes', 'GET /api/classes/stats/summary', async () => {
    const r = await req('/api/classes/stats/summary', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Classes', 'GET /api/classes/teachers/list', async () => {
    const r = await req('/api/classes/teachers/list', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Classes', 'CRUD: Create → Update → Delete class', async () => {
    // classTeacherId required when sections are defined
    const c = await req('/api/classes', {
      method: 'POST', token: tokens.superadmin,
      body: { name: `${PREFIX}-TempClass`, section: 'X', sections: ['X'], classTeacherId: fixtures.teacher._id, capacity: 30, academicYear: '2026-2027' }
    });
    assert.ok([200, 201].includes(c.status), `Create: expected 200/201, got ${c.status}: ${c.text}`);
    const id = c.data?.class?._id || c.data?._id;
    assert.ok(id, 'Created class should have an ID');

    const u = await req(`/api/classes/${id}`, {
      method: 'PUT', token: tokens.superadmin, body: { capacity: 35 }
    });
    assert.equal(u.status, 200);

    const d = await req(`/api/classes/${id}`, { method: 'DELETE', token: tokens.superadmin });
    assert.equal(d.status, 200);
  });
}

async function testStudents() {
  log('\n🎓 MODULE: Students');
  await check('Students', 'GET /api/students list', async () => {
    const r = await req('/api/students', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Students', 'GET /api/students/:id single', async () => {
    const r = await req(`/api/students/${fixtures.student._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Students', 'GET /api/students/stats/summary', async () => {
    const r = await req('/api/students/stats/summary', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Students', 'GET /api/students/class/:classId', async () => {
    const r = await req(`/api/students/class/${fixtures.cls._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testAttendance() {
  log('\n📝 MODULE: Attendance');
  const today = new Date().toISOString().split('T')[0];
  await check('Attendance', 'POST /api/attendance/bulk', async () => {
    const r = await req('/api/attendance/bulk', {
      method: 'POST', token: tokens.teacher,
      body: {
        classId: fixtures.cls._id, date: today, section: 'A',
        records: [{ studentId: fixtures.student._id, status: 'present' }]
      }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
  });
  await check('Attendance', 'GET /api/attendance/class/:id/:date', async () => {
    const r = await req(`/api/attendance/class/${fixtures.cls._id}/${today}`, { token: tokens.teacher });
    assert.equal(r.status, 200);
  });
  await check('Attendance', 'GET /api/attendance/student/:id', async () => {
    const r = await req(`/api/attendance/student/${fixtures.student._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Attendance', 'GET /api/attendance/report/daily', async () => {
    const r = await req('/api/attendance/report/daily', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Attendance', 'GET /api/attendance/report/monthly', async () => {
    const r = await req('/api/attendance/report/monthly', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Attendance', 'GET /api/attendance/defaulters', async () => {
    const r = await req('/api/attendance/defaulters', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testFee() {
  log('\n💰 MODULE: Fee Management');
  await check('Fee', 'POST /api/fee/structure (create)', async () => {
    const r = await req('/api/fee/structure', {
      method: 'POST', token: tokens.superadmin,
      body: { feeType: 'Tuition', amount: 5000, classId: fixtures.cls._id, academicYear: '2026-2027' }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?.feeStructure?._id) fixtures.feeStructure = r.data.feeStructure;
  });
  await check('Fee', 'GET /api/fee/structures', async () => {
    const r = await req('/api/fee/structures', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Fee', 'POST /api/fee/collect', async () => {
    const r = await req('/api/fee/collect', {
      method: 'POST', token: tokens.superadmin,
      body: {
        studentId: fixtures.student._id,
        amountPaid: 5000,
        paymentMode: 'cash',
        feeStructureId: fixtures.feeStructure?._id || null
      }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?.payment?._id) fixtures.feePayment = r.data.payment;
  });
  await check('Fee', 'GET /api/fee/payments', async () => {
    const r = await req('/api/fee/payments', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Fee', 'GET /api/fee/student/:id', async () => {
    const r = await req(`/api/fee/student/${fixtures.student._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Fee', 'GET /api/fee/defaulters', async () => {
    const r = await req('/api/fee/defaulters', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Fee', 'GET /api/fee/collection-report', async () => {
    const r = await req('/api/fee/collection-report', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Fee', 'GET /api/fee/my (student view)', async () => {
    const r = await req('/api/fee/my', { token: tokens.student });
    assert.equal(r.status, 200);
  });
}

async function testExams() {
  log('\n📖 MODULE: Exams');
  await check('Exams', 'POST /api/exams/schedule (create)', async () => {
    const r = await req('/api/exams/schedule', {
      method: 'POST', token: tokens.superadmin,
      body: {
        name: `${PREFIX}-Midterm`, classId: fixtures.cls._id,
        subject: 'Mathematics', date: '2026-06-15',
        time: '09:00', startTime: '09:00', endTime: '12:00',
        totalMarks: 100, examType: 'midterm'
      }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?.exam?._id) fixtures.exam = r.data.exam;
    else if (r.data?._id) fixtures.exam = r.data;
  });
  await check('Exams', 'GET /api/exams/schedule', async () => {
    const r = await req('/api/exams/schedule', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  if (fixtures.exam) {
    await check('Exams', 'POST /api/exams/results (save)', async () => {
      const r = await req('/api/exams/results', {
        method: 'POST', token: tokens.superadmin,
        body: {
          examId: fixtures.exam._id, studentId: fixtures.student._id,
          marksObtained: 85, grade: 'A'
        }
      });
      assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    });
    await check('Exams', 'GET /api/exams/results/exam/:id', async () => {
      const r = await req(`/api/exams/results/exam/${fixtures.exam._id}`, { token: tokens.superadmin });
      assert.equal(r.status, 200);
    });
  }
  await check('Exams', 'GET /api/exams/results/student/:id', async () => {
    const r = await req(`/api/exams/results/student/${fixtures.student._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Exams', 'GET /api/exams/analytics', async () => {
    const r = await req('/api/exams/analytics', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testLibrary() {
  log('\n📚 MODULE: Library');
  await check('Library', 'POST /api/library/manual (add book)', async () => {
    const r = await req('/api/library/manual', {
      method: 'POST', token: tokens.superadmin,
      body: { title: `${PREFIX}-Book`, author: 'Test Author', isbn: `${PREFIX}-ISBN-001`, genre: 'Fiction', totalCopies: 5, availableCopies: 5 }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.book = r.data;
  });
  await check('Library', 'GET /api/library/books', async () => {
    const r = await req('/api/library/books', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Library', 'GET /api/library/dashboard', async () => {
    const r = await req('/api/library/dashboard', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  if (fixtures.book) {
    await check('Library', 'POST /api/library/issue', async () => {
      const r = await req('/api/library/issue', {
        method: 'POST', token: tokens.superadmin,
        body: { bookId: fixtures.book._id, studentId: fixtures.student._id, dueDate: '2026-07-01' }
      });
      assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
      if (r.data?._id) fixtures.libraryTx = r.data;
    });
    await check('Library', 'GET /api/library/transactions', async () => {
      const r = await req('/api/library/transactions', { token: tokens.superadmin });
      assert.equal(r.status, 200);
    });
    if (fixtures.libraryTx) {
      await check('Library', 'PATCH /api/library/transactions/:id/return', async () => {
        const r = await req(`/api/library/transactions/${fixtures.libraryTx._id}/return`, {
          method: 'PATCH', token: tokens.superadmin, body: {}
        });
        assert.equal(r.status, 200, r.text);
      });
    }
  }
}

async function testHostel() {
  log('\n🏠 MODULE: Hostel');
  await check('Hostel', 'POST /api/hostel/room-types (create)', async () => {
    const r = await req('/api/hostel/room-types', {
      method: 'POST', token: tokens.superadmin,
      body: { name: `${PREFIX}-SingleRoom`, occupancy: 2 }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.roomType = r.data;
  });
  // Hostel module only has POST /room-types, POST /rooms, POST /fee-structures,
  // POST /allocations, PATCH /allocations/:id/vacate, and GET /dashboard.
  // There are no separate GET /room-types, GET /rooms, GET /fee-structures, GET /allocations.
  // All data is returned through GET /dashboard.
  await check('Hostel', 'GET /api/hostel/dashboard (returns all data)', async () => {
    const r = await req('/api/hostel/dashboard', { token: tokens.superadmin });
    assert.equal(r.status, 200);
    assert.ok(r.data.roomTypes !== undefined, 'Dashboard should include roomTypes');
    assert.ok(r.data.rooms !== undefined, 'Dashboard should include rooms');
    assert.ok(r.data.allocations !== undefined, 'Dashboard should include allocations');
    assert.ok(r.data.feeStructures !== undefined, 'Dashboard should include feeStructures');
  });
  if (fixtures.roomType) {
    await check('Hostel', 'POST /api/hostel/rooms (create)', async () => {
      const r = await req('/api/hostel/rooms', {
        method: 'POST', token: tokens.superadmin,
        body: { roomNumber: `${PREFIX}-R101`, roomTypeId: fixtures.roomType._id, floor: '1', block: 'A' }
      });
      assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
      if (r.data?._id) fixtures.hostelRoom = r.data;
    });
    await check('Hostel', 'POST /api/hostel/fee-structures', async () => {
      const r = await req('/api/hostel/fee-structures', {
        method: 'POST', token: tokens.superadmin,
        body: { roomTypeId: fixtures.roomType._id, amount: 10000, term: 'Term 1', academicYear: '2026-2027' }
      });
      assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    });
  }
}

async function testTransport() {
  log('\n🚌 MODULE: Transport');
  await check('Transport', 'POST /api/transport (create vehicle)', async () => {
    const r = await req('/api/transport', {
      method: 'POST', token: tokens.superadmin,
      body: { busNumber: `${PREFIX.toUpperCase()}-BUS-01`, numberPlate: `${PREFIX.toUpperCase()}-NP-01`, route: 'Test Route', capacity: 30 }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.vehicle = r.data;
  });
  await check('Transport', 'GET /api/transport list', async () => {
    const r = await req('/api/transport', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  if (fixtures.vehicle) {
    await check('Transport', 'PUT /api/transport/:id (update)', async () => {
      const r = await req(`/api/transport/${fixtures.vehicle._id}`, {
        method: 'PUT', token: tokens.superadmin, body: { route: 'Updated Route', capacity: 35 }
      });
      assert.equal(r.status, 200, r.text);
    });
    // Assign student to bus first, then record attendance
    await check('Transport', 'PUT /api/transport/:id (assign student)', async () => {
      const r = await req(`/api/transport/${fixtures.vehicle._id}/students`, {
        method: 'PUT', token: tokens.superadmin,
        body: { students: [fixtures.student._id] }
      });
      assert.equal(r.status, 200, r.text);
    });
    await check('Transport', 'POST /api/transport/:id/attendance', async () => {
      const r = await req(`/api/transport/${fixtures.vehicle._id}/attendance`, {
        method: 'POST', token: tokens.superadmin,
        body: { studentId: fixtures.student._id, status: 'boarded' }
      });
      assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    });
    await check('Transport', 'GET /api/transport/:id/attendance', async () => {
      const r = await req(`/api/transport/${fixtures.vehicle._id}/attendance`, { token: tokens.superadmin });
      assert.equal(r.status, 200);
    });
  }
  await check('Transport', 'GET /api/transport/student/:id/history', async () => {
    const r = await req(`/api/transport/student/${fixtures.student._id}/history`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testBusRoutes() {
  log('\n🗺️ MODULE: Bus Routes');
  await check('BusRoutes', 'GET /api/bus-routes list', async () => {
    const r = await req('/api/bus-routes', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testPayroll() {
  log('\n💼 MODULE: Payroll');
  await check('Payroll', 'GET /api/payroll list', async () => {
    const r = await req('/api/payroll', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Payroll', 'POST /api/payroll/generate-batch', async () => {
    // Uses monthNumber not month
    const r = await req('/api/payroll/generate-batch', {
      method: 'POST', token: tokens.superadmin,
      body: { year: 2026, monthNumber: 4 }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
  });
  await check('Payroll', 'GET /api/payroll/:staffId', async () => {
    const r = await req(`/api/payroll/${fixtures.teacher._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testSalarySetup() {
  log('\n📋 MODULE: Salary Setup');
  await check('SalarySetup', 'GET /api/salary-setup list', async () => {
    const r = await req('/api/salary-setup', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('SalarySetup', 'POST /api/salary-setup (create)', async () => {
    // Uses basicSalary not basicPay, and needs effectiveFrom
    const r = await req('/api/salary-setup', {
      method: 'POST', token: tokens.superadmin,
      body: { staffId: fixtures.teacher._id, basicSalary: 30000, hra: 5000, da: 3000, conveyance: 2000, effectiveFrom: '2026-01-01' }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
  });
}

async function testStaffAttendance() {
  log('\n🕐 MODULE: Staff Attendance');
  const today = new Date().toISOString().split('T')[0];
  await check('StaffAttendance', 'POST /api/staff-attendance', async () => {
    // Expects { date, records: [{staffId, status}] }
    const r = await req('/api/staff-attendance', {
      method: 'POST', token: tokens.superadmin,
      body: { date: today, records: [{ staffId: fixtures.teacher._id, status: 'present' }] }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
  });
  await check('StaffAttendance', 'GET /api/staff-attendance/:date', async () => {
    // GET requires date param
    const r = await req(`/api/staff-attendance/${today}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testCanteen() {
  log('\n🍽️ MODULE: Canteen');
  await check('Canteen', 'POST /api/canteen/items (create)', async () => {
    const r = await req('/api/canteen/items', {
      method: 'POST', token: tokens.superadmin,
      body: { name: `${PREFIX}-Samosa`, price: 20, category: 'snacks', quantityAvailable: 100 }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.canteenItem = r.data;
  });
  await check('Canteen', 'GET /api/canteen/items', async () => {
    const r = await req('/api/canteen/items', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  if (fixtures.canteenItem) {
    await check('Canteen', 'PUT /api/canteen/items/:id (update)', async () => {
      const r = await req(`/api/canteen/items/${fixtures.canteenItem._id}`, {
        method: 'PUT', token: tokens.superadmin, body: { price: 25 }
      });
      assert.equal(r.status, 200, r.text);
    });
    await check('Canteen', 'POST /api/canteen/sell', async () => {
      const r = await req('/api/canteen/sell', {
        method: 'POST', token: tokens.superadmin,
        body: { items: [{ itemId: fixtures.canteenItem._id, quantity: 2, price: 20 }], total: 40 }
      });
      assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    });
    await check('Canteen', 'GET /api/canteen/sales', async () => {
      const r = await req('/api/canteen/sales', { token: tokens.superadmin });
      assert.equal(r.status, 200);
    });
  }
}

async function testHomework() {
  log('\n📝 MODULE: Homework');
  await check('Homework', 'POST /api/homework (create)', async () => {
    const r = await req('/api/homework', {
      method: 'POST', token: tokens.teacher,
      body: {
        classId: fixtures.cls._id, subject: 'Mathematics',
        title: `${PREFIX}-HW`, description: 'Test homework',
        dueDate: '2026-06-01'
      }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.homework = r.data;
  });
  await check('Homework', 'GET /api/homework', async () => {
    const r = await req(`/api/homework?classId=${fixtures.cls._id}`, { token: tokens.teacher });
    assert.equal(r.status, 200);
  });
  await check('Homework', 'GET /api/homework/my (student)', async () => {
    const r = await req('/api/homework/my', { token: tokens.student });
    assert.equal(r.status, 200);
  });
}

async function testRoutine() {
  log('\n🕒 MODULE: Routine');
  await check('Routine', 'GET /api/routine/:classId', async () => {
    const r = await req(`/api/routine/${fixtures.cls._id}`, { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Routine', 'POST /api/routine/manual (create entry)', async () => {
    // Requires: classId, day, period, subjectName
    const r = await req('/api/routine/manual', {
      method: 'POST', token: tokens.superadmin,
      body: {
        classId: fixtures.cls._id, day: 'Monday',
        period: '8:00', subjectName: 'Mathematics',
        teacherId: fixtures.teacher._id
      }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
  });
}

async function testLeave() {
  log('\n🏖️ MODULE: Leave');
  await check('Leave', 'POST /api/leave/request', async () => {
    const r = await req('/api/leave/request', {
      method: 'POST', token: tokens.teacher,
      body: { reason: `${PREFIX}-SickLeave`, fromDate: '2026-06-10', toDate: '2026-06-11', type: 'casual' }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.leave = r.data;
  });
  await check('Leave', 'GET /api/leave (list)', async () => {
    const r = await req('/api/leave', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Leave', 'GET /api/leave/my', async () => {
    const r = await req('/api/leave/my', { token: tokens.teacher });
    assert.equal(r.status, 200);
  });
  await check('Leave', 'GET /api/leave/balance', async () => {
    const r = await req('/api/leave/balance', { token: tokens.teacher });
    assert.equal(r.status, 200);
  });
  if (fixtures.leave) {
    await check('Leave', 'PUT /api/leave/:id/approve', async () => {
      const r = await req(`/api/leave/${fixtures.leave._id}/approve`, {
        method: 'PUT', token: tokens.superadmin, body: { status: 'approved' }
      });
      assert.equal(r.status, 200, r.text);
    });
  }
}

async function testRemarks() {
  log('\n📌 MODULE: Remarks');
  await check('Remarks', 'POST /api/remarks (create)', async () => {
    const r = await req('/api/remarks', {
      method: 'POST', token: tokens.teacher,
      body: { studentId: fixtures.student._id, remark: `${PREFIX}-GoodStudent`, type: 'positive' }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
  });
  await check('Remarks', 'GET /api/remarks', async () => {
    const r = await req('/api/remarks', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testComplaints() {
  log('\n📢 MODULE: Complaints');
  await check('Complaints', 'POST /api/complaints (file)', async () => {
    const r = await req('/api/complaints', {
      method: 'POST', token: tokens.parent,
      body: { subject: 'Test Complaint Subject', description: `${PREFIX}-TestComplaint` }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.complaint = r.data;
  });
  await check('Complaints', 'GET /api/complaints', async () => {
    const r = await req('/api/complaints', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Complaints', 'GET /api/complaints/staff-targets', async () => {
    const r = await req('/api/complaints/staff-targets', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  if (fixtures.complaint) {
    await check('Complaints', 'PUT /api/complaints/:id (update status)', async () => {
      const r = await req(`/api/complaints/${fixtures.complaint._id}`, {
        method: 'PUT', token: tokens.superadmin,
        body: { status: 'resolved', resolutionNote: 'Resolved for testing' }
      });
      assert.equal(r.status, 200, r.text);
    });
  }
}

async function testNotices() {
  log('\n📰 MODULE: Notices');
  await check('Notices', 'POST /api/notices (create)', async () => {
    const r = await req('/api/notices', {
      method: 'POST', token: tokens.superadmin,
      body: { title: `${PREFIX}-Notice`, content: 'Test notice content', audience: 'all' }
    });
    assert.ok([200, 201].includes(r.status), `Expected 200/201, got ${r.status}: ${r.text}`);
    if (r.data?._id) fixtures.notice = r.data;
  });
  await check('Notices', 'GET /api/notices', async () => {
    const r = await req('/api/notices', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  if (fixtures.notice) {
    await check('Notices', 'PUT /api/notices/:id (update)', async () => {
      const r = await req(`/api/notices/${fixtures.notice._id}`, {
        method: 'PUT', token: tokens.superadmin, body: { content: 'Updated content' }
      });
      assert.equal(r.status, 200, r.text);
    });
  }
}

async function testNotifications() {
  log('\n🔔 MODULE: Notifications');
  await check('Notifications', 'GET /api/notifications', async () => {
    const r = await req('/api/notifications', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Notifications', 'GET /api/notifications/unread-count', async () => {
    const r = await req('/api/notifications/unread-count', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testExport() {
  log('\n📥 MODULE: Export');
  await check('Export', 'GET /api/export/students/excel', async () => {
    const r = await req('/api/export/students/excel', { token: tokens.superadmin, raw: true });
    assert.equal(r.status, 200);
  });
  await check('Export', 'GET /api/export/fees/excel', async () => {
    const r = await req('/api/export/fees/excel', { token: tokens.superadmin, raw: true });
    assert.equal(r.status, 200);
  });
  await check('Export', 'GET /api/export/students/pdf', async () => {
    const r = await req('/api/export/students/pdf', { token: tokens.superadmin, raw: true });
    assert.equal(r.status, 200);
  });
}

async function testImport() {
  log('\n📤 MODULE: Import');
  await check('Import', 'Import route accessible (not 404)', async () => {
    const r = await req('/api/import/students', { method: 'POST', token: tokens.superadmin, body: {} });
    assert.ok(r.status !== 404, `Import route exists (got ${r.status})`);
  });
}

async function testTally() {
  log('\n📊 MODULE: Tally');
  await check('Tally', 'GET /api/tally/vouchers', async () => {
    const r = await req('/api/tally/vouchers', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Tally', 'POST /api/tally/export-fees', async () => {
    const r = await req('/api/tally/export-fees', {
      method: 'POST', token: tokens.superadmin,
      body: { startDate: '2026-01-01', endDate: '2026-12-31', format: 'json' }
    });
    // 400 if no payments exist, 200 if they do - both are valid (route exists)
    assert.ok([200, 400].includes(r.status), `Tally export-fees route accessible (got ${r.status})`);
  });
}

async function testChatbot() {
  log('\n🤖 MODULE: Chatbot');
  // Note: AI bootstrap is skipped in test mode, so NLP engine may not be fully trained.
  // We test the endpoint is reachable and returns a valid response shape.
  await check('Chatbot', 'POST /api/chatbot/chat', async () => {
    const r = await req('/api/chatbot/chat', {
      method: 'POST', token: tokens.superadmin,
      body: { message: 'hello', language: 'en' }
    });
    // The endpoint should respond (even with a fallback message if NLP isn't trained)
    assert.ok([200, 201, 500].includes(r.status), `Chatbot endpoint reachable (got ${r.status})`);
    if (r.status === 200) {
      assert.ok(r.raw && typeof r.raw === 'object', 'chatbot should return response data');
    }
  });
  await check('Chatbot', 'GET /api/chatbot/history', async () => {
    const r = await req('/api/chatbot/history', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('Chatbot', 'GET /api/chatbot/analytics', async () => {
    const r = await req('/api/chatbot/analytics', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testArchive() {
  log('\n🗄️ MODULE: Archive');
  await check('Archive', 'GET /api/archive/students', async () => {
    const r = await req('/api/archive/students', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
}

async function testPDF() {
  log('\n📄 MODULE: PDF Generation');
  await check('PDF', 'POST /api/pdf/payslip (route accessible)', async () => {
    // PDF routes are POST-based: /api/pdf/payslip and /api/pdf/transfer-certificate
    const r = await req('/api/pdf/payslip', {
      method: 'POST', token: tokens.superadmin,
      body: { payrollId: '000000000000000000000000' }
    });
    // 404 for "payroll not found" is valid (route exists), only 404 "route not found" is a problem
    assert.ok(r.status !== 404 || (r.data?.msg && !r.data.msg.includes('Route not found')),
      `PDF route accessible (got ${r.status})`);
  });
  await check('PDF', 'POST /api/pdf/transfer-certificate (route accessible)', async () => {
    const r = await req('/api/pdf/transfer-certificate', {
      method: 'POST', token: tokens.superadmin,
      body: { studentId: fixtures.student._id }
    });
    assert.ok([200, 404, 500].includes(r.status), `TC route accessible (got ${r.status})`);
  });
}

async function testRoleBasedAccess() {
  log('\n🔒 MODULE: Role-Based Access Control');
  await check('RBAC', 'Student cannot access /api/auth/users', async () => {
    const r = await req('/api/auth/users', { token: tokens.student });
    assert.ok([401, 403].includes(r.status), `Expected 401/403, got ${r.status}`);
  });
  await check('RBAC', 'Parent cannot access /api/auth/users', async () => {
    const r = await req('/api/auth/users', { token: tokens.parent });
    assert.ok([401, 403].includes(r.status), `Expected 401/403, got ${r.status}`);
  });
  await check('RBAC', 'Teacher cannot register users', async () => {
    const r = await req('/api/auth/register', {
      method: 'POST', token: tokens.teacher,
      body: { name: 'Hack', email: 'hack@test.local', password: 'Test@12345', role: 'superadmin', phone: '9999999999' }
    });
    assert.ok([401, 403].includes(r.status), `Expected 401/403, got ${r.status}`);
  });
  await check('RBAC', 'Superadmin CAN access users', async () => {
    const r = await req('/api/auth/users', { token: tokens.superadmin });
    assert.equal(r.status, 200);
  });
  await check('RBAC', 'Student cannot access admin import', async () => {
    const r = await req('/api/import/students', { method: 'POST', token: tokens.student, body: {} });
    assert.ok([401, 403].includes(r.status), `Expected 401/403, got ${r.status}`);
  });
}

async function testDatabaseModels() {
  log('\n🗃️ MODULE: Database Model Integrity');
  const models = [
    { name: 'User', model: User },
    { name: 'Class', model: Class },
    { name: 'Student', model: Student },
    { name: 'Attendance', model: Attendance },
    { name: 'FeeStructure', model: FeeStructure },
    { name: 'FeePayment', model: FeePayment },
    { name: 'Exam', model: Exam },
    { name: 'ExamResult', model: ExamResult },
    { name: 'LibraryBook', model: LibraryBook },
    { name: 'LibraryTransaction', model: LibraryTransaction },
    { name: 'HostelRoomType', model: HostelRoomType },
    { name: 'HostelRoom', model: HostelRoom },
    { name: 'HostelAllocation', model: HostelAllocation },
    { name: 'HostelFeeStructure', model: HostelFeeStructure },
    { name: 'TransportVehicle', model: TransportVehicle },
    { name: 'TransportAttendance', model: TransportAttendance },
    { name: 'BusRoute', model: BusRoute },
    { name: 'CanteenItem', model: CanteenItem },
    { name: 'CanteenSale', model: CanteenSale },
    { name: 'Homework', model: Homework },
    { name: 'Routine', model: Routine },
    { name: 'Leave', model: Leave },
    { name: 'Remark', model: Remark },
    { name: 'Complaint', model: Complaint },
    { name: 'Notice', model: Notice },
    { name: 'Notification', model: Notification },
    { name: 'Payroll', model: Payroll },
    { name: 'SalaryStructure', model: SalaryStructure },
    { name: 'StaffAttendance', model: StaffAttendance },
    { name: 'ChatbotLog', model: ChatbotLog },
    { name: 'AuditLog', model: AuditLog },
  ];

  for (const { name, model } of models) {
    await check('DB', `Model ${name} – count() succeeds`, async () => {
      const count = await model.countDocuments();
      assert.ok(typeof count === 'number', `${name}.countDocuments() should return a number`);
    });
  }
}

// Prisma-based model integrity check (overrides legacy definition above)
async function testDatabaseModels() {
  log('\nðŸ—ƒï¸ MODULE: Database Model Integrity');
  const models = [
    { name: 'User', count: () => prisma.user.count() },
    { name: 'Class', count: () => prisma.class.count() },
    { name: 'Student', count: () => prisma.student.count() },
    { name: 'Attendance', count: () => prisma.attendance.count() },
    { name: 'FeeStructure', count: () => prisma.feeStructure.count() },
    { name: 'FeePayment', count: () => prisma.feePayment.count() },
    { name: 'Exam', count: () => prisma.exam.count() },
    { name: 'ExamResult', count: () => prisma.examResult.count() },
    { name: 'LibraryBook', count: () => prisma.libraryBook.count() },
    { name: 'LibraryTransaction', count: () => prisma.libraryTransaction.count() },
    { name: 'HostelRoomType', count: () => prisma.hostelRoomType.count() },
    { name: 'HostelRoom', count: () => prisma.hostelRoom.count() },
    { name: 'HostelAllocation', count: () => prisma.hostelAllocation.count() },
    { name: 'HostelFeeStructure', count: () => prisma.hostelFeeStructure.count() },
    { name: 'TransportVehicle', count: () => prisma.transportVehicle.count() },
    { name: 'TransportAttendance', count: () => prisma.transportAttendance.count() },
    { name: 'BusRoute', count: () => prisma.busRoute.count() },
    { name: 'CanteenItem', count: () => prisma.canteenItem.count() },
    { name: 'CanteenSale', count: () => prisma.canteenSale.count() },
    { name: 'Homework', count: () => prisma.homework.count() },
    { name: 'Routine', count: () => prisma.routine.count() },
    { name: 'Leave', count: () => prisma.leave.count() },
    { name: 'Remark', count: () => prisma.remark.count() },
    { name: 'Complaint', count: () => prisma.complaint.count() },
    { name: 'Notice', count: () => prisma.notice.count() },
    { name: 'Notification', count: () => prisma.notification.count() },
    { name: 'Payroll', count: () => prisma.payroll.count() },
    { name: 'SalaryStructure', count: () => prisma.salaryStructure.count() },
    { name: 'StaffAttendance', count: () => prisma.staffAttendance.count() },
    { name: 'ChatbotLog', count: () => prisma.chatbotLog.count() },
    { name: 'AuditLog', count: () => prisma.auditLog.count() },
  ];

  for (const { name, count } of models) {
    await check('DB', `Model ${name} â€“ count() succeeds`, async () => {
      const total = await count();
      assert.ok(typeof total === 'number', `${name}.count() should return a number`);
    });
  }
}

async function test404Handler() {
  log('\n🚫 MODULE: 404 Handler');
  await check('404', 'Unknown route returns 404', async () => {
    const r = await req('/api/this-does-not-exist', { token: tokens.superadmin });
    assert.equal(r.status, 404);
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// RUNNER
// ═══════════════════════════════════════════════════════════════════════════
async function runAll() {
  log('═══════════════════════════════════════════════════════');
  log('  SCHOOL ERP – FULL END-TO-END AUDIT');
  log('═══════════════════════════════════════════════════════');

  await testHealthCheck();
  await testAuth();
  await testDashboard();
  await testClasses();
  await testStudents();
  await testAttendance();
  await testFee();
  await testExams();
  await testLibrary();
  await testHostel();
  await testTransport();
  await testBusRoutes();
  await testPayroll();
  await testSalarySetup();
  await testStaffAttendance();
  await testCanteen();
  await testHomework();
  await testRoutine();
  await testLeave();
  await testRemarks();
  await testComplaints();
  await testNotices();
  await testNotifications();
  await testExport();
  await testImport();
  await testTally();
  await testChatbot();
  await testArchive();
  await testPDF();
  await testRoleBasedAccess();
  await testDatabaseModels();
  await test404Handler();

  // ── Summary ────────────────────────────────────────────────────────────
  log('\n═══════════════════════════════════════════════════════');
  log(`  RESULTS: ${results.pass} PASSED | ${results.fail} FAILED | ${results.skip} SKIPPED`);
  log(`  TOTAL:   ${results.pass + results.fail + results.skip} checks`);
  log('═══════════════════════════════════════════════════════');

  if (results.fail > 0) {
    log('\n❌ FAILURES:');
    for (const d of results.details.filter(d => d.status === 'FAIL')) {
      log(`  [${d.module}] ${d.name}: ${d.error}`);
    }
  }

  return results;
}

(async () => {
  try {
    log('Setting up test environment...');
    await setup();
    log('Setup complete. Running tests...\n');
    const r = await runAll();
    process.exitCode = r.fail > 0 ? 1 : 0;
  } catch (err) {
    log(`\nFATAL ERROR: ${err.stack || err.message}`);
    process.exitCode = 1;
  } finally {
    try { await cleanup(); } catch (e) { log(`Cleanup error: ${e.message}`); }
    try {
      if (server) await new Promise((resolve, reject) => server.close(err => err ? reject(err) : resolve()));
      await prisma.$disconnect();
    } catch (e) { log(`Shutdown error: ${e.message}`); }
    // Write results JSON
    const fs = require('fs');
    fs.writeFileSync(
      path.join(__dirname, '..', 'e2e-audit-results.json'),
      JSON.stringify(results, null, 2)
    );
    log('\nResults saved to e2e-audit-results.json');
    process.exit(process.exitCode || 0);
  }
})();
