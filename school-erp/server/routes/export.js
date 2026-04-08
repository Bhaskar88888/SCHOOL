const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { exportToPDF, exportToExcel, exportReportCard } = require('../utils/export');
const { getTeacherClassIds, canUserAccessStudent } = require('../utils/accessScope');

function normalizeExportFormat(format, fallback = 'csv') {
  const normalized = String(format || fallback).toLowerCase();
  if (['pdf'].includes(normalized)) return 'pdf';
  if (['csv', 'excel', 'xlsx'].includes(normalized)) return 'csv';
  return fallback;
}

function buildQueryString(query) {
  const params = new URLSearchParams();
  Object.entries(query || {}).forEach(([key, value]) => {
    if (key === 'format' || value === undefined || value === null || value === '') {
      return;
    }
    if (Array.isArray(value)) {
      value.forEach((item) => params.append(key, String(item)));
      return;
    }
    params.append(key, String(value));
  });
  const output = params.toString();
  return output ? `?${output}` : '';
}

function redirectExport(req, res, resource, defaultFormat = 'csv') {
  const format = normalizeExportFormat(req.query.format, defaultFormat);
  const suffix = format === 'pdf' ? '/pdf' : '/excel';
  return res.redirect(307, `${req.baseUrl}${resource}${suffix}${buildQueryString(req.query)}`);
}

async function getStudentExportData(user, query) {
  const { classId, search } = query;
  const where = {};
  if (classId) where.classId = classId;
  if (search) {
    where.OR = [
      { name: { contains: search, mode: 'insensitive' } },
      { admissionNo: { contains: search, mode: 'insensitive' } },
    ];
  }

  if (user.role === 'teacher') {
    const allowedClasses = await getTeacherClassIds(user.id);
    if (where.classId && !allowedClasses.includes(where.classId)) {
      const err = new Error('FORBIDDEN_CLASS');
      err.code = 'FORBIDDEN_CLASS';
      throw err;
    }
    if (!where.classId) {
      where.classId = { in: allowedClasses };
    }
  }

  return prisma.student.findMany({
    where,
    include: {
      class: { select: { id: true, name: true, section: true } },
      user: { select: { email: true, phone: true } },
    },
    orderBy: { name: 'asc' },
  });
}

async function getAttendanceExportData(user, query) {
  const { classId, startDate, endDate } = query;
  const where = {};
  if (classId) where.classId = classId;
  if (startDate || endDate) {
    where.date = {};
    if (startDate) where.date.gte = new Date(startDate);
    if (endDate) where.date.lte = new Date(endDate);
  }

  if (user.role === 'teacher') {
    const allowedClasses = await getTeacherClassIds(user.id);
    if (where.classId && !allowedClasses.includes(where.classId)) {
      const err = new Error('FORBIDDEN_CLASS');
      err.code = 'FORBIDDEN_CLASS';
      throw err;
    }
    if (!where.classId) {
      where.classId = { in: allowedClasses };
    }
  }

  return prisma.attendance.findMany({
    where,
    include: {
      student: { select: { id: true, name: true, admissionNo: true } },
      class: { select: { id: true, name: true, section: true } },
    },
    orderBy: { date: 'desc' },
  });
}

async function getFeeExportData(query) {
  const { startDate, endDate, studentId } = query;
  const where = {};
  if (startDate || endDate) {
    where.paymentDate = {};
    if (startDate) where.paymentDate.gte = new Date(startDate);
    if (endDate) where.paymentDate.lte = new Date(endDate);
  }
  if (studentId) where.studentId = studentId;

  return prisma.feePayment.findMany({
    where,
    include: {
      student: { select: { id: true, name: true, admissionNo: true } },
      collectedBy: { select: { id: true, name: true } },
    },
    orderBy: { paymentDate: 'desc' },
  });
}

// ==================== STUDENT EXPORTS ====================

