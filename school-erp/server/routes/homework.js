const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { canTeacherAccessClass, getStudentRecordsForUser, getTeacherClassIds } = require('../utils/accessScope');
const { withLegacyId, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyHomework(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    teacherId: record.teacher ? toLegacyUser(record.teacher) : record.teacherId,
  });
}

// GET /api/homework?classId=xxx
router.get('/', auth, async (req, res) => {
  try {
    const { classId } = req.query;
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = {};

    if (req.user.role === 'teacher') {
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (classId && !(await canTeacherAccessClass(req.user.id, classId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      where.classId = classId ? classId : { in: allowedClassIds };
    } else if (['student', 'parent'].includes(req.user.role)) {
      const students = await getStudentRecordsForUser(req.user);
      const classIds = [
        ...new Set(
          students
            .map(student => String(student.classId?._id || student.classId?.id || student.classId))
            .filter(Boolean)
        ),
      ];
      if (!classIds.length) {
        return res.json({
          data: [],
          homework: [],
          pagination: getPaginationData(page, limit, 0),
          meta: { query: { page, limit, sort: '-createdAt' } },
        });
      }
      where.classId = classId
        ? (classIds.includes(String(classId)) ? classId : '__forbidden__')
        : { in: classIds };
    } else if (classId) {
      where.classId = classId;
    }

    if (where.classId === '__forbidden__') {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const [total, homeworks] = await Promise.all([
      prisma.homework.count({ where }),
      prisma.homework.findMany({
        where,
        include: { teacher: { select: { id: true, name: true } } },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: homeworks.map(toLegacyHomework),
      homework: homeworks.map(toLegacyHomework),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/homework/my (Student/Parent view)
router.get('/my', auth, roleCheck('student', 'parent'), async (req, res) => {
  try {
    const students = await getStudentRecordsForUser(req.user);
    const classIds = [
      ...new Set(
        students
          .map(student => String(student.classId?._id || student.classId?.id || student.classId))
          .filter(Boolean)
      )
    ];
    if (classIds.length === 0) return res.status(404).json({ msg: 'No class assigned' });
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { classId: { in: classIds } };

    const [total, homeworks] = await Promise.all([
      prisma.homework.count({ where }),
      prisma.homework.findMany({
        where,
        include: { teacher: { select: { id: true, name: true } } },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: homeworks.map(toLegacyHomework),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// POST /api/homework (Teacher assigns homework)
router.post('/', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const { classId, subject, title, description, dueDate } = req.body;
    if (req.user.role === 'teacher' && !(await canTeacherAccessClass(req.user.id, classId))) {
      return res.status(403).json({ msg: 'You can only assign homework to your classes.' });
    }
    const homework = await prisma.homework.create({
      data: {
        classId,
        teacherId: req.user.id,
        subject,
        title,
        description,
        dueDate: new Date(dueDate),
      },
      include: { teacher: { select: { id: true, name: true } } },
    });

    const students = await prisma.student.findMany({
      where: { classId },
      select: { parentUserId: true },
    });
    const parentIds = [...new Set(students.filter(s => s.parentUserId).map(s => String(s.parentUserId)))];

    if (parentIds.length > 0) {
      await prisma.notification.createMany({
        data: parentIds.map(parentId => ({
          recipientId: parentId,
          senderId: req.user.id,
          title: 'New Homework Assigned',
          message: `New homework for ${subject}: ${title}. Due: ${new Date(dueDate).toDateString()}`,
          type: 'homework',
          relatedEntityId: homework.id,
        })),
      });
    }

    res.status(201).json(toLegacyHomework(homework));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/homework/:id (Teacher edits homework)
router.put('/:id', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const homework = await prisma.homework.update({
      where: { id: req.params.id },
      data: req.body,
      include: { teacher: { select: { id: true, name: true } } },
    });
    res.json(toLegacyHomework(homework));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// DELETE /api/homework/:id
router.delete('/:id', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    await prisma.homework.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Homework deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
