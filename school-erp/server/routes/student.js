const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const smsService = require('../services/smsService');
const upload = require('../middleware/upload');
const { generateAdmissionNo, generateStudentId } = require('../utils/idGenerator');
const { getTeacherClassIds, canUserAccessStudent } = require('../utils/accessScope');
const { validateUploadedFiles, cleanupUploadedFiles } = require('../utils/fileValidation');
const { generateTemporaryPassword } = require('../utils/security');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');
const { toLegacyStudent } = require('../utils/prismaCompat');

const MIN_PASSWORD_LENGTH = parseInt(process.env.MIN_PASSWORD_LENGTH || '8', 10);
const STUDENT_DIRECTORY_ROLES = new Set(['superadmin', 'accounts', 'teacher', 'student', 'parent']);
const STUDENT_SUMMARY_ROLES = new Set(['superadmin', 'accounts', 'teacher']);

const studentInclude = {
  user: {
    select: {
      id: true,
      name: true,
      email: true,
      phone: true,
      role: true,
      employeeId: true,
      department: true,
      designation: true,
      isActive: true,
      createdAt: true,
      updatedAt: true,
    },
  },
  class: {
    include: {
      classTeacher: true,
      subjects: {
        include: {
          teacher: true,
        },
      },
    },
  },
  parentUser: {
    select: {
      id: true,
      name: true,
      email: true,
      phone: true,
      role: true,
      employeeId: true,
      department: true,
      designation: true,
      isActive: true,
      createdAt: true,
      updatedAt: true,
    },
  },
};

function boolFromBody(value) {
  return value === true || value === 'true';
}

function buildStructuredAddress(body) {
  return {
    line1: body.addressLine1 || '',
    line2: body.addressLine2 || '',
    city: body.city || '',
    state: body.state || '',
    pincode: body.pincode || '',
  };
}

function parseSort(sortValue, fallback = [{ name: 'asc' }]) {
  const tokens = String(sortValue || '')
    .split(/\s+/)
    .map((token) => token.trim())
    .filter(Boolean);

  if (!tokens.length) {
    return fallback;
  }

  return tokens.map((token) => ({
    [token.startsWith('-') ? token.slice(1) : token]: token.startsWith('-') ? 'desc' : 'asc',
  }));
}

async function hashPassword(password) {
  const salt = await bcrypt.genSalt(10);
  return bcrypt.hash(password, salt);
}

async function attachParentChildLink(parentUserId, studentId) {
  if (!parentUserId) {
    return;
  }

  const parent = await prisma.user.findUnique({
    where: { id: parentUserId },
    select: { linkedStudentIds: true },
  });

  const current = Array.isArray(parent?.linkedStudentIds)
    ? parent.linkedStudentIds.filter(Boolean).map(String)
    : [];

  if (!current.includes(String(studentId))) {
    current.push(String(studentId));
    await prisma.user.update({
      where: { id: parentUserId },
      data: { linkedStudentIds: current },
    });
  }
}

async function detachParentChildLink(parentUserId, studentId) {
  if (!parentUserId) {
    return;
  }

  const parent = await prisma.user.findUnique({
    where: { id: parentUserId },
    select: { linkedStudentIds: true },
  });

  const current = Array.isArray(parent?.linkedStudentIds)
    ? parent.linkedStudentIds.filter((value) => String(value) !== String(studentId))
    : [];

  await prisma.user.update({
    where: { id: parentUserId },
    data: { linkedStudentIds: current },
  });
}

async function ensureParentUser({ parentEmail, parentPhone, fatherName, motherName, guardianName, studentName }) {
  if (!parentEmail && !parentPhone) {
    return { parentUser: null, generatedPassword: null };
  }

  let parentUser = null;
  const normalizedEmail = parentEmail ? String(parentEmail).trim().toLowerCase() : null;

  if (normalizedEmail) {
    parentUser = await prisma.user.findUnique({ where: { email: normalizedEmail } });
  }

  if (!parentUser && parentPhone) {
    parentUser = await prisma.user.findFirst({
      where: {
        role: 'parent',
        phone: parentPhone,
      },
    });
  }

  if (!parentUser) {
    const derivedName =
      fatherName ||
      motherName ||
      guardianName ||
      `${studentName}'s Parent`;
    const generatedPassword = generateTemporaryPassword();

    parentUser = await prisma.user.create({
      data: {
        name: derivedName,
        email: normalizedEmail || `parent.${Date.now()}@school.local`,
        password: await hashPassword(generatedPassword),
        role: 'parent',
        phone: parentPhone || '0000000000',
        passwordChangeRequired: true,
      },
    });

    return { parentUser, generatedPassword };
  }

  return { parentUser, generatedPassword: null };
}

