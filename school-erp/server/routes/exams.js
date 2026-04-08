const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const jspdfModule = require('jspdf');
const jsPDF = jspdfModule.jsPDF || jspdfModule;
const { getTeacherClassIds, canUserAccessStudent, getStudentRecordsForUser } = require('../utils/accessScope');
const { withLegacyId, toLegacyClass, toLegacyStudent } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyExam(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    classId: record.class ? toLegacyClass(record.class) : record.classId,
  });
}

function toLegacyExamResult(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    examId: record.exam ? toLegacyExam(record.exam) : record.examId,
  });
}

function calculateGrade(marks, total) {
  if (!total || total === 0) return 'N/A';
  const percentage = (marks / total) * 100;
  if (percentage >= 90) return 'A+';
  if (percentage >= 80) return 'A';
  if (percentage >= 70) return 'B+';
  if (percentage >= 60) return 'B';
  if (percentage >= 50) return 'C';
  if (percentage >= 40) return 'D';
  return 'F';
}

function buildExamListResponse(data, page, limit, total) {
  return {
    data,
    exams: data,
    pagination: getPaginationData(page, limit, total),
    meta: { query: { page, limit, sort: 'date' } },
  };
}

function buildExamResultListResponse(data, page, limit, total, extraQuery = {}) {
  return {
    data,
    results: data,
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

// POST /api/exams/schedule - Create exam schedule
router.post('/schedule', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const {
      name,
      examType,
      classId,
      subject,
      date,
      time,
      startTime,
      endTime,
      roomNumber,
      instructions,
      totalMarks,
      passingMarks,
    } = req.body;

    const resolvedTime = time || startTime;
    if (!classId || !subject || !date || !resolvedTime) {
      return res.status(400).json({ msg: 'classId, subject, date, and time are required' });
    }

    const existingClass = await prisma.class.findUnique({
      where: { id: classId },
      select: { id: true },
    });
    if (!existingClass) {
      return res.status(400).json({ msg: 'Invalid classId' });
    }

    const exam = await prisma.exam.create({
      data: {
        name: name || `${subject} Exam`,
        examType: examType || 'General',
        classId,
        subject,
        date: new Date(date),
        time: resolvedTime,
        startTime: startTime || resolvedTime,
        endTime: endTime || '',
        roomNumber: roomNumber || '',
        instructions: instructions || '',
        totalMarks: Number(totalMarks || 100),
        passingMarks: Number(passingMarks || 35),
      },
      include: {
        class: { select: { id: true, name: true, section: true } },
      },
    });

    res.status(201).json({ msg: 'Exam scheduled successfully', exam: toLegacyExam(exam) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.post('/', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const {
      name,
      examType,
      classId,
      subject,
      date,
      time,
      startTime,
      endTime,
      roomNumber,
      instructions,
      totalMarks,
      passingMarks,
    } = req.body;

    const resolvedTime = time || startTime;
    if (!classId || !subject || !date || !resolvedTime) {
      return res.status(400).json({ msg: 'classId, subject, date, and time are required' });
    }

    const existingClass = await prisma.class.findUnique({
      where: { id: classId },
      select: { id: true },
    });
    if (!existingClass) {
      return res.status(400).json({ msg: 'Invalid classId' });
    }

    const exam = await prisma.exam.create({
      data: {
        name: name || `${subject} Exam`,
        examType: examType || 'General',
        classId,
        subject,
        date: new Date(date),
        time: resolvedTime,
        startTime: startTime || resolvedTime,
        endTime: endTime || '',
        roomNumber: roomNumber || '',
        instructions: instructions || '',
        totalMarks: Number(totalMarks || 100),
        passingMarks: Number(passingMarks || 35),
      },
      include: {
        class: { select: { id: true, name: true, section: true } },
      },
    });

    res.status(201).json({ msg: 'Exam scheduled successfully', exam: toLegacyExam(exam) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/exams/schedule - Get all exam schedules
router.get('/schedule', auth, async (req, res) => {
  try {
    const { classId, examType, startDate, endDate } = req.query;

    const where = {};
    if (classId) where.classId = classId;
    if (examType) where.examType = examType;
    if (startDate || endDate) {
      where.date = {};
      if (startDate) where.date.gte = new Date(startDate);
      if (endDate) where.date.lte = new Date(endDate);
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      where.classId = where.classId
        ? { in: allowedClassIds.filter(id => id === String(where.classId)) }
        : { in: allowedClassIds };
    }

    if (['student', 'parent'].includes(req.user.role)) {
      const studentRecords = await getStudentRecordsForUser(req.user);
      const classIds = [
        ...new Set(
          studentRecords
            .map(item => String(item.classId?._id || item.classId?.id || item.classId))
            .filter(Boolean)
        )
      ];
      if (!classIds.length) {
        return res.json([]);
      }
      where.classId = { in: classIds };
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, exams] = await Promise.all([
      prisma.exam.count({ where }),
      prisma.exam.findMany({
        where,
        include: {
          class: { select: { id: true, name: true, section: true } },
        },
        orderBy: { date: 'asc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildExamListResponse(exams.map(toLegacyExam), page, limit, total));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/', auth, async (req, res) => {
  try {
    const { classId, examType, startDate, endDate } = req.query;

    const where = {};
    if (classId) where.classId = classId;
    if (examType) where.examType = examType;
    if (startDate || endDate) {
      where.date = {};
      if (startDate) where.date.gte = new Date(startDate);
      if (endDate) where.date.lte = new Date(endDate);
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      where.classId = where.classId
        ? { in: allowedClassIds.filter(id => id === String(where.classId)) }
        : { in: allowedClassIds };
    }

    if (['student', 'parent'].includes(req.user.role)) {
      const studentRecords = await getStudentRecordsForUser(req.user);
      const classIds = [
        ...new Set(
          studentRecords
            .map(item => String(item.classId?._id || item.classId?.id || item.classId))
            .filter(Boolean)
        )
      ];
      if (!classIds.length) {
        return res.json(buildExamListResponse([], 1, 20, 0));
      }
      where.classId = { in: classIds };
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, exams] = await Promise.all([
      prisma.exam.count({ where }),
      prisma.exam.findMany({
        where,
        include: {
          class: { select: { id: true, name: true, section: true } },
        },
        orderBy: { date: 'asc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildExamListResponse(exams.map(toLegacyExam), page, limit, total));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/exams/schedule/:id - Get single exam schedule
router.get('/schedule/:id', auth, async (req, res) => {
  try {
    const exam = await prisma.exam.findUnique({
      where: { id: req.params.id },
      include: {
        class: { select: { id: true, name: true, section: true } },
      },
    });

    if (!exam) {
      return res.status(404).json({ msg: 'Exam not found' });
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (!allowedClassIds.includes(String(exam.classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    if (['student', 'parent'].includes(req.user.role)) {
      const studentRecords = await getStudentRecordsForUser(req.user);
      const classIds = studentRecords
        .map(item => String(item.classId?._id || item.classId?.id || item.classId))
        .filter(Boolean);
      if (!classIds.includes(String(exam.classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    res.json(toLegacyExam(exam));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// PUT /api/exams/schedule/:id - Update exam schedule
router.put('/schedule/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const updateData = { ...req.body };
    if (updateData.time && !updateData.startTime) {
      updateData.startTime = updateData.time;
    }
    if (!updateData.name && updateData.subject) {
      updateData.name = `${updateData.subject} Exam`;
    }

    const exam = await prisma.exam.update({
      where: { id: req.params.id },
      data: updateData,
      include: {
        class: { select: { id: true, name: true, section: true } },
      },
    });

    res.json({ msg: 'Exam schedule updated', exam: toLegacyExam(exam) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// DELETE /api/exams/schedule/:id - Delete exam schedule
router.delete('/schedule/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    await prisma.examResult.deleteMany({ where: { examId: req.params.id } });
    await prisma.exam.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Exam schedule and associated results deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// POST /api/exams/results/bulk - Bulk save exam results
router.post('/results/bulk', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const { results, examId } = req.body;
    if (!results || !Array.isArray(results)) {
      return res.status(400).json({ msg: 'results array is required' });
    }

    const targetExamId = examId || results[0]?.examId;
    if (req.user.role === 'teacher' && targetExamId) {
      const exam = await prisma.exam.findUnique({
        where: { id: targetExamId },
        select: { classId: true },
      });
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (!exam || !allowedClassIds.includes(String(exam.classId))) {
        return res.status(403).json({ msg: 'You can only enter results for your classes.' });
      }
    }

    await prisma.$transaction(
      results.map(r => prisma.examResult.upsert({
        where: {
          examId_studentId: {
            examId: examId || r.examId,
            studentId: r.studentId,
          },
        },
        update: {
          marksObtained: r.marksObtained,
          totalMarks: r.totalMarks || 100,
          grade: r.grade || calculateGrade(r.marksObtained, r.totalMarks || 100),
          remarks: r.remarks || '',
        },
        create: {
          examId: examId || r.examId,
          studentId: r.studentId,
          marksObtained: r.marksObtained,
          totalMarks: r.totalMarks || 100,
          grade: r.grade || calculateGrade(r.marksObtained, r.totalMarks || 100),
          remarks: r.remarks || '',
        },
      }))
    );

    res.json({ msg: 'Results saved successfully', count: results.length });
  } catch (err) {
    console.error('Bulk results error:', err);
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// POST /api/exams/results - Save single result
router.post('/results', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const { examId, studentId, marksObtained, totalMarks, grade, remarks } = req.body;

    if (!examId || !studentId || marksObtained === undefined) {
      return res.status(400).json({ msg: 'examId, studentId, and marksObtained are required' });
    }

    const [exam, student] = await Promise.all([
      prisma.exam.findUnique({ where: { id: examId }, select: { id: true, classId: true } }),
      prisma.student.findUnique({ where: { id: studentId }, select: { id: true, classId: true } }),
    ]);

    if (!exam || !student) {
      return res.status(400).json({ msg: 'Invalid examId or studentId' });
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (!allowedClassIds.includes(String(exam.classId)) || !allowedClassIds.includes(String(student.classId))) {
        return res.status(403).json({ msg: 'You can only enter results for your assigned classes.' });
      }
    }

    const result = await prisma.examResult.upsert({
      where: {
        examId_studentId: { examId, studentId },
      },
      update: {
        marksObtained,
        totalMarks: totalMarks || 100,
        grade: grade || calculateGrade(marksObtained, totalMarks || 100),
        remarks: remarks || '',
      },
      create: {
        examId,
        studentId,
        marksObtained,
        totalMarks: totalMarks || 100,
        grade: grade || calculateGrade(marksObtained, totalMarks || 100),
        remarks: remarks || '',
      },
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        exam: { select: { id: true, name: true, examType: true, subject: true } },
      },
    });

    res.status(201).json({ msg: 'Result saved', result: toLegacyExamResult(result) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/exams/results/exam/:examId - Get all results for an exam
router.get('/results/exam/:examId', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const exam = await prisma.exam.findUnique({
      where: { id: req.params.examId },
      select: { classId: true },
    });
    if (!exam) {
      return res.status(404).json({ msg: 'Exam not found' });
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (!allowedClassIds.includes(String(exam.classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { examId: req.params.examId };

    const [total, results] = await Promise.all([
      prisma.examResult.count({ where }),
      prisma.examResult.findMany({
        where,
        include: {
          student: {
            include: {
              class: { select: { id: true, name: true, section: true } },
            },
          },
        },
        orderBy: { marksObtained: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildExamResultListResponse(results.map(toLegacyExamResult), page, limit, total, { sort: '-marksObtained' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/results', auth, async (req, res) => {
  try {
    const { examId, studentId } = req.query;
    const where = {};

    if (examId) where.examId = examId;
    if (studentId) where.studentId = studentId;

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      const students = await prisma.student.findMany({
        where: { classId: { in: allowedClassIds } },
        select: { id: true },
      });
      const allowedStudentIds = students.map((student) => student.id);
      where.studentId = studentId
        ? (allowedStudentIds.includes(String(studentId)) ? studentId : '__forbidden__')
        : { in: allowedStudentIds };
    } else if (['student', 'parent'].includes(req.user.role)) {
      const studentRecords = await getStudentRecordsForUser(req.user);
      const allowedStudentIds = studentRecords.map((student) => String(student._id || student.id)).filter(Boolean);
      where.studentId = studentId
        ? (allowedStudentIds.includes(String(studentId)) ? studentId : '__forbidden__')
        : { in: allowedStudentIds };
    }

    if (where.studentId === '__forbidden__') {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, results] = await Promise.all([
      prisma.examResult.count({ where }),
      prisma.examResult.findMany({
        where,
        include: {
          student: {
            include: {
              class: { select: { id: true, name: true, section: true } },
            },
          },
          exam: {
            include: {
              class: { select: { id: true, name: true, section: true } },
            },
          },
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildExamResultListResponse(results.map(toLegacyExamResult), page, limit, total, { sort: '-createdAt' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/exams/results/student/:studentId - Get student's all results
router.get('/results/student/:studentId', auth, async (req, res) => {
  try {
    const hasAccess = await canUserAccessStudent(req.user, req.params.studentId);
    if (!hasAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const { examType } = req.query;

    let examIds = null;
    if (examType) {
      const exams = await prisma.exam.findMany({
        where: { examType },
        select: { id: true },
      });
      examIds = exams.map(e => e.id);
    }

    const maxLimit = Math.min(200, Math.max(1, parseInt(req.query.limit) || 200));
    const results = await prisma.examResult.findMany({
      where: {
        studentId: req.params.studentId,
        ...(examIds ? { examId: { in: examIds } } : {}),
      },
      include: {
        exam: {
          include: { class: { select: { id: true, name: true, section: true } } },
        },
      },
      orderBy: { exam: { date: 'desc' } },
      take: maxLimit,
    });

    const totalExams = results.length;
    const passed = results.filter(r => r.marksObtained >= (r.totalMarks * 0.4)).length;
    const failed = totalExams - passed;
    const averagePercentage = results.length > 0
      ? Math.round((results.reduce((sum, r) => sum + (r.marksObtained / r.totalMarks) * 100, 0) / results.length))
      : 0;

    res.json({
      studentId: req.params.studentId,
      results: results.map(toLegacyExamResult),
      summary: {
        totalExams,
        passed,
        failed,
        averagePercentage,
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// PUT /api/exams/results/:id - Update result
router.put('/results/:id', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const { marksObtained, totalMarks, grade, remarks } = req.body;

    const result = await prisma.examResult.update({
      where: { id: req.params.id },
      data: { marksObtained, totalMarks, grade, remarks },
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        exam: { select: { id: true, name: true, examType: true } },
      },
    });

    res.json({ msg: 'Result updated', result: toLegacyExamResult(result) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// DELETE /api/exams/results/:id - Delete result
router.delete('/results/:id', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    await prisma.examResult.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Result deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/exams/report-card/:studentId - Generate report card PDF
router.get('/report-card/:studentId', auth, async (req, res) => {
  try {
    const hasAccess = await canUserAccessStudent(req.user, req.params.studentId);
    if (!hasAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const { examType } = req.query;

    const student = await prisma.student.findUnique({
      where: { id: req.params.studentId },
      include: {
        user: { select: { id: true, name: true, email: true, phone: true } },
        class: { select: { id: true, name: true, section: true } },
        parentUser: { select: { id: true, name: true, phone: true } },
      },
    });

    if (!student) {
      return res.status(404).json({ msg: 'Student not found' });
    }

    const examWhere = { classId: student.classId };
    if (examType) examWhere.examType = examType;

    const exams = await prisma.exam.findMany({
      where: examWhere,
      orderBy: { date: 'asc' },
    });
    const examIds = exams.map(e => e.id);

    const results = await prisma.examResult.findMany({
      where: {
        studentId: student.id,
        examId: { in: examIds },
      },
      include: { exam: true },
    });

    const doc = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    let y = 18;

    doc.setFontSize(18);
    doc.text(process.env.SCHOOL_NAME || 'School', pageWidth / 2, y, { align: 'center' });
    y += 8;
    doc.setFontSize(14);
    doc.text('PROGRESS REPORT', pageWidth / 2, y, { align: 'center' });
    y += 6;
    doc.setFontSize(10);
    doc.text(`Academic Year: ${student.academicYear || `${new Date().getFullYear()}-${new Date().getFullYear() + 1}`}`, pageWidth / 2, y, { align: 'center' });
    y += 10;

    doc.setFontSize(11);
    doc.text(`Student Name: ${student.name}`, 14, y);
    doc.text(`Admission No: ${student.admissionNo}`, 110, y);
    y += 8;
    doc.text(`Class: ${student.class?.name || 'N/A'} - ${student.section || student.class?.section || 'N/A'}`, 14, y);
    doc.text(`Roll No: ${student.rollNumber || 'N/A'}`, 110, y);
    y += 8;
    doc.text(`Date of Birth: ${student.dob ? new Date(student.dob).toLocaleDateString() : 'N/A'}`, 14, y);
    doc.text(`Parent: ${student.parentUser?.name || student.parentPhone || 'N/A'}`, 110, y);
    y += 12;

    doc.setFontSize(12);
    doc.text('Examination Results', 14, y);
    y += 6;
    doc.setFontSize(10);
    doc.text('Exam', 14, y);
    doc.text('Subject', 58, y);
    doc.text('Date', 100, y);
    doc.text('Marks', 132, y);
    doc.text('Grade', 164, y);
    doc.text('Status', 186, y);
    y += 4;
    doc.line(14, y, 196, y);
    y += 6;

    results.forEach((result) => {
      if (y > 270) {
        doc.addPage();
        y = 20;
      }

      const exam = result.exam;
      const status = result.marksObtained >= (result.totalMarks * 0.4) ? 'PASS' : 'FAIL';

      doc.setFontSize(9);
      doc.text(exam?.name || 'N/A', 14, y, { maxWidth: 40 });
      doc.text(exam?.subject || 'N/A', 58, y, { maxWidth: 36 });
      doc.text(exam?.date ? new Date(exam.date).toLocaleDateString() : 'N/A', 100, y);
      doc.text(`${result.marksObtained}/${result.totalMarks}`, 132, y);
      doc.text(result.grade || 'N/A', 164, y);
      doc.text(status, 186, y);
      y += 8;
    });

    const totalExams = results.length;
    const passed = results.filter(r => r.marksObtained >= (r.totalMarks * 0.4)).length;
    const percentage = results.length > 0
      ? Math.round((results.reduce((sum, r) => sum + (r.marksObtained / r.totalMarks) * 100, 0) / results.length))
      : 0;

    y += 4;
    doc.setFontSize(11);
    doc.text('Summary', 14, y);
    y += 8;
    doc.setFontSize(10);
    doc.text(`Total Examinations: ${totalExams}`, 14, y);
    y += 6;
    doc.text(`Passed: ${passed}`, 14, y);
    y += 6;
    doc.text(`Overall Percentage: ${percentage}%`, 14, y);

    doc.setFontSize(8);
    doc.text('This is a computer-generated report card.', pageWidth / 2, 285, { align: 'center' });
    doc.text('For any queries, please contact the school office.', pageWidth / 2, 290, { align: 'center' });

    const buffer = Buffer.from(doc.output('arraybuffer'));
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `inline; filename=ReportCard_${student.admissionNo}.pdf`);
    res.send(buffer);
  } catch (err) {
    console.error('Report card error:', err);
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/exams/analytics - Exam analytics
router.get('/analytics', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const { classId, examId } = req.query;

    const examWhere = {};
    if (classId) examWhere.classId = classId;
    if (examId) examWhere.id = examId;

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (classId && !allowedClassIds.includes(String(classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      examWhere.classId = classId ? classId : { in: allowedClassIds };
    }

    const exams = await prisma.exam.findMany({ where: examWhere });

    const analytics = await Promise.all(
      exams.map(async (exam) => {
        const results = await prisma.examResult.findMany({
          where: { examId: exam.id },
          select: { marksObtained: true, totalMarks: true, grade: true },
        });

        const totalStudents = results.length;
        const passThreshold = exam.passingMarks || exam.totalMarks * 0.4;
        const passed = results.filter(r => r.marksObtained >= passThreshold).length;
        const failed = totalStudents - passed;

        const avgMarks = totalStudents > 0
          ? Math.round(results.reduce((sum, r) => sum + r.marksObtained, 0) / totalStudents)
          : 0;

        const gradeDistribution = {};
        results.forEach(r => {
          const grade = r.grade || 'N/A';
          gradeDistribution[grade] = (gradeDistribution[grade] || 0) + 1;
        });

        return {
          exam: {
            _id: exam.id,
            name: exam.name,
            examType: exam.examType,
            subject: exam.subject,
            date: exam.date,
            totalMarks: exam.totalMarks,
          },
          stats: {
            totalStudents,
            passed,
            failed,
            passPercentage: totalStudents > 0 ? Math.round((passed / totalStudents) * 100) : 0,
            averageMarks: avgMarks,
            gradeDistribution,
          },
        };
      })
    );

    res.json(analytics);
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

module.exports = router;
