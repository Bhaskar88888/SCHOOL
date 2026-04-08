process.env.NODE_ENV = 'test';

const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

if (process.env.DATABASE_URL_TEST) {
  process.env.DATABASE_URL = process.env.DATABASE_URL_TEST;
} else {
  throw new Error('DATABASE_URL_TEST is required for integration tests.');
}

const assert = require('node:assert/strict');
const bcrypt = require('bcryptjs');

const connectDB = require('../config/db');
const prisma = require('../config/prisma');
const { startServer } = require('../server');

const FIXTURE_PREFIX = 'apitest';

let baseUrl = '';
let server;
let tokens = {};
let fixtures = {};

function log(message) {
  process.stdout.write(`${message}\n`);
}

async function request(pathname, { method = 'GET', token, body } = {}) {
  const response = await fetch(`${baseUrl}${pathname}`, {
    method,
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const text = await response.text();
  let data = null;

  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text;
  }

  return { status: response.status, data, text };
}

async function login(email, password) {
  const result = await request('/api/auth/login', {
    method: 'POST',
    body: { email, password },
  });

  assert.equal(result.status, 200, `login failed for ${email}: ${result.text}`);
  assert.ok(result.data.token, `missing token for ${email}`);
  return result.data.token;
}

async function hashPassword(password) {
  const salt = await bcrypt.genSalt(10);
  return bcrypt.hash(password, salt);
}

async function createUser(data) {
  await prisma.user.deleteMany({ where: { email: data.email } });
  return prisma.user.create({
    data: {
      ...data,
      password: await hashPassword(data.password),
    },
  });
}

async function pushLinkedStudent(parentId, studentId) {
  const parent = await prisma.user.findUnique({
    where: { id: parentId },
    select: { linkedStudentIds: true },
  });

  const next = Array.isArray(parent?.linkedStudentIds)
    ? parent.linkedStudentIds.map(String)
    : [];

  if (!next.includes(String(studentId))) {
    next.push(String(studentId));
  }

  await prisma.user.update({
    where: { id: parentId },
    data: { linkedStudentIds: next },
  });
}

async function cleanupFixtures() {
  const ids = Object.values(fixtures).map((item) => item?.id).filter(Boolean);

  await prisma.transportAttendance.deleteMany({
    where: {
      OR: [
        { busId: { in: ids } },
        { studentId: { in: ids } },
      ],
    },
  });

  await prisma.transportVehicle.deleteMany({
    where: { busNumber: { startsWith: FIXTURE_PREFIX } },
  });

  await prisma.homework.deleteMany({
    where: { title: { startsWith: FIXTURE_PREFIX } },
  });

  await prisma.notification.deleteMany({
    where: { title: { startsWith: FIXTURE_PREFIX } },
  });

  await prisma.student.deleteMany({
    where: { admissionNo: { startsWith: FIXTURE_PREFIX } },
  });

  await prisma.class.deleteMany({
    where: { name: { startsWith: FIXTURE_PREFIX } },
  });

  await prisma.user.deleteMany({
    where: { email: { startsWith: `${FIXTURE_PREFIX}.` } },
  });
}

async function seedFixtures() {
  await cleanupFixtures();

  fixtures.superadmin = await createUser({
    name: 'API Test Superadmin',
    email: `${FIXTURE_PREFIX}.superadmin@test.local`,
    password: 'test12345',
    role: 'superadmin',
    phone: '9000000001',
  });

  fixtures.teacher = await createUser({
    name: 'API Test Teacher',
    email: `${FIXTURE_PREFIX}.teacher@test.local`,
    password: 'test12345',
    role: 'teacher',
    phone: '9000000002',
    employeeId: `${FIXTURE_PREFIX.toUpperCase()}-T01`,
  });

  fixtures.parent = await createUser({
    name: 'API Test Parent',
    email: `${FIXTURE_PREFIX}.parent@test.local`,
    password: 'test12345',
    role: 'parent',
    phone: '9000000003',
  });

  fixtures.otherParent = await createUser({
    name: 'API Test Other Parent',
    email: `${FIXTURE_PREFIX}.otherparent@test.local`,
    password: 'test12345',
    role: 'parent',
    phone: '9000000004',
  });

  fixtures.studentUser = await createUser({
    name: 'API Test Student',
    email: `${FIXTURE_PREFIX}.student@test.local`,
    password: 'test12345',
    role: 'student',
    phone: '9000000005',
  });

  fixtures.otherStudentUser = await createUser({
    name: 'API Test Other Student',
    email: `${FIXTURE_PREFIX}.otherstudent@test.local`,
    password: 'test12345',
    role: 'student',
    phone: '9000000006',
  });

  fixtures.classRecord = await prisma.class.create({
    data: {
      name: `${FIXTURE_PREFIX}-10`,
      section: 'A',
      sections: ['A'],
      classTeacherId: fixtures.teacher.id,
      subjects: {
        create: [{ subject: 'Mathematics', name: 'Mathematics', teacherId: fixtures.teacher.id }],
      },
      capacity: 40,
      academicYear: '2026-2027',
    },
  });

  fixtures.student = await prisma.student.create({
    data: {
      userId: fixtures.studentUser.id,
      name: 'API Test Student',
      admissionNo: `${FIXTURE_PREFIX}-STU-001`,
      studentId: `${FIXTURE_PREFIX.toUpperCase()}-STU-001`,
      classId: fixtures.classRecord.id,
      parentPhone: '9000000003',
      parentEmail: fixtures.parent.email,
      parentUserId: fixtures.parent.id,
      dob: new Date('2010-01-05'),
      gender: 'male',
      section: 'A',
      academicYear: '2026-2027',
    },
  });

  fixtures.otherStudent = await prisma.student.create({
    data: {
      userId: fixtures.otherStudentUser.id,
      name: 'API Test Other Student',
      admissionNo: `${FIXTURE_PREFIX}-STU-002`,
      studentId: `${FIXTURE_PREFIX.toUpperCase()}-STU-002`,
      classId: fixtures.classRecord.id,
      parentPhone: '9000000004',
      parentEmail: fixtures.otherParent.email,
      parentUserId: fixtures.otherParent.id,
      dob: new Date('2010-02-15'),
      gender: 'female',
      section: 'A',
      academicYear: '2026-2027',
    },
  });

  await pushLinkedStudent(fixtures.parent.id, fixtures.student.id);
  await pushLinkedStudent(fixtures.otherParent.id, fixtures.otherStudent.id);

  fixtures.homework = await prisma.homework.create({
    data: {
      classId: fixtures.classRecord.id,
      teacherId: fixtures.teacher.id,
      subject: 'Mathematics',
      title: `${FIXTURE_PREFIX} Homework 1`,
      description: 'Integration test homework fixture',
      dueDate: new Date('2026-05-01'),
    },
  });

  await prisma.notification.create({
    data: {
      recipientId: fixtures.superadmin.id,
      senderId: fixtures.teacher.id,
      title: `${FIXTURE_PREFIX} Notification`,
      message: 'Integration test notification',
      type: 'general',
    },
  });

  fixtures.restrictedBus = await prisma.transportVehicle.create({
    data: {
      busNumber: `${FIXTURE_PREFIX.toUpperCase()}-BUS-01`,
      numberPlate: `${FIXTURE_PREFIX.toUpperCase()}-NP-01`,
      route: 'Restricted Route',
      capacity: 30,
      students: {
        connect: [{ id: fixtures.otherStudent.id }],
      },
    },
  });

  await prisma.transportAttendance.create({
    data: {
      busId: fixtures.restrictedBus.id,
      studentId: fixtures.otherStudent.id,
      date: new Date().toISOString().split('T')[0],
      status: 'boarded',
    },
  });

  fixtures.remark = await prisma.remark.create({
    data: {
      studentId: fixtures.student.id,
      teacherId: fixtures.teacher.id,
      remark: `${FIXTURE_PREFIX} remark`,
    },
  });
}

async function closeResources() {
  if (server) {
    await new Promise((resolve, reject) => {
      server.close((error) => (error ? reject(error) : resolve()));
    });
    server = null;
  }

  await prisma.$disconnect();
}

async function setup() {
  await connectDB();
  await seedFixtures();

  const started = await startServer({
    port: 0,
    skipAiBootstrap: true,
    skipScheduler: true,
  });

  server = started.server;
  baseUrl = `http://127.0.0.1:${server.address().port}`;

  const health = await request('/api/health');
  assert.equal(health.status, 200, `health check failed: ${health.text}`);

  tokens.superadmin = await login(fixtures.superadmin.email, 'test12345');
  tokens.teacher = await login(fixtures.teacher.email, 'test12345');
  tokens.student = await login(fixtures.studentUser.email, 'test12345');
  tokens.parent = await login(fixtures.parent.email, 'test12345');
  tokens.otherParent = await login(fixtures.otherParent.email, 'test12345');
}

async function runCheck(name, fn) {
  try {
    await fn();
    log(`PASS ${name}`);
    return true;
  } catch (error) {
    log(`FAIL ${name}`);
    log(`  ${error.stack || error.message}`);
    return false;
  }
}

async function run() {
  const checks = [
    ['auth login returns token and enriched user payload', async () => {
      const result = await request('/api/auth/login', {
        method: 'POST',
        body: { email: fixtures.superadmin.email, password: 'test12345' },
      });

      assert.equal(result.status, 200);
      assert.ok(result.data.token);
      assert.equal(result.data.user.role, 'superadmin');
      assert.equal(result.data.user.email, fixtures.superadmin.email);
    }],
    ['major admin API routes return successful responses', async () => {
      const endpoints = [
        '/api/dashboard/stats',
        '/api/dashboard/quick-actions',
        '/api/classes',
        '/api/classes/stats/summary',
        '/api/classes/teachers/list',
        '/api/students',
        '/api/attendance/report/monthly',
        '/api/fee/structures',
        '/api/exams/schedule',
        '/api/notices',
        '/api/library/dashboard',
        '/api/hostel/dashboard',
        '/api/transport',
        '/api/bus-routes',
        '/api/complaints',
        `/api/homework?classId=${fixtures.classRecord.id}`,
        '/api/remarks',
        '/api/archive/students',
        '/api/notifications',
        '/api/notifications/unread-count',
      ];

      for (const endpoint of endpoints) {
        const result = await request(endpoint, { token: tokens.superadmin });
        assert.equal(result.status, 200, `${endpoint} should return 200, got ${result.status}: ${result.text}`);
      }
    }],
    ['student-facing dashboard and homework endpoints work', async () => {
      const dashboard = await request('/api/dashboard/stats', { token: tokens.student });
      const homework = await request('/api/homework/my', { token: tokens.student });
      const transport = await request('/api/transport', { token: tokens.student });

      assert.equal(dashboard.status, 200);
      assert.equal(homework.status, 200);
      assert.equal(transport.status, 200);
      const homeworkPayload = Array.isArray(homework.data)
        ? homework.data
        : homework.data?.data;
      assert.ok(Array.isArray(homeworkPayload), 'student homework response should be an array');
    }],
    ['superadmin can create, update, and delete a transport vehicle', async () => {
      const busNumber = `${FIXTURE_PREFIX.toUpperCase()}-BUS-${Date.now()}`;
      const numberPlate = `${FIXTURE_PREFIX.toUpperCase()}-NP-${Date.now()}`;

      const created = await request('/api/transport', {
        method: 'POST',
        token: tokens.superadmin,
        body: {
          busNumber,
          numberPlate,
          route: 'API Mutation Route',
          capacity: 25,
        },
      });

      assert.equal(created.status, 201, created.text);
      assert.ok(created.data._id);

      const updated = await request(`/api/transport/${created.data._id}`, {
        method: 'PUT',
        token: tokens.superadmin,
        body: {
          route: 'Updated API Mutation Route',
          capacity: 28,
        },
      });

      assert.equal(updated.status, 200, updated.text);
      assert.equal(updated.data.route, 'Updated API Mutation Route');

      const deleted = await request(`/api/transport/${created.data._id}`, {
        method: 'DELETE',
        token: tokens.superadmin,
      });

      assert.equal(deleted.status, 200, deleted.text);
    }],
    ['transport attendance persists the marker audit field', async () => {
      const marked = await request(`/api/transport/${fixtures.restrictedBus.id}/attendance`, {
        method: 'POST',
        token: tokens.superadmin,
        body: {
          studentId: fixtures.otherStudent.id,
          status: 'dropped_off',
        },
      });

      assert.equal(marked.status, 200, marked.text);

      const record = await prisma.transportAttendance.findFirst({
        where: {
          busId: fixtures.restrictedBus.id,
          studentId: fixtures.otherStudent.id,
          date: new Date().toISOString().split('T')[0],
        },
      });

      assert.ok(record, 'transport attendance record should exist');
      assert.equal(String(record.markedById), String(fixtures.superadmin.id));
    }],
    ['student cannot read unrelated transport attendance or history', async () => {
      const attendance = await request(`/api/transport/${fixtures.restrictedBus.id}/attendance`, {
        token: tokens.student,
      });
      const history = await request(`/api/transport/student/${fixtures.otherStudent.id}/history`, {
        token: tokens.student,
      });

      assert.equal(attendance.status, 403, attendance.text);
      assert.equal(history.status, 403, history.text);
    }],
    ['parent cannot read another family transport attendance or history', async () => {
      const attendance = await request(`/api/transport/${fixtures.restrictedBus.id}/attendance`, {
        token: tokens.parent,
      });
      const history = await request(`/api/transport/student/${fixtures.otherStudent.id}/history`, {
        token: tokens.parent,
      });

      assert.equal(attendance.status, 403, attendance.text);
      assert.equal(history.status, 403, history.text);
    }],
    ['students cannot export reports', async () => {
      const exportResult = await request('/api/export/students/pdf', {
        token: tokens.student,
      });

      assert.equal(exportResult.status, 403, exportResult.text);
    }],
    ['remarks access is scoped to linked students', async () => {
      const allowed = await request(`/api/remarks/student/${fixtures.student.id}`, {
        token: tokens.parent,
      });
      const denied = await request(`/api/remarks/student/${fixtures.student.id}`, {
        token: tokens.otherParent,
      });

      assert.equal(allowed.status, 200, allowed.text);
      assert.equal(denied.status, 403, denied.text);
      assert.ok(Array.isArray(allowed.data), 'remarks response should be an array');
    }],
  ];

  let passed = 0;

  for (const [name, fn] of checks) {
    if (await runCheck(name, fn)) {
      passed += 1;
    }
  }

  log('');
  log(`Summary: ${passed}/${checks.length} checks passed`);

  if (passed !== checks.length) {
    process.exitCode = 1;
  }
}

(async () => {
  try {
    await setup();
    await run();
  } catch (error) {
    log(`FATAL ${error.stack || error.message}`);
    process.exitCode = 1;
  } finally {
    try {
      await cleanupFixtures();
    } catch (cleanupError) {
      log(`CLEANUP ${cleanupError.stack || cleanupError.message}`);
      process.exitCode = 1;
    }

    try {
      await closeResources();
    } catch (closeError) {
      log(`SHUTDOWN ${closeError.stack || closeError.message}`);
      process.exitCode = 1;
    }

    process.exit(process.exitCode || 0);
  }
})();
