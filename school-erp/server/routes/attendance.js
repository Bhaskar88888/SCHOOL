const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const notificationService = require('../services/notificationService');
const smsService = require('../services/smsService');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { canTeacherAccessClass, canUserAccessStudent, getTeacherClassIds, getStudentRecordsForUser } = require('../utils/accessScope');
const { normalizeDateOnly } = require('../utils/security');
const { withLegacyId, toLegacyClass, toLegacyUser, toLegacyStudent } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyAttendance(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    classId: record.class ? toLegacyClass(record.class) : record.classId,
    teacherId: record.teacher ? toLegacyUser(record.teacher) : record.teacherId,
  });
}

function buildAttendanceListResponse(data, page, limit, total, extraQuery = {}) {
  return {
    data,
    attendance: data,
    pagination: getPaginationData(page, limit, total),
    meta: {
      query: {
        page,
        limit,
        ...extraQuery,
      },
    },
  };
}

router.get('/', auth, async (req, res) => {
  try {
    const where = {};
    const { classId, studentId, status, date } = req.query;
    if (classId) where.classId = classId;
    if (studentId) where.studentId = studentId;
    if (status) where.status = status;
    if (date) where.date = normalizeDateOnly(date);

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (classId && !allowedClassIds.includes(String(classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      where.classId = classId ? classId : { in: allowedClassIds };
    } else if (['student', 'parent'].includes(req.user.role)) {
      const students = await getStudentRecordsForUser(req.user);
      const studentIds = students.map((student) => String(student._id || student.id)).filter(Boolean);
      if (!studentIds.length) {
        return res.json(buildAttendanceListResponse([], 1, 20, 0));
      }
      if (studentId && !studentIds.includes(String(studentId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      where.studentId = studentId ? studentId : { in: studentIds };
    } else if (!['superadmin', 'accounts', 'hr'].includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, records] = await Promise.all([
      prisma.attendance.count({ where }),
      prisma.attendance.findMany({
        where,
        include: {
          student: { select: { id: true, name: true, admissionNo: true, rollNumber: true } },
          class: { select: { id: true, name: true, section: true } },
          teacher: { select: { id: true, name: true } },
        },
        orderBy: { date: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    const data = records.map(toLegacyAttendance);
    res.json(buildAttendanceListResponse(data, page, limit, total, { sort: '-date' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.post('/', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const { studentId, classId, date, status, subject } = req.body;

    if (!studentId || !status) {
      return res.status(400).json({ msg: 'studentId and status are required' });
    }

    const student = await prisma.student.findUnique({
      where: { id: studentId },
      select: {
        id: true,
        name: true,
        classId: true,
        parentUserId: true,
        parentPhone: true,
      },
    });
    if (!student) {
      return res.status(404).json({ msg: 'Student not found' });
    }

    const resolvedClassId = classId || student.classId;
    if (!resolvedClassId) {
      return res.status(400).json({ msg: 'classId is required for attendance marking' });
    }

    if (req.user.role === 'teacher' && !(await canTeacherAccessClass(req.user.id, resolvedClassId))) {
      return res.status(403).json({ msg: 'You can only mark attendance for your assigned classes.' });
    }

    const normalizedDate = normalizeDateOnly(date);
    const normalizedSubject = subject || null;

    const existing = await prisma.attendance.findUnique({
      where: {
        studentId_classId_date_subject: {
          studentId,
          classId: resolvedClassId,
          date: normalizedDate,
          subject: normalizedSubject,
        },
      },
    });

    if (existing) {
      return res.status(409).json({ msg: 'Attendance already marked for this student on this date' });
    }

    const record = await prisma.attendance.create({
      data: {
        studentId,
        classId: resolvedClassId,
        teacherId: req.user.id,
        date: normalizedDate,
        status,
        subject: normalizedSubject,
      },
      include: {
        student: { select: { id: true, name: true, admissionNo: true, rollNumber: true } },
        class: { select: { id: true, name: true, section: true } },
        teacher: { select: { id: true, name: true } },
      },
    });

    if (status === 'absent' && student.parentUserId) {
      if (student.parentPhone) {
        await smsService.send({
          to: student.parentPhone,
          message: `Dear Parent, ${student.name} was absent in ${normalizedSubject || 'class'} on ${normalizedDate.toDateString()}. - ${process.env.SCHOOL_NAME}`,
        }).catch(() => { });
      }

      await notificationService.notifyParentAttendance({
        studentId,
        status: 'absent',
        className: 'Class',
        markedBy: req.user.id,
      });
    }

    res.status(201).json({ msg: 'Attendance marked', record: toLegacyAttendance(record) });
  } catch (err) {
    console.error('Mark attendance error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/reports', auth, async (req, res) => {
  try {
    if (!['superadmin', 'accounts', 'hr', 'teacher'].includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const today = normalizeDateOnly(new Date());
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    const teacherClassIds = req.user.role === 'teacher' ? await getTeacherClassIds(req.user.id) : [];
    const baseWhere = req.user.role === 'teacher' ? { classId: { in: teacherClassIds } } : {};

    const [dailyCount, monthlyCount, absentToday] = await Promise.all([
      prisma.attendance.count({ where: { ...baseWhere, date: today } }),
      prisma.attendance.count({
        where: {
          ...baseWhere,
          date: {
            gte: normalizeDateOnly(monthStart),
            lte: normalizeDateOnly(monthEnd),
          },
        },
      }),
      prisma.attendance.count({ where: { ...baseWhere, date: today, status: 'absent' } }),
    ]);

    res.json({
      dailyCount,
      monthlyCount,
      absentToday,
      links: {
        daily: '/api/attendance/report/daily',
        monthly: '/api/attendance/report/monthly',
        defaulters: '/api/attendance/defaulters',
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// POST /api/attendance/bulk - Mark attendance for entire class
router.post('/bulk', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const { classId, date, records, subject } = req.body;

    if (!classId || !records || !Array.isArray(records)) {
      return res.status(400).json({ msg: 'classId and records array are required' });
    }

    if (req.user.role === 'teacher' && !(await canTeacherAccessClass(req.user.id, classId))) {
      return res.status(403).json({ msg: 'You can only mark attendance for your assigned classes.' });
    }

    const normalizedDate = normalizeDateOnly(date);
    const normalizedSubject = subject || null;

    const existingCount = await prisma.attendance.count({
      where: {
        classId,
        date: normalizedDate,
        subject: normalizedSubject,
      },
    });

    if (existingCount > 0) {
      return res.status(400).json({
        msg: 'Attendance already marked for this class on this date',
        existingCount,
      });
    }

    const mapped = records.map(r => ({
      studentId: r.studentId,
      classId,
      teacherId: req.user.id,
      date: normalizedDate,
      status: r.status,
      subject: normalizedSubject,
    }));

    const writeResult = await prisma.attendance.createMany({
      data: mapped,
      skipDuplicates: true,
    });

    const insertedCount = writeResult?.count || 0;

    const absentStudentIds = records.filter(r => r.status === 'absent').map(r => r.studentId);
    if (absentStudentIds.length > 0) {
      const students = await prisma.student.findMany({
        where: {
          id: { in: absentStudentIds },
          parentUserId: { not: null },
        },
        select: {
          id: true,
          name: true,
          classId: true,
        },
      });

      for (const student of students) {
        await notificationService.notifyParentAttendance({
          studentId: student.id,
          status: 'absent',
          className: 'Class',
          markedBy: req.user.id,
        });
      }
    }

    res.status(201).json({
      msg: 'Attendance marked successfully',
      count: insertedCount,
      absentCount: absentStudentIds.length,
    });
  } catch (err) {
    console.error('Bulk attendance error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

// POST /api/attendance/mark - Mark individual attendance
router.post('/mark', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const { studentId, classId, date, status, subject } = req.body;

    if (!studentId || !status) {
      return res.status(400).json({ msg: 'studentId and status are required' });
    }

    const student = await prisma.student.findUnique({
      where: { id: studentId },
      select: {
        id: true,
        name: true,
        classId: true,
        parentUserId: true,
        parentPhone: true,
      },
    });
    if (!student) {
      return res.status(404).json({ msg: 'Student not found' });
    }

    const resolvedClassId = classId || student.classId;
    if (!resolvedClassId) {
      return res.status(400).json({ msg: 'classId is required for attendance marking' });
    }

    if (req.user.role === 'teacher' && !(await canTeacherAccessClass(req.user.id, resolvedClassId))) {
      return res.status(403).json({ msg: 'You can only mark attendance for your assigned classes.' });
    }

    const normalizedDate = normalizeDateOnly(date);
    const normalizedSubject = subject || null;

    const existing = await prisma.attendance.findUnique({
      where: {
        studentId_classId_date_subject: {
          studentId,
          classId: resolvedClassId,
          date: normalizedDate,
          subject: normalizedSubject,
        },
      },
    });

    if (existing) {
      return res.status(409).json({ msg: 'Attendance already marked for this student on this date' });
    }

    const record = await prisma.attendance.create({
      data: {
        studentId,
        classId: resolvedClassId,
        teacherId: req.user.id,
        date: normalizedDate,
        status,
        subject: normalizedSubject,
      },
      include: {
        student: { select: { id: true, name: true, admissionNo: true, rollNumber: true } },
        class: { select: { id: true, name: true, section: true } },
        teacher: { select: { id: true, name: true } },
      },
    });

    if (status === 'absent' && student.parentUserId) {
      if (student.parentPhone) {
        await smsService.send({
          to: student.parentPhone,
          message: `Dear Parent, ${student.name} was absent in ${normalizedSubject || 'class'} on ${normalizedDate.toDateString()}. - ${process.env.SCHOOL_NAME}`,
        }).catch(() => { });
      }

      await notificationService.notifyParentAttendance({
        studentId,
        status: 'absent',
        className: 'Class',
        markedBy: req.user.id,
      });
    }

    res.status(201).json({ msg: 'Attendance marked', record: toLegacyAttendance(record) });
  } catch (err) {
    console.error('Mark attendance error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/attendance/:id - Update attendance record
router.put('/:id', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const { status, remarks } = req.body;

    const record = await prisma.attendance.update({
      where: { id: req.params.id },
      data: { status, remarks },
      include: {
        student: { select: { id: true, name: true } },
      },
    });

    res.json({ msg: 'Attendance updated', record: toLegacyAttendance(record) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/attendance/class/:classId/:date - Get class attendance for a date
router.get('/class/:classId/:date', auth, async (req, res) => {
  try {
    const { classId, date } = req.params;

    if (!['superadmin', 'accounts', 'hr'].includes(req.user.role)) {
      if (req.user.role === 'teacher') {
        const allowed = await canTeacherAccessClass(req.user.id, classId);
        if (!allowed) return res.status(403).json({ msg: 'Access denied' });
      } else {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    const records = await prisma.attendance.findMany({
      where: {
        classId,
        date: normalizeDateOnly(date),
      },
      include: {
        student: { select: { id: true, name: true, admissionNo: true, rollNumber: true } },
        teacher: { select: { id: true, name: true } },
      },
    });

    res.json(records.map(toLegacyAttendance));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/attendance/student/:id - Get student attendance history
router.get('/student/:id', auth, async (req, res) => {
  try {
    const hasAccess = await canUserAccessStudent(req.user, req.params.id);
    if (!hasAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const { startDate, endDate } = req.query;

    let dateFilter = {};
    if (startDate || endDate) {
      if (startDate) dateFilter.gte = normalizeDateOnly(startDate);
      if (endDate) dateFilter.lte = normalizeDateOnly(endDate);
    }

    const records = await prisma.attendance.findMany({
      where: {
        studentId: req.params.id,
        ...(Object.keys(dateFilter).length ? { date: dateFilter } : {}),
      },
      include: {
        teacher: { select: { id: true, name: true } },
        class: { select: { id: true, name: true, section: true } },
      },
      orderBy: { date: 'desc' },
      take: 100,
    });

    res.json(records.map(toLegacyAttendance));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/attendance/student/:id/stats - Get student attendance statistics
router.get('/student/:id/stats', auth, async (req, res) => {
  try {
    const hasAccess = await canUserAccessStudent(req.user, req.params.id);
    if (!hasAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const { startDate, endDate } = req.query;

    let dateFilter = {};
    if (startDate || endDate) {
      if (startDate) dateFilter.gte = normalizeDateOnly(startDate);
      if (endDate) dateFilter.lte = normalizeDateOnly(endDate);
    }

    const baseWhere = {
      studentId: req.params.id,
      ...(Object.keys(dateFilter).length ? { date: dateFilter } : {}),
    };

    const [total, present, absent, late, halfDay, student] = await Promise.all([
      prisma.attendance.count({ where: baseWhere }),
      prisma.attendance.count({ where: { ...baseWhere, status: 'present' } }),
      prisma.attendance.count({ where: { ...baseWhere, status: 'absent' } }),
      prisma.attendance.count({ where: { ...baseWhere, status: 'late' } }),
      prisma.attendance.count({ where: { ...baseWhere, status: 'half-day' } }),
      prisma.student.findUnique({
        where: { id: req.params.id },
        include: {
          user: { select: { id: true, name: true } },
          class: { select: { id: true, name: true, section: true } },
        },
      }),
    ]);

    res.json({
      student: student ? toLegacyStudent(student) : null,
      stats: {
        total,
        present,
        absent,
        late,
        halfDay,
        percentage: total > 0 ? Math.round((present / total) * 100) : 0,
      },
      period: { startDate, endDate },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/attendance/report/daily - Daily attendance report
router.get('/report/daily', auth, async (req, res) => {
  try {
    const { date, classId } = req.query;
    const dt = normalizeDateOnly(date);

    let query = { date: dt };
    if (!['superadmin', 'accounts', 'hr', 'teacher'].includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (classId && !allowedClassIds.includes(String(classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      query.classId = classId ? classId : { in: allowedClassIds };
    } else if (classId) {
      query.classId = classId;
    }

    const records = await prisma.attendance.findMany({
      where: query,
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        class: { select: { id: true, name: true, section: true } },
        teacher: { select: { id: true, name: true } },
      },
    });

    const byClass = {};
    records.forEach(record => {
      const className = record.class?.name || 'Unknown';
      if (!byClass[className]) {
        byClass[className] = {
          class: record.class ? toLegacyClass(record.class) : record.classId,
          records: [],
          present: 0,
          absent: 0,
          late: 0,
        };
      }
      byClass[className].records.push(toLegacyAttendance(record));
      if (record.status === 'present') byClass[className].present += 1;
      else if (record.status === 'absent') byClass[className].absent += 1;
      else if (record.status === 'late') byClass[className].late += 1;
    });

    res.json({
      date: dt,
      byClass: Object.values(byClass),
      summary: {
        total: records.length,
        present: records.filter(r => r.status === 'present').length,
        absent: records.filter(r => r.status === 'absent').length,
        late: records.filter(r => r.status === 'late').length,
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/attendance/report/monthly - Monthly attendance report
router.get('/report/monthly', auth, async (req, res) => {
  try {
    const { month, year, classId } = req.query;

    const currentMonth = month || (new Date().getMonth() + 1);
    const currentYear = year || new Date().getFullYear();

    const startDate = new Date(currentYear, currentMonth - 1, 1);
    const endDate = new Date(currentYear, currentMonth, 0);

    let query = {
      date: {
        gte: normalizeDateOnly(startDate),
        lte: normalizeDateOnly(endDate),
      },
    };

    if (!['superadmin', 'accounts', 'hr', 'teacher'].includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    let studentQuery = {};
    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (classId && !allowedClassIds.includes(String(classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      const scopedClassFilter = classId ? [String(classId)] : allowedClassIds;
      query.classId = { in: scopedClassFilter };
      studentQuery.classId = { in: scopedClassFilter };
    } else if (classId) {
      query.classId = classId;
      studentQuery.classId = classId;
    }

    const students = await prisma.student.findMany({
      where: studentQuery,
      include: {
        user: { select: { id: true, name: true } },
        class: { select: { id: true, name: true, section: true } },
      },
    });

    const baseFilters = query;
    const targetStudentIds = students.map(student => student.id);

    if (!targetStudentIds.length) {
      return res.json({ month: currentMonth, year: currentYear, report: [] });
    }

    const attendanceStats = await prisma.attendance.groupBy({
      by: ['studentId'],
      where: {
        ...baseFilters,
        studentId: { in: targetStudentIds },
      },
      _count: { _all: true },
    });

    const statsMap = new Map();
    attendanceStats.forEach(stat => statsMap.set(stat.studentId, stat._count._all));

    const presentCounts = await prisma.attendance.groupBy({
      by: ['studentId'],
      where: { ...baseFilters, studentId: { in: targetStudentIds }, status: 'present' },
      _count: { _all: true },
    });

    const presentMap = new Map();
    presentCounts.forEach(stat => presentMap.set(stat.studentId, stat._count._all));

    const report = students.map(student => {
      const total = statsMap.get(student.id) || 0;
      const present = presentMap.get(student.id) || 0;
      return {
        student: {
          _id: student.id,
          name: student.name,
          admissionNo: student.admissionNo,
          class: student.class ? toLegacyClass(student.class) : student.classId,
        },
        attendance: {
          total,
          present,
          absent: total - present,
          percentage: total > 0 ? Math.round((present / total) * 100) : 0,
        },
      };
    });

    res.json({
      month: currentMonth,
      year: currentYear,
      report,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/attendance/defaulters - Get students with low attendance
router.get('/defaulters', auth, async (req, res) => {
  try {
    const { threshold = 75, classId } = req.query;
    const minPercentage = parseInt(threshold);

    if (!['superadmin', 'accounts', 'hr', 'teacher'].includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    let studentQuery = {};
    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (classId && !allowedClassIds.includes(String(classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      studentQuery.classId = classId ? classId : { in: allowedClassIds };
    } else if (classId) {
      studentQuery.classId = classId;
    }

    const students = await prisma.student.findMany({
      where: studentQuery,
      include: {
        user: { select: { id: true, name: true, phone: true } },
        class: { select: { id: true, name: true, section: true } },
        parentUser: { select: { id: true, name: true, phone: true } },
      },
    });

    const attendanceStats = await prisma.attendance.groupBy({
      by: ['studentId'],
      where: { studentId: { in: students.map(s => s.id) } },
      _count: { _all: true },
    });

    const presentCounts = await prisma.attendance.groupBy({
      by: ['studentId'],
      where: { studentId: { in: students.map(s => s.id) }, status: 'present' },
      _count: { _all: true },
    });

    const statsMap = new Map();
    attendanceStats.forEach(stat => statsMap.set(stat.studentId, stat._count._all));
    const presentMap = new Map();
    presentCounts.forEach(stat => presentMap.set(stat.studentId, stat._count._all));

    const defaulters = [];
    for (const student of students) {
      const total = statsMap.get(student.id) || 0;
      const present = presentMap.get(student.id) || 0;
      const percentage = total > 0 ? Math.round((present / total) * 100) : 100;

      if (percentage < minPercentage) {
        defaulters.push({
          student: {
            _id: student.id,
            name: student.name,
            admissionNo: student.admissionNo,
            class: student.class ? toLegacyClass(student.class) : student.classId,
            parentPhone: student.parentPhone,
            parent: student.parentUser ? toLegacyUser(student.parentUser) : student.parentUserId,
          },
          attendance: {
            total,
            present,
            percentage,
          },
        });
      }
    }

    res.json({
      threshold: minPercentage,
      count: defaulters.length,
      defaulters,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

module.exports = router;
