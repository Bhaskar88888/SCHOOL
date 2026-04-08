const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { getTeacherClassIds } = require('../utils/accessScope');
const { toLegacyClass, toLegacyStudent, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

const classInclude = {
  classTeacher: true,
  subjects: {
    include: {
      teacher: true,
    },
  },
};

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
        include: { teacher: true },
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

function normalizeSubjects(subjects = []) {
  return subjects.map((item) => ({
    name: item.name || item.subject || '',
    subject: item.subject || item.name || '',
    teacherId: item.teacherId || null,
    periodsPerWeek: Number(item.periodsPerWeek || 4),
  }));
}

async function buildClassWithStats(record) {
  const cls = toLegacyClass(record);
  const sections = Array.isArray(cls.sections) && cls.sections.length ? cls.sections : [cls.section || 'A'];

  const [studentCount, sectionStats] = await Promise.all([
    prisma.student.count({ where: { classId: record.id } }),
    Promise.all(
      sections.map(async (section) => ({
        section,
        count: await prisma.student.count({ where: { classId: record.id, section } }),
      }))
    ),
  ]);

  return {
    ...cls,
    studentCount,
    sectionStats,
    subjectCount: cls.subjects?.length || 0,
  };
}

function buildClassListResponse(classesWithStats, page, limit, total) {
  return {
    data: classesWithStats,
    classes: classesWithStats,
    pagination: getPaginationData(page, limit, total),
    meta: { query: { page, limit, sort: 'name,section' } },
  };
}

router.get('/stats/summary', auth, async (_req, res) => {
  try {
    const [classes, totalStudents, classesWithTeachers, studentDistribution] = await Promise.all([
      prisma.class.findMany({
        select: {
          id: true,
          sections: true,
        },
      }),
      prisma.student.count(),
      prisma.class.count({ where: { classTeacherId: { not: null } } }),
      prisma.student.groupBy({
        by: ['classId'],
        _count: { _all: true },
      }),
    ]);

    const classNames = await prisma.class.findMany({
      where: { id: { in: studentDistribution.map((item) => item.classId) } },
      select: { id: true, name: true },
    });

    const classNameMap = new Map(classNames.map((item) => [item.id, item.name]));

    res.json({
      totalClasses: classes.length,
      totalStudents,
      totalSections: classes.reduce((sum, cls) => sum + (Array.isArray(cls.sections) ? cls.sections.length : 0), 0),
      classesWithTeachers,
      classesWithoutTeachers: classes.length - classesWithTeachers,
      studentDistribution: studentDistribution
        .map((item) => ({
          _id: classNameMap.get(item.classId) || item.classId,
          count: item._count._all,
        }))
        .sort((a, b) => String(a._id).localeCompare(String(b._id))),
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/teachers/list', auth, async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = {
      role: { in: ['teacher', 'staff'] },
      isActive: true,
    };

    const [total, teachers] = await Promise.all([
      prisma.user.count({ where }),
      prisma.user.findMany({
        where,
        select: {
          id: true,
          name: true,
          email: true,
          phone: true,
          employeeId: true,
          department: true,
          designation: true,
          role: true,
          isActive: true,
          createdAt: true,
          updatedAt: true,
        },
        orderBy: { name: 'asc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: teachers.map((teacher) => toLegacyUser(teacher)),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: 'name' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { name, sections, classTeacherId, classTeacher, subjects, capacity, academicYear } = req.body;
    const resolvedTeacherId = classTeacherId || classTeacher || null;
    const resolvedSections = Array.isArray(sections) && sections.length ? sections : ['A'];

    if (!name) {
      return res.status(400).json({ msg: 'Class name is required' });
    }

    const created = await prisma.class.create({
      data: {
        name,
        sections: resolvedSections,
        section: req.body.section || resolvedSections[0] || 'A',
        classTeacherId: resolvedTeacherId,
        capacity: Number(capacity || 60),
        academicYear: academicYear || new Date().getFullYear().toString(),
        subjects: {
          create: normalizeSubjects(subjects),
        },
      },
      include: classInclude,
    });

    res.status(201).json({ msg: 'Class created successfully', class: toLegacyClass(created) });
  } catch (err) {
    console.error('Class creation error:', err);
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/', auth, async (req, res) => {
  try {
    let classIds = null;

    if (req.user.role === 'teacher') {
      classIds = await getTeacherClassIds(req.user.id);
    } else if (req.user.role === 'student') {
      const student = await prisma.student.findFirst({
        where: { userId: req.user.id },
        select: { classId: true },
      });
      classIds = student?.classId ? [student.classId] : [];
    } else if (req.user.role === 'parent') {
      const children = await prisma.student.findMany({
        where: { parentUserId: req.user.id },
        select: { classId: true },
      });
      classIds = [...new Set(children.map((item) => item.classId).filter(Boolean))];
    }

    const where = classIds ? { id: { in: classIds } } : undefined;
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, classes] = await Promise.all([
      prisma.class.count({ where }),
      prisma.class.findMany({
        where,
        include: classInclude,
        orderBy: [
          { name: 'asc' },
          { section: 'asc' },
        ],
        skip,
        take: limit,
      }),
    ]);

    const classesWithStats = await Promise.all(classes.map(buildClassWithStats));
    res.json(buildClassListResponse(classesWithStats, page, limit, total));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.get('/:id', auth, async (req, res) => {
  try {
    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (!allowedClassIds.includes(String(req.params.id))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    const cls = await prisma.class.findUnique({
      where: { id: req.params.id },
      include: classInclude,
    });

    if (!cls) {
      return res.status(404).json({ msg: 'Class not found' });
    }

    const students = await prisma.student.findMany({
      where: { classId: req.params.id },
      include: studentInclude,
      orderBy: { name: 'asc' },
    });

    const sections = Array.isArray(cls.sections) && cls.sections.length ? cls.sections : [cls.section || 'A'];
    const sectionStudents = sections.map((section) => ({
      section,
      students: students.filter((student) => student.section === section).map(toLegacyStudent),
    }));

    const subjectTeachers = (cls.subjects || []).map((subject) => ({
      subject: subject.subject,
      teacher: subject.teacher ? toLegacyUser(subject.teacher) : subject.teacherId,
    }));

    res.json({
      class: toLegacyClass(cls),
      totalStudents: students.length,
      sectionStudents,
      subjectTeachers,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.put('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { sections, classTeacherId, classTeacher, subjects, capacity, section } = req.body;

    const existing = await prisma.class.findUnique({
      where: { id: req.params.id },
      include: { subjects: true },
    });

    if (!existing) {
      return res.status(404).json({ msg: 'Class not found' });
    }

    const updateData = {};
    if (sections) updateData.sections = sections;
    if (section !== undefined) updateData.section = section;
    if (classTeacherId !== undefined || classTeacher !== undefined) {
      updateData.classTeacherId = classTeacherId || classTeacher || null;
    }
    if (capacity) updateData.capacity = Number(capacity);

    if (subjects) {
      await prisma.classSubject.deleteMany({ where: { classId: req.params.id } });
      updateData.subjects = {
        create: normalizeSubjects(subjects),
      };
    }

    const cls = await prisma.class.update({
      where: { id: req.params.id },
      data: updateData,
      include: classInclude,
    });

    res.json({ msg: 'Class updated successfully', class: toLegacyClass(cls) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.delete('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const studentCount = await prisma.student.count({ where: { classId: req.params.id } });

    if (studentCount > 0) {
      return res.status(400).json({
        msg: `Cannot delete class with ${studentCount} enrolled students. Transfer or discharge students first.`,
      });
    }

    await prisma.class.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Class deleted successfully' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.post('/:id/assign-teacher', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { subject, teacherId } = req.body;

    if (!subject || !teacherId) {
      return res.status(400).json({ msg: 'Subject and teacher ID are required' });
    }

    const teacher = await prisma.user.findUnique({
      where: { id: teacherId },
      select: { id: true, role: true },
    });

    if (!teacher || !['teacher', 'staff'].includes(teacher.role)) {
      return res.status(400).json({ msg: 'Invalid teacher selected' });
    }

    const cls = await prisma.class.findUnique({ where: { id: req.params.id } });
    if (!cls) {
      return res.status(404).json({ msg: 'Class not found' });
    }

    const existingSubject = await prisma.classSubject.findFirst({
      where: {
        classId: req.params.id,
        subject,
      },
    });

    if (existingSubject) {
      await prisma.classSubject.update({
        where: { id: existingSubject.id },
        data: { teacherId },
      });
    } else {
      await prisma.classSubject.create({
        data: {
          classId: req.params.id,
          subject,
          name: subject,
          teacherId,
        },
      });
    }

    const updated = await prisma.class.findUnique({
      where: { id: req.params.id },
      include: classInclude,
    });

    res.json({ msg: `Teacher assigned to ${subject}`, class: toLegacyClass(updated) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.delete('/:id/remove-subject/:subject', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const cls = await prisma.class.findUnique({ where: { id: req.params.id } });
    if (!cls) {
      return res.status(404).json({ msg: 'Class not found' });
    }

    await prisma.classSubject.deleteMany({
      where: {
        classId: req.params.id,
        subject: req.params.subject,
      },
    });

    const updated = await prisma.class.findUnique({
      where: { id: req.params.id },
      include: classInclude,
    });

    res.json({ msg: 'Subject removed', class: toLegacyClass(updated) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

module.exports = router;
