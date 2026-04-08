const prisma = require('../config/prisma');
const { toLegacyStudent } = require('./prismaCompat');

// Fix: Narrow selects to only needed fields for access control
const studentScopeInclude = {
  class: {
    select: {
      id: true,
      name: true,
      section: true,
      classTeacherId: true,
    },
  },
  parentUser: {
    select: {
      id: true,
      name: true,
      phone: true,
    },
  },
};

// Fix: Add per-request cache for teacher class IDs to prevent N+1 queries
const teacherClassIdCache = new Map();

function getTeacherClassIds(userId) {
  if (!userId) {
    return [];
  }

  // Return cached value if available for this request
  if (teacherClassIdCache.has(userId)) {
    return Promise.resolve(teacherClassIdCache.get(userId));
  }

  return prisma.class.findMany({
    where: {
      OR: [
        { classTeacherId: userId },
        {
          subjects: {
            some: {
              teacherId: userId,
            },
          },
        },
      ],
    },
    select: { id: true },
  }).then((classes) => {
    const ids = classes.map((item) => item.id);
    teacherClassIdCache.set(userId, ids);
    return ids;
  });
}

// Clear cache periodically (every 5 minutes)
setInterval(() => teacherClassIdCache.clear(), 5 * 60 * 1000);

async function getPrimaryStudentRecordForUser(userId) {
  if (!userId) {
    return null;
  }

  const records = await prisma.student.findMany({
    where: { userId },
    include: studentScopeInclude,
    orderBy: [
      { academicYear: 'desc' },
      { updatedAt: 'desc' },
      { createdAt: 'desc' },
    ],
  });

  if (!records.length) {
    return null;
  }

  if (records.length === 1) {
    return toLegacyStudent(records[0]);
  }

  const linkedUser = await prisma.user.findUnique({
    where: { id: userId },
    select: { name: true },
  });

  const normalizedUserName = String(linkedUser?.name || '').trim().toLowerCase();
  const exactNameMatch = normalizedUserName
    ? records.find((record) => String(record.name || '').trim().toLowerCase() === normalizedUserName)
    : null;

  return toLegacyStudent(exactNameMatch || records[0]);
}

async function getStudentRecordsForUser(user) {
  if (!user?.id || !user?.role) {
    return [];
  }

  if (user.role === 'student') {
    const student = await getPrimaryStudentRecordForUser(user.id);
    return student ? [student] : [];
  }

  if (user.role !== 'parent') {
    return [];
  }

  const records = await prisma.student.findMany({
    where: { parentUserId: user.id },
    include: studentScopeInclude,
    orderBy: [
      { academicYear: 'desc' },
      { updatedAt: 'desc' },
      { createdAt: 'desc' },
    ],
  });

  return records.map(toLegacyStudent);
}

async function getStudentIdsForUser(user) {
  const records = await getStudentRecordsForUser(user);
  return records.map((record) => String(record._id || record.id));
}

async function canTeacherAccessClass(userId, classId) {
  const allowedClassIds = await getTeacherClassIds(userId);
  return allowedClassIds.includes(String(classId));
}

async function canUserAccessStudent(user, studentId) {
  if (!user?.role) {
    return false;
  }

  if (['superadmin', 'accounts', 'hr'].includes(user.role)) {
    return true;
  }

  if (user.role === 'student') {
    const student = await getPrimaryStudentRecordForUser(user.id);
    return Boolean(student && String(student._id) === String(studentId));
  }

  if (user.role === 'parent') {
    // Fix: Use findUnique instead of findFirst for unique identifier
    const student = await prisma.student.findUnique({
      where: { id: studentId },
      select: { id: true, parentUserId: true },
    });

    return Boolean(student && student.parentUserId === user.id);
  }

  if (user.role === 'teacher') {
    const student = await prisma.student.findUnique({
      where: { id: studentId },
      select: { classId: true },
    });

    return Boolean(student && await canTeacherAccessClass(user.id, student.classId));
  }

  return false;
}

module.exports = {
  getTeacherClassIds,
  getStudentRecordsForUser,
  getStudentIdsForUser,
  canTeacherAccessClass,
  canUserAccessStudent,
};
