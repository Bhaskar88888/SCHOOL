const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { toLegacyClass, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function getCurrentAcademicYear(now = new Date()) {
  const year = now.getFullYear();
  const academicStartYear = now.getMonth() >= 3 ? year : year - 1;
  return `${academicStartYear}-${academicStartYear + 1}`;
}

function getAcademicYearStart(now = new Date()) {
  const year = now.getFullYear();
  const academicStartYear = now.getMonth() >= 3 ? year : year - 1;
  return new Date(academicStartYear, 3, 1);
}

router.use(auth, roleCheck('superadmin', 'accounts'));

router.get('/students', async (req, res) => {
  try {
    const currentAcademicYear = getCurrentAcademicYear();
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = {
      academicYear: { not: currentAcademicYear },
    };

    const [total, students] = await Promise.all([
      prisma.student.count({ where }),
      prisma.student.findMany({
        where,
        include: {
          class: { select: { id: true, name: true, section: true } },
        },
        orderBy: [
          { academicYear: 'desc' },
          { createdAt: 'desc' },
        ],
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: students.map((student) => ({
      _id: student.id,
      type: 'student',
      name: student.name,
      admissionNo: student.admissionNo,
      studentId: student.studentId || '',
      academicYear: student.academicYear || '',
      className: student.class?.name || '',
      section: student.section || student.class?.section || '',
      parentPhone: student.parentPhone || '',
      date: student.updatedAt || student.createdAt,
      status: 'archived',
      reason: 'Previous academic year',
      })),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-academicYear,-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/staff', async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = {
      role: { in: ['teacher', 'staff', 'hr', 'accounts', 'canteen', 'conductor', 'driver'] },
      isActive: false,
    };

    const [total, staff] = await Promise.all([
      prisma.user.count({ where }),
      prisma.user.findMany({
        where,
        orderBy: [
          { updatedAt: 'desc' },
          { createdAt: 'desc' },
        ],
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: staff.map((member) => ({
      _id: member.id,
      type: 'staff',
      name: member.name,
      employeeId: member.employeeId || '',
      role: member.role,
      department: member.department || '',
      designation: member.designation || '',
      academicYear: '',
      date: member.updatedAt || member.createdAt,
      status: 'archived',
      reason: 'Inactive staff account',
      })),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-updatedAt,-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/fees', async (req, res) => {
  try {
    const cutoff = getAcademicYearStart();
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { paymentDate: { lt: cutoff } };

    const [total, payments] = await Promise.all([
      prisma.feePayment.count({ where }),
      prisma.feePayment.findMany({
        where,
        include: {
          student: { select: { id: true, name: true, admissionNo: true } },
          collectedBy: { select: { id: true, name: true } },
        },
        orderBy: { paymentDate: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: payments.map((payment) => ({
      _id: payment.id,
      type: 'fee',
      name: payment.student?.name || 'Unknown Student',
      admissionNo: payment.student?.admissionNo || '',
      receiptNo: payment.receiptNo,
      feeType: payment.feeType || 'General',
      amountPaid: payment.amountPaid,
      paymentMode: payment.paymentMode,
      collectedBy: payment.collectedBy?.name || '',
      academicYear: payment.academicYear || '',
      date: payment.paymentDate || payment.date,
      status: 'archived',
      reason: 'Previous academic period',
      })),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-paymentDate' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/exams', async (req, res) => {
  try {
    const now = new Date();
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { date: { lt: now } };

    const [total, exams] = await Promise.all([
      prisma.exam.count({ where }),
      prisma.exam.findMany({
        where,
        include: { class: { select: { id: true, name: true, section: true } } },
        orderBy: { date: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: exams.map((exam) => ({
      _id: exam.id,
      type: 'exam',
      name: exam.name,
      subject: exam.subject,
      examType: exam.examType,
      className: exam.class?.name || '',
      section: exam.class?.section || '',
      roomNumber: exam.roomNumber || '',
      totalMarks: exam.totalMarks,
      academicYear: '',
      date: exam.date,
      status: 'archived',
      reason: 'Completed exam',
      })),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-date' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/attendance', async (req, res) => {
  try {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { date: { lt: today } };

    const [total, records] = await Promise.all([
      prisma.attendance.count({ where }),
      prisma.attendance.findMany({
        where,
        include: {
          student: { select: { id: true, name: true, admissionNo: true } },
          class: { select: { id: true, name: true, section: true } },
          teacher: { select: { id: true, name: true } },
        },
        orderBy: { date: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: records.map((record) => ({
      _id: record.id,
      type: 'attendance',
      name: record.student?.name || 'Unknown Student',
      admissionNo: record.student?.admissionNo || '',
      className: record.class?.name || '',
      section: record.class?.section || '',
      subject: record.subject || '',
      teacherName: record.teacher?.name || '',
      attendanceStatus: record.status,
      academicYear: '',
      date: record.date,
      status: 'archived',
      reason: 'Historical attendance',
      })),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-date' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

module.exports = router;