function isUniqueConstraintError(error) {
  return error?.code === 'P2002';
}

function buildStudentSearchConditions(search) {
  const value = String(search || '').trim();
  if (!value) {
    return [];
  }

  return [
    { name: { contains: value } },
    { admissionNo: { contains: value } },
    { studentId: { contains: value } },
    { aadhaar: { contains: value } },
    { parentPhone: { contains: value } },
  ];
}

function buildStudentListResponse(data, page, limit, total, sort = 'name') {
  return {
    data,
    students: data,
    pagination: getPaginationData(page, limit, total),
    meta: {
      query: {
        page,
        limit,
        sort,
      },
    },
  };
}

router.post('/admit', auth, roleCheck('superadmin'), upload.fields([
  { name: 'tcFile', maxCount: 1 },
  { name: 'birthCertFile', maxCount: 1 },
]), async (req, res) => {
  try {
    const {
      name, email, password, phone, admissionNo, classId,
      parentPhone, parentEmail, dob, gender, section, rollNumber,
      aadhaar, apaarId, pen, enrollmentNo, academicYear, bloodGroup,
      nationality, religion, category, motherTongue, previousSchool,
      fatherName, fatherOccupation, motherName, motherPhone, motherOccupation,
      guardianName, guardianRelation, guardianPhone,
      emergencyContactName, emergencyContactPhone,
      transportRequired, hostelRequired, medicalConditions, allergies, admissionNotes,
    } = req.body;

    if (!name || !classId || !parentPhone || !dob || !gender) {
      await cleanupUploadedFiles(req.files);
      return res.status(400).json({ msg: 'Name, class, parent phone, DOB, and gender are required.' });
    }

    await validateUploadedFiles(req.files);

    if (password && password.length < MIN_PASSWORD_LENGTH) {
      await cleanupUploadedFiles(req.files);
      return res.status(400).json({ msg: `Password must be at least ${MIN_PASSWORD_LENGTH} characters long.` });
    }

    let finalAdmissionNo = admissionNo || await generateAdmissionNo();
    let finalStudentId = await generateStudentId();

    while (await prisma.student.findUnique({ where: { admissionNo: finalAdmissionNo } })) {
      finalAdmissionNo = await generateAdmissionNo();
    }

    while (await prisma.student.findUnique({ where: { studentId: finalStudentId } })) {
      finalStudentId = await generateStudentId();
    }

    const generatedStudentPassword = password || generateTemporaryPassword();
    const user = await prisma.user.create({
      data: {
        name,
        email: email || `${finalAdmissionNo.toLowerCase().replace(/[^a-z0-9]/g, '')}@student.school`,
        password: await hashPassword(generatedStudentPassword),
        role: 'student',
        phone: phone || parentPhone,
        passwordChangeRequired: !password,
      },
    });

    const { parentUser, generatedPassword: generatedParentPassword } = await ensureParentUser({
      parentEmail,
      parentPhone,
      fatherName,
      motherName,
      guardianName,
      studentName: name,
    });

    const student = await prisma.student.create({
      data: {
        userId: user.id,
        name,
        studentId: finalStudentId,
        admissionNo: finalAdmissionNo,
        classId,
        parentPhone,
        parentEmail,
        parentUserId: parentUser?.id || null,
        dob: new Date(dob),
        gender,
        section,
        rollNumber,
        academicYear: academicYear || `${new Date().getFullYear()}-${new Date().getFullYear() + 1}`,
        aadhaar,
        apaarId,
        pen,
        enrollmentNo,
        bloodGroup,
        nationality,
        religion,
        category,
        motherTongue,
        previousSchool,
        fatherName,
        fatherOccupation,
        motherName,
        motherPhone,
        motherOccupation,
        guardianName,
        guardianRelation,
        guardianPhone,
        emergencyContactName,
        emergencyContactPhone,
        structuredAddress: buildStructuredAddress(req.body),
        transportRequired: boolFromBody(transportRequired),
        hostelRequired: boolFromBody(hostelRequired),
        medicalConditions,
        allergies,
        admissionNotes,
        tcFileUrl: req.files?.tcFile ? `/uploads/students/${req.files.tcFile[0].filename}` : null,
        birthCertFileUrl: req.files?.birthCertFile ? `/uploads/students/${req.files.birthCertFile[0].filename}` : null,
      },
      include: studentInclude,
    });

    await attachParentChildLink(parentUser?.id, student.id);

    if (parentPhone) {
      await smsService.send({
        to: parentPhone,
        message: `Welcome! ${name} admitted. Admission No: ${finalAdmissionNo}, Student ID: ${finalStudentId}. - ${process.env.SCHOOL_NAME}`,
      }).catch(() => {});
    }

    const generatedCredentials = {
      student: !password ? {
        email: user.email,
        password: generatedStudentPassword,
      } : null,
      parent: generatedParentPassword ? {
        email: parentUser.email,
        password: generatedParentPassword,
      } : null,
    };

    res.status(201).json({
      msg: 'Student admitted successfully',
      student: toLegacyStudent(student),
      generatedCredentials,
    });
  } catch (err) {
    await cleanupUploadedFiles(req.files);
    console.error('Admission error:', err);
    if (isUniqueConstraintError(err)) {
      return res.status(400).json({ msg: 'Duplicate admission number, student ID, email, or Aadhaar detected.' });
    }
    const isUploadValidationError = /Unsupported file type|File content does not match/i.test(err.message || '');
    res.status(isUploadValidationError ? 400 : 500).json({
      msg: isUploadValidationError ? err.message : 'Server Error',
    });
  }
});