router.get('/students/pdf', auth, roleCheck('superadmin', 'accounts', 'teacher'), async (req, res) => {
  try {
    const { classId, search } = req.query;
    const where = {};
    if (classId) where.classId = classId;
    if (search) {
      where.OR = [
        { name: { contains: search, mode: 'insensitive' } },
        { admissionNo: { contains: search, mode: 'insensitive' } },
      ];
    }

    if (req.user.role === 'teacher') {
      const allowedClasses = await getTeacherClassIds(req.user.id);
      if (where.classId && !allowedClasses.includes(where.classId)) {
        return res.status(403).json({ msg: 'Access Denied: You cannot export data for this class' });
      }
      if (!where.classId) {
        where.classId = { in: allowedClasses };
      }
    }

    const students = await prisma.student.findMany({
      where,
      include: {
        class: { select: { id: true, name: true, section: true } },
        user: { select: { email: true, phone: true } },
      },
      orderBy: { name: 'asc' },
    });

    exportToPDF(res, 'students', students, {
      title: 'STUDENT LIST',
      subtitle: `Total Students: ${students.length}`,
      schoolName: process.env.SCHOOL_NAME,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/students/excel', auth, roleCheck('superadmin', 'accounts', 'teacher'), async (req, res) => {
  try {
    const { classId, search } = req.query;
    const where = {};
    if (classId) where.classId = classId;
    if (search) {
      where.OR = [
        { name: { contains: search, mode: 'insensitive' } },
        { admissionNo: { contains: search, mode: 'insensitive' } },
      ];
    }

    if (req.user.role === 'teacher') {
      const allowedClasses = await getTeacherClassIds(req.user.id);
      if (where.classId && !allowedClasses.includes(where.classId)) {
        return res.status(403).json({ msg: 'Access Denied: You cannot export data for this class' });
      }
      if (!where.classId) {
        where.classId = { in: allowedClasses };
      }
    }

    const students = await prisma.student.findMany({
      where,
      include: {
        class: { select: { id: true, name: true, section: true } },
        user: { select: { email: true, phone: true } },
      },
      orderBy: { name: 'asc' },
    });

    const excelData = students.map(s => ({
      AdmissionNo: s.admissionNo,
      Name: s.name,
      Class: s.class?.name || 'N/A',
      Section: s.section || s.class?.section || 'N/A',
      Gender: s.gender,
      DOB: new Date(s.dob).toLocaleDateString(),
      ParentPhone: s.parentPhone,
      ParentEmail: s.parentEmail || 'N/A',
      Email: s.user?.email || 'N/A',
      Phone: s.user?.phone || 'N/A',
      Aadhaar: s.aadhaar || 'N/A',
      BloodGroup: s.bloodGroup || 'N/A',
      Address: s.structuredAddress
        ? `${s.structuredAddress.line1}, ${s.structuredAddress.city}, ${s.structuredAddress.state} - ${s.structuredAddress.pincode}`
        : 'N/A',
    }));

    exportToExcel(res, 'students', excelData);
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/students/:id/report-card', auth, async (req, res) => {
  try {
    if (!(await canUserAccessStudent(req.user, req.params.id))) {
      return res.status(403).json({ msg: 'Access Denied' });
    }

    const student = await prisma.student.findUnique({
      where: { id: req.params.id },
      include: {
        class: { select: { id: true, name: true, section: true } },
        user: { select: { email: true, phone: true } },
      },
    });

    if (!student) {
      return res.status(404).json({ msg: 'Student not found' });
    }

    const results = await prisma.examResult.findMany({
      where: { studentId: student.id },
      include: { exam: { select: { id: true, name: true, subject: true, examType: true, date: true, totalMarks: true } } },
    });

    const totalAttendance = await prisma.attendance.count({ where: { studentId: student.id } });
    const presentCount = await prisma.attendance.count({ where: { studentId: student.id, status: 'present' } });
    const attendanceStats = {
      total: totalAttendance,
      present: presentCount,
      absent: totalAttendance - presentCount,
      percentage: totalAttendance > 0 ? Math.round((presentCount / totalAttendance) * 100) : 0,
    };

    const feePayments = await prisma.feePayment.findMany({
      where: { studentId: student.id },
      orderBy: { paymentDate: 'desc' },
      take: 10,
    });

    exportReportCard(res, student, results, attendanceStats, feePayments);
  } catch (err) {
    res.status(500).json({ msg: 'Report card generation failed', error: err.message });
  }
});

// ==================== ATTENDANCE EXPORTS ====================

router.get('/attendance/pdf', auth, roleCheck('superadmin', 'teacher', 'accounts', 'hr'), async (req, res) => {
  try {
    const { classId, startDate, endDate } = req.query;
    const where = {};
    if (classId) where.classId = classId;
    if (startDate || endDate) {
      where.date = {};
      if (startDate) where.date.gte = new Date(startDate);
      if (endDate) where.date.lte = new Date(endDate);
    }

    if (req.user.role === 'teacher') {
      const allowedClasses = await getTeacherClassIds(req.user.id);
      if (where.classId && !allowedClasses.includes(where.classId)) {
        return res.status(403).json({ msg: 'Access Denied: You cannot export data for this class' });
      }
      if (!where.classId) {
        where.classId = { in: allowedClasses };
      }
    }

    const attendance = await prisma.attendance.findMany({
      where,
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        class: { select: { id: true, name: true, section: true } },
      },
      orderBy: { date: 'desc' },
    });

    exportToPDF(res, 'attendance', attendance, {
      title: 'ATTENDANCE REPORT',
      subtitle: `Records: ${attendance.length}`,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/attendance/excel', auth, roleCheck('superadmin', 'teacher', 'accounts', 'hr'), async (req, res) => {
  try {
    const { classId, startDate, endDate } = req.query;
    const where = {};
    if (classId) where.classId = classId;
    if (startDate || endDate) {
      where.date = {};
      if (startDate) where.date.gte = new Date(startDate);
      if (endDate) where.date.lte = new Date(endDate);
    }

    if (req.user.role === 'teacher') {
      const allowedClasses = await getTeacherClassIds(req.user.id);
      if (where.classId && !allowedClasses.includes(where.classId)) {
        return res.status(403).json({ msg: 'Access Denied: You cannot export data for this class' });
      }
      if (!where.classId) {
        where.classId = { in: allowedClasses };
      }
    }

    const attendance = await prisma.attendance.findMany({
      where,
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        class: { select: { id: true, name: true, section: true } },
      },
      orderBy: { date: 'desc' },
    });

    const excelData = attendance.map(a => ({
      Date: a.date,
      StudentName: a.student?.name || 'N/A',
      AdmissionNo: a.student?.admissionNo || 'N/A',
      Class: a.class?.name || 'N/A',
      Status: a.status,
      Subject: a.subject || 'N/A',
      MarkedBy: a.teacherId || 'N/A',
    }));

    exportToExcel(res, 'attendance', excelData);
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

// ==================== FEE EXPORTS ====================

router.get('/fees/pdf', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { startDate, endDate, studentId } = req.query;
    const where = {};
    if (startDate || endDate) {
      where.paymentDate = {};
      if (startDate) where.paymentDate.gte = new Date(startDate);
      if (endDate) where.paymentDate.lte = new Date(endDate);
    }
    if (studentId) where.studentId = studentId;

    const payments = await prisma.feePayment.findMany({
      where,
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        collectedBy: { select: { id: true, name: true } },
      },
      orderBy: { paymentDate: 'desc' },
    });

    exportToPDF(res, 'fees', payments, {
      title: 'FEE COLLECTION REPORT',
      subtitle: `Total Payments: ${payments.length}`,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/fees/excel', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { startDate, endDate, studentId } = req.query;
    const where = {};
    if (startDate || endDate) {
      where.paymentDate = {};
      if (startDate) where.paymentDate.gte = new Date(startDate);
      if (endDate) where.paymentDate.lte = new Date(endDate);
    }
    if (studentId) where.studentId = studentId;

    const payments = await prisma.feePayment.findMany({
      where,
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        collectedBy: { select: { id: true, name: true } },
      },
      orderBy: { paymentDate: 'desc' },
    });

    const excelData = payments.map(p => ({
      ReceiptNo: p.receiptNo,
      Date: new Date(p.paymentDate).toLocaleDateString(),
      StudentName: p.student?.name || 'N/A',
      AdmissionNo: p.student?.admissionNo || 'N/A',
      FeeType: p.feeType,
      Amount: p.amountPaid,
      PaymentMode: p.paymentMode,
      CollectedBy: p.collectedBy?.name || 'N/A',
    }));

    exportToExcel(res, 'fees', excelData);
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

// ==================== EXAM EXPORTS ====================

router.get('/exams/pdf', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const { classId, examType } = req.query;
    const where = {};
    if (classId) where.classId = classId;
    if (examType) where.examType = examType;

    if (req.user.role === 'teacher') {
      const allowedClasses = await getTeacherClassIds(req.user.id);
      if (where.classId && !allowedClasses.includes(where.classId)) {
        return res.status(403).json({ msg: 'Access Denied: You cannot export data for this class' });
      }
      if (!where.classId) {
        where.classId = { in: allowedClasses };
      }
    }

    const exams = await prisma.exam.findMany({
      where,
      include: { class: { select: { id: true, name: true, section: true } } },
      orderBy: { date: 'asc' },
    });

    exportToPDF(res, 'exams', exams, {
      title: 'EXAMINATION SCHEDULE',
      subtitle: `Total Exams: ${exams.length}`,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/exam-results/pdf', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const { examId, studentId } = req.query;
    const where = {};
    if (examId) where.examId = examId;
    if (studentId) where.studentId = studentId;

    if (req.user.role === 'teacher') {
      const allowedClasses = await getTeacherClassIds(req.user.id);
      if (studentId) {
        if (!(await canUserAccessStudent(req.user, studentId))) {
          return res.status(403).json({ msg: 'Access Denied: Student not in your classes' });
        }
      } else {
        const studentsInClasses = await prisma.student.findMany({
          where: { classId: { in: allowedClasses } },
          select: { id: true },
        });
        where.studentId = { in: studentsInClasses.map(s => s.id) };
      }
    }

    const results = await prisma.examResult.findMany({
      where,
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        exam: { select: { id: true, name: true, subject: true, examType: true, totalMarks: true, date: true } },
      },
      orderBy: { exam: { date: 'desc' } },
    });

    exportToPDF(res, 'exam-results', results, {
      title: 'EXAMINATION RESULTS',
      subtitle: `Total Results: ${results.length}`,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/exam-results/excel', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    const { examId, studentId } = req.query;
    const where = {};
    if (examId) where.examId = examId;
    if (studentId) where.studentId = studentId;

    if (req.user.role === 'teacher') {
      const allowedClasses = await getTeacherClassIds(req.user.id);
      if (studentId) {
        if (!(await canUserAccessStudent(req.user, studentId))) {
          return res.status(403).json({ msg: 'Access Denied: Student not in your classes' });
        }
      } else {
        const studentsInClasses = await prisma.student.findMany({
          where: { classId: { in: allowedClasses } },
          select: { id: true },
        });
        where.studentId = { in: studentsInClasses.map(s => s.id) };
      }
    }

    const results = await prisma.examResult.findMany({
      where,
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        exam: { select: { id: true, name: true, subject: true, examType: true } },
      },
    });

    const excelData = results.map(r => ({
      StudentName: r.student?.name || 'N/A',
      AdmissionNo: r.student?.admissionNo || 'N/A',
      ExamName: r.exam?.name || 'N/A',
      Subject: r.exam?.subject || 'N/A',
      MarksObtained: r.marksObtained,
      TotalMarks: r.totalMarks,
      Grade: r.grade,
      Remarks: r.remarks || 'N/A',
    }));

    exportToExcel(res, 'exam-results', excelData);
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

// ==================== LIBRARY EXPORTS ====================

router.get('/library/pdf', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (_req, res) => {
  try {
    const books = await prisma.libraryBook.findMany({ orderBy: { title: 'asc' } });
    exportToPDF(res, 'library', books, {
      title: 'LIBRARY BOOK CATALOG',
      subtitle: `Total Books: ${books.length}`,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/library/excel', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (_req, res) => {
  try {
    const books = await prisma.libraryBook.findMany({ orderBy: { title: 'asc' } });
    const excelData = books.map(b => ({
      Title: b.title,
      Author: b.author,
      ISBN: b.isbn,
      Category: b.category,
      AvailableCopies: b.availableCopies,
      TotalCopies: b.totalCopies,
      RackNumber: b.rackNumber,
    }));
    exportToExcel(res, 'library', excelData);
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

// ==================== STAFF EXPORTS ====================

router.get('/staff/pdf', auth, roleCheck('superadmin', 'hr', 'accounts'), async (req, res) => {
  try {
    const { role, department } = req.query;
    const where = { role: { in: ['teacher', 'staff', 'hr', 'accounts'] } };
    if (role) where.role = role;
    if (department) where.department = department;

    const staff = await prisma.user.findMany({ where, orderBy: { name: 'asc' } });
    exportToPDF(res, 'staff', staff, {
      title: 'STAFF DIRECTORY',
      subtitle: `Total Staff: ${staff.length}`,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

router.get('/staff/excel', auth, roleCheck('superadmin', 'hr', 'accounts'), async (req, res) => {
  try {
    const { role, department } = req.query;
    const where = { role: { in: ['teacher', 'staff', 'hr', 'accounts'] } };
    if (role) where.role = role;
    if (department) where.department = department;

    const staff = await prisma.user.findMany({ where, orderBy: { name: 'asc' } });
    const excelData = staff.map(s => ({
      EmployeeID: s.employeeId,
      Name: s.name,
      Email: s.email,
      Phone: s.phone,
      Role: s.role,
      Department: s.department || 'N/A',
      Designation: s.designation || 'N/A',
      JoiningDate: s.joiningDate ? new Date(s.joiningDate).toLocaleDateString() : 'N/A',
    }));
    exportToExcel(res, 'staff', excelData);
  } catch (err) {
    res.status(500).json({ msg: 'Export failed', error: err.message });
  }
});

// ==================== COMPATIBILITY EXPORT ROUTES ====================

router.get('/students', auth, roleCheck('superadmin', 'accounts', 'teacher'), (req, res) => {
  const format = normalizeExportFormat(req.query.format, 'csv');
  getStudentExportData(req.user, req.query)
    .then((students) => {
      if (format === 'pdf') {
        return exportToPDF(res, 'students', students, {
          title: 'STUDENT LIST',
          subtitle: `Total Students: ${students.length}`,
          schoolName: process.env.SCHOOL_NAME,
        });
      }

      const excelData = students.map(s => ({
        AdmissionNo: s.admissionNo,
        Name: s.name,
        Class: s.class?.name || 'N/A',
        Section: s.section || s.class?.section || 'N/A',
        Gender: s.gender,
        DOB: new Date(s.dob).toLocaleDateString(),
        ParentPhone: s.parentPhone,
        ParentEmail: s.parentEmail || 'N/A',
        Email: s.user?.email || 'N/A',
        Phone: s.user?.phone || 'N/A',
        Aadhaar: s.aadhaar || 'N/A',
        BloodGroup: s.bloodGroup || 'N/A',
      }));

      return exportToExcel(res, 'students', excelData);
    })
    .catch((err) => {
      if (err?.code === 'FORBIDDEN_CLASS') {
        return res.status(403).json({ msg: 'Access Denied: You cannot export data for this class' });
      }
      return res.status(500).json({ msg: 'Export failed', error: err.message });
    });
});

router.get('/students/csv', auth, roleCheck('superadmin', 'accounts', 'teacher'), (req, res) => {
  redirectExport(req, res, '/students', 'csv');
});

router.get('/attendance', auth, roleCheck('superadmin', 'teacher', 'accounts', 'hr'), (req, res) => {
  const format = normalizeExportFormat(req.query.format, 'csv');
  getAttendanceExportData(req.user, req.query)
    .then((attendance) => {
      if (format === 'pdf') {
        return exportToPDF(res, 'attendance', attendance, {
          title: 'ATTENDANCE REPORT',
          subtitle: `Records: ${attendance.length}`,
        });
      }

      const excelData = attendance.map(a => ({
        Date: a.date,
        StudentName: a.student?.name || 'N/A',
        AdmissionNo: a.student?.admissionNo || 'N/A',
        Class: a.class?.name || 'N/A',
        Status: a.status,
        Subject: a.subject || 'N/A',
        MarkedBy: a.teacherId || 'N/A',
      }));

      return exportToExcel(res, 'attendance', excelData);
    })
    .catch((err) => {
      if (err?.code === 'FORBIDDEN_CLASS') {
        return res.status(403).json({ msg: 'Access Denied: You cannot export data for this class' });
      }
      return res.status(500).json({ msg: 'Export failed', error: err.message });
    });
});

router.get('/attendance/csv', auth, roleCheck('superadmin', 'teacher', 'accounts', 'hr'), (req, res) => {
  redirectExport(req, res, '/attendance', 'csv');
});

router.get('/fees', auth, roleCheck('superadmin', 'accounts'), (req, res) => {
  const format = normalizeExportFormat(req.query.format, 'csv');
  getFeeExportData(req.query)
    .then((payments) => {
      if (format === 'pdf') {
        return exportToPDF(res, 'fees', payments, {
          title: 'FEE COLLECTION REPORT',
          subtitle: `Total Payments: ${payments.length}`,
        });
      }

      const excelData = payments.map(p => ({
        ReceiptNo: p.receiptNo,
        Date: new Date(p.paymentDate).toLocaleDateString(),
        StudentName: p.student?.name || 'N/A',
        AdmissionNo: p.student?.admissionNo || 'N/A',
        FeeType: p.feeType,
        Amount: p.amountPaid,
        PaymentMode: p.paymentMode,
        CollectedBy: p.collectedBy?.name || 'N/A',
      }));

      return exportToExcel(res, 'fees', excelData);
    })
    .catch((err) => res.status(500).json({ msg: 'Export failed', error: err.message }));
});

router.get('/fees/csv', auth, roleCheck('superadmin', 'accounts'), (req, res) => {
  redirectExport(req, res, '/fees', 'csv');
});

router.get('/exams', auth, roleCheck('superadmin', 'teacher'), (req, res) => {
  redirectExport(req, res, '/exams', 'csv');
});

router.get('/exams/csv', auth, roleCheck('superadmin', 'teacher'), (req, res) => {
  redirectExport(req, res, '/exams', 'csv');
});

router.get('/exam-results', auth, roleCheck('superadmin', 'teacher'), (req, res) => {
  redirectExport(req, res, '/exam-results', 'csv');
});

router.get('/exam-results/csv', auth, roleCheck('superadmin', 'teacher'), (req, res) => {
  redirectExport(req, res, '/exam-results', 'csv');
});

router.get('/library/csv', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), (req, res) => {
  redirectExport(req, res, '/library', 'csv');
});

router.get('/library', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), (req, res) => {
  redirectExport(req, res, '/library', 'csv');
});

router.get('/staff', auth, roleCheck('superadmin', 'hr', 'accounts'), (req, res) => {
  redirectExport(req, res, '/staff', 'csv');
});

router.get('/staff/csv', auth, roleCheck('superadmin', 'hr', 'accounts'), (req, res) => {
  redirectExport(req, res, '/staff', 'csv');
});

// ==================== BULK EXPORT ====================

router.get('/bulk-export', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { types } = req.query;
    const typeList = types.split(',');
    const exports = {};

    for (const type of typeList) {
      switch (type) {
        case 'students':
          exports.students = await prisma.student.findMany({ take: 100 });
          break;
        case 'attendance':
          exports.attendance = await prisma.attendance.findMany({ take: 100 });
          break;
        case 'fees':
          exports.fees = await prisma.feePayment.findMany({ take: 100 });
          break;
        case 'exams':
          exports.exams = await prisma.exam.findMany({ take: 100 });
          break;
        default:
          break;
      }
    }

    res.json({
      msg: 'Bulk export data ready',
      count: Object.keys(exports).length,
      data: exports,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Bulk export failed', error: err.message });
  }
});

module.exports = router;