router.get('/stats/summary', auth, async (req, res) => {
  try {
    if (!STUDENT_SUMMARY_ROLES.has(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const [
      totalStudents,
      maleCount,
      femaleCount,
      transportRequired,
      hostelRequired,
      byCategoryRaw,
      byClassRaw,
    ] = await Promise.all([
      prisma.student.count(),
      prisma.student.count({ where: { gender: 'male' } }),
      prisma.student.count({ where: { gender: 'female' } }),
      prisma.student.count({ where: { transportRequired: true } }),
      prisma.student.count({ where: { hostelRequired: true } }),
      prisma.student.groupBy({ by: ['category'], _count: { _all: true } }),
      prisma.student.groupBy({ by: ['classId'], _count: { _all: true } }),
    ]);

    const classMap = new Map(
      (await prisma.class.findMany({
        where: { id: { in: byClassRaw.map((item) => item.classId) } },
        select: { id: true, name: true, section: true },
      })).map((item) => [item.id, item])
    );

    res.json({
      total: totalStudents,
      gender: { male: maleCount, female: femaleCount, other: totalStudents - maleCount - femaleCount },
      transport: transportRequired,
      hostel: hostelRequired,
      byCategory: byCategoryRaw.map((item) => ({ _id: item.category, count: item._count._all })),
      byClass: byClassRaw.map((item) => ({
        _id: item.classId,
        className: classMap.get(item.classId)?.name || item.classId,
        section: classMap.get(item.classId)?.section || null,
        count: item._count._all,
      })),
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/stats', auth, async (req, res) => {
  try {
    if (!STUDENT_SUMMARY_ROLES.has(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const [
      totalStudents,
      maleCount,
      femaleCount,
      transportRequired,
      hostelRequired,
      byCategoryRaw,
      byClassRaw,
    ] = await Promise.all([
      prisma.student.count(),
      prisma.student.count({ where: { gender: 'male' } }),
      prisma.student.count({ where: { gender: 'female' } }),
      prisma.student.count({ where: { transportRequired: true } }),
      prisma.student.count({ where: { hostelRequired: true } }),
      prisma.student.groupBy({ by: ['category'], _count: { _all: true } }),
      prisma.student.groupBy({ by: ['classId'], _count: { _all: true } }),
    ]);

    const classMap = new Map(
      (await prisma.class.findMany({
        where: { id: { in: byClassRaw.map((item) => item.classId) } },
        select: { id: true, name: true, section: true },
      })).map((item) => [item.id, item])
    );

    res.json({
      total: totalStudents,
      gender: { male: maleCount, female: femaleCount, other: totalStudents - maleCount - femaleCount },
      transport: transportRequired,
      hostel: hostelRequired,
      byCategory: byCategoryRaw.map((item) => ({ _id: item.category, count: item._count._all })),
      byClass: byClassRaw.map((item) => ({
        _id: item.classId,
        className: classMap.get(item.classId)?.name || item.classId,
        section: classMap.get(item.classId)?.section || null,
        count: item._count._all,
      })),
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/class/:classId', auth, async (req, res) => {
  try {
    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (!allowedClassIds.includes(String(req.params.classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { classId: req.params.classId };

    const [total, students] = await Promise.all([
      prisma.student.count({ where }),
      prisma.student.findMany({
        where,
        include: studentInclude,
        orderBy: { name: 'asc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: students.map(toLegacyStudent),
      students: students.map(toLegacyStudent),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: 'name' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/search', auth, async (req, res) => {
  try {
    if (!STUDENT_DIRECTORY_ROLES.has(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const {
      classId, section, gender, category,
      transportRequired, hostelRequired, academicYear,
    } = req.query;
    const search = req.query.search || req.query.q;
    const filters = [];

    if (classId) filters.push({ classId });
    if (section) filters.push({ section });
    if (gender) filters.push({ gender });
    if (category) filters.push({ category });
    if (transportRequired) filters.push({ transportRequired: transportRequired === 'true' });
    if (hostelRequired) filters.push({ hostelRequired: hostelRequired === 'true' });
    if (academicYear) filters.push({ academicYear });

    const searchConditions = buildStudentSearchConditions(search);
    if (searchConditions.length) {
      filters.push({ OR: searchConditions });
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      filters.push({
        classId: classId
          ? { in: allowedClassIds.filter((id) => id === String(classId)) }
          : { in: allowedClassIds },
      });
    }

    if (req.user.role === 'student') {
      filters.push({ userId: req.user.id });
    }

    if (req.user.role === 'parent') {
      filters.push({ parentUserId: req.user.id });
    }

    const where = filters.length ? { AND: filters } : {};
    const { page, limit, sort } = parsePaginationParams(req.query);
    const requestedLimit = req.query.limit
      ? Math.min(100, Math.max(1, parseInt(req.query.limit, 10) || 20))
      : 50;
    const orderBy = req.user.role === 'student'
      ? [{ academicYear: 'desc' }, { updatedAt: 'desc' }, { createdAt: 'desc' }]
      : parseSort(sort, [{ name: 'asc' }]);

    const [total, records] = await Promise.all([
      prisma.student.count({ where }),
      prisma.student.findMany({
        where,
        include: studentInclude,
        orderBy,
        skip: (page - 1) * requestedLimit,
        take: requestedLimit,
      }),
    ]);

    const data = records.map(toLegacyStudent);
    res.json(buildStudentListResponse(data, page, requestedLimit, total, sort));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/', auth, async (req, res) => {
  try {
    if (!STUDENT_DIRECTORY_ROLES.has(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const {
      classId, section, search, gender, category,
      transportRequired, hostelRequired, academicYear,
    } = req.query;

    const filters = [];

    if (classId) filters.push({ classId });
    if (section) filters.push({ section });
    if (gender) filters.push({ gender });
    if (category) filters.push({ category });
    if (transportRequired) filters.push({ transportRequired: transportRequired === 'true' });
    if (hostelRequired) filters.push({ hostelRequired: hostelRequired === 'true' });
    if (academicYear) filters.push({ academicYear });

    const searchConditions = buildStudentSearchConditions(search);
    if (searchConditions.length) {
      filters.push({ OR: searchConditions });
    }

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      filters.push({
        classId: classId
          ? { in: allowedClassIds.filter((id) => id === String(classId)) }
          : { in: allowedClassIds },
      });
    }

    if (req.user.role === 'student') {
      filters.push({ userId: req.user.id });
    }

    if (req.user.role === 'parent') {
      filters.push({ parentUserId: req.user.id });
    }

    const where = filters.length ? { AND: filters } : {};
    const { page, limit, sort } = parsePaginationParams(req.query);
    const requestedLimit = req.query.limit
      ? Math.min(100, Math.max(1, parseInt(req.query.limit, 10) || 20))
      : 50;
    const orderBy = req.user.role === 'student'
      ? [{ academicYear: 'desc' }, { updatedAt: 'desc' }, { createdAt: 'desc' }]
      : parseSort(sort, [{ name: 'asc' }]);

    const [total, records] = await Promise.all([
      prisma.student.count({ where }),
      prisma.student.findMany({
        where,
        include: studentInclude,
        orderBy,
        skip: (page - 1) * requestedLimit,
        take: requestedLimit,
      }),
    ]);

    const data = records.map(toLegacyStudent);

    if (req.user.role === 'student') {
      const ownData = data.slice(0, 1);
      return res.json(ownData);
    }

    res.json(buildStudentListResponse(data, page, requestedLimit, total, sort));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/:id', auth, async (req, res) => {
  try {
    const hasAccess = await canUserAccessStudent(req.user, req.params.id);
    if (!hasAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const student = await prisma.student.findUnique({
      where: { id: req.params.id },
      include: studentInclude,
    });

    if (!student) {
      return res.status(404).json({ msg: 'Student not found' });
    }

    const [totalAttendance, presentCount, feePayments] = await Promise.all([
      prisma.attendance.count({ where: { studentId: student.id } }),
      prisma.attendance.count({ where: { studentId: student.id, status: 'present' } }),
      prisma.feePayment.findMany({
        where: { studentId: student.id },
        orderBy: { createdAt: 'desc' },
        take: 10,
      }),
    ]);

    res.json({
      student: toLegacyStudent(student),
      attendanceStats: {
        total: totalAttendance,
        present: presentCount,
        percentage: totalAttendance > 0 ? Math.round((presentCount / totalAttendance) * 100) : 0,
      },
      recentFeePayments: feePayments.map((payment) => ({ ...payment, _id: payment.id })),
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.put('/:id', auth, roleCheck('superadmin', 'accounts', 'teacher'), upload.fields([
  { name: 'tcFile', maxCount: 1 },
  { name: 'birthCertFile', maxCount: 1 },
]), async (req, res) => {
  try {
    if (req.user.role === 'teacher') {
      const allowed = await canUserAccessStudent(req.user, req.params.id);
      if (!allowed) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    await validateUploadedFiles(req.files);

    const updateData = { ...req.body };
    if (req.files?.tcFile) updateData.tcFileUrl = `/uploads/students/${req.files.tcFile[0].filename}`;
    if (req.files?.birthCertFile) updateData.birthCertFileUrl = `/uploads/students/${req.files.birthCertFile[0].filename}`;

    if (req.body.addressLine1 || req.body.addressLine2 || req.body.city || req.body.state || req.body.pincode) {
      updateData.structuredAddress = buildStructuredAddress(req.body);
    }

    delete updateData.addressLine1;
    delete updateData.addressLine2;
    delete updateData.city;
    delete updateData.state;
    delete updateData.pincode;

    const student = await prisma.student.update({
      where: { id: req.params.id },
      data: {
        ...updateData,
        dob: updateData.dob ? new Date(updateData.dob) : undefined,
        transportRequired: updateData.transportRequired !== undefined ? boolFromBody(updateData.transportRequired) : undefined,
        hostelRequired: updateData.hostelRequired !== undefined ? boolFromBody(updateData.hostelRequired) : undefined,
      },
      include: studentInclude,
    });

    if (student?.userId) {
      const userUpdates = {};
      if (req.body.name) userUpdates.name = req.body.name;
      if (req.body.phone) userUpdates.phone = req.body.phone;
      if (Object.keys(userUpdates).length > 0) {
        await prisma.user.update({
          where: { id: student.user.id },
          data: userUpdates,
        });
      }
    }

    res.json({ msg: 'Student updated successfully', student: toLegacyStudent(student) });
  } catch (err) {
    await cleanupUploadedFiles(req.files);
    const isUploadValidationError = /Unsupported file type|File content does not match/i.test(err.message || '');
    res.status(isUploadValidationError ? 400 : 500).json({
      msg: isUploadValidationError ? err.message : 'Server Error',
    });
  }
});

router.delete('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const student = await prisma.student.findUnique({
      where: { id: req.params.id },
      select: {
        id: true,
        userId: true,
        parentUserId: true,
      },
    });

    if (!student) {
      return res.status(404).json({ msg: 'Student not found' });
    }

    await detachParentChildLink(student.parentUserId, student.id);
    await prisma.student.delete({ where: { id: req.params.id } });
    await prisma.user.delete({ where: { id: student.userId } });

    res.json({ msg: 'Student discharged successfully' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.post('/bulk-import', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { students } = req.body;
    if (!Array.isArray(students) || students.length === 0) {
      return res.status(400).json({ msg: 'Invalid data. Provide an array of students.' });
    }

    const results = { success: [], failed: [] };

    for (const stu of students) {
      try {
        let finalAdmissionNo = stu.admissionNo || await generateAdmissionNo();
        let finalStudentId = await generateStudentId();

        while (await prisma.student.findUnique({ where: { admissionNo: finalAdmissionNo } })) {
          finalAdmissionNo = await generateAdmissionNo();
        }

        while (await prisma.student.findUnique({ where: { studentId: finalStudentId } })) {
          finalStudentId = await generateStudentId();
        }

        if (stu.password && String(stu.password).length < MIN_PASSWORD_LENGTH) {
          throw new Error(`Password must be at least ${MIN_PASSWORD_LENGTH} characters long.`);
        }

        const generatedStudentPassword = stu.password || generateTemporaryPassword();
        const user = await prisma.user.create({
          data: {
            name: stu.name,
            email: stu.email || `${finalAdmissionNo.toLowerCase().replace(/[^a-z0-9]/g, '')}@student.school`,
            password: await hashPassword(generatedStudentPassword),
            role: 'student',
            phone: stu.phone || stu.parentPhone,
            passwordChangeRequired: !stu.password,
          },
        });

        const { parentUser, generatedPassword: generatedParentPassword } = await ensureParentUser({
          parentEmail: stu.parentEmail,
          parentPhone: stu.parentPhone,
          fatherName: stu.fatherName,
          motherName: stu.motherName,
          guardianName: stu.guardianName,
          studentName: stu.name,
        });

        const student = await prisma.student.create({
          data: {
            userId: user.id,
            name: stu.name,
            studentId: finalStudentId,
            admissionNo: finalAdmissionNo,
            classId: stu.classId,
            parentPhone: stu.parentPhone,
            parentEmail: stu.parentEmail,
            parentUserId: parentUser?.id || null,
            dob: new Date(stu.dob),
            gender: stu.gender,
            section: stu.section,
            rollNumber: stu.rollNumber,
            aadhaar: stu.aadhaar,
          },
        });

        await attachParentChildLink(parentUser?.id, student.id);

        results.success.push({
          admissionNo: finalAdmissionNo,
          studentId: finalStudentId,
          name: stu.name,
          generatedCredentials: {
            student: !stu.password ? {
              email: user.email,
              password: generatedStudentPassword,
            } : null,
            parent: generatedParentPassword ? {
              email: parentUser.email,
              password: generatedParentPassword,
            } : null,
          },
        });
      } catch (err) {
        results.failed.push({ data: stu, error: err.message });
      }
    }

    res.json({ msg: `Imported ${results.success.length} students. ${results.failed.length} failed.`, results });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.put('/:id/promote', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    if (req.user.role === 'teacher') {
      const allowed = await canUserAccessStudent(req.user, req.params.id);
      if (!allowed) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    const { newClassId, remarks } = req.body;
    if (!newClassId) {
      return res.status(400).json({ msg: 'New class ID is required' });
    }

    const current = await prisma.student.findUnique({
      where: { id: req.params.id },
      select: { admissionNotes: true },
    });

    const note = remarks
      ? [current?.admissionNotes, `Promoted: ${remarks}`].filter(Boolean).join(' | ')
      : current?.admissionNotes;

    const student = await prisma.student.update({
      where: { id: req.params.id },
      data: {
        classId: newClassId,
        admissionNotes: note,
      },
      include: studentInclude,
    });

    res.json({ msg: 'Student promoted successfully', student: toLegacyStudent(student) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

module.exports = router;
