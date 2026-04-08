const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { canUserAccessStudent } = require('../utils/accessScope');
const { withLegacyId, toLegacyStudent, toLegacyUser } = require('../utils/prismaCompat');

function toLegacyRemark(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    teacherId: record.teacher ? toLegacyUser(record.teacher) : record.teacherId,
  });
}

// POST /api/remarks (Teacher only)
router.post('/', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    if (req.user.role === 'teacher') {
      if (!(await canUserAccessStudent(req.user, req.body.studentId))) {
        return res.status(403).json({ msg: 'Access Denied: Student not in your assigned classes' });
      }
    }
    const { studentId, remark: remarkText } = req.body;
    const remarkRecord = await prisma.remark.create({
      data: { studentId, remark: remarkText, teacherId: req.user.id },
    });
    res.status(201).json(toLegacyRemark(remarkRecord));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/remarks/my (Student, Parent)
router.get('/my', auth, async (req, res) => {
  try {
    let studentIds = [];
    if (req.user.role === 'student') {
      const stud = await prisma.student.findFirst({
        where: { userId: req.user.id },
        select: { id: true },
      });
      if (stud) studentIds.push(stud.id);
    } else if (req.user.role === 'parent') {
      const children = await prisma.student.findMany({
        where: { parentUserId: req.user.id },
        select: { id: true },
      });
      studentIds = children.map(c => c.id);
    }
    if (studentIds.length === 0) return res.json([]);
    const remarks = await prisma.remark.findMany({
      where: { studentId: { in: studentIds } },
      include: { teacher: { select: { id: true, name: true } } },
      orderBy: { createdAt: 'desc' },
    });
    res.json(remarks.map(toLegacyRemark));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/remarks/teacher (Teacher viewing their own)
router.get('/teacher', auth, roleCheck('teacher'), async (req, res) => {
  try {
    const remarks = await prisma.remark.findMany({
      where: { teacherId: req.user.id },
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
      },
      orderBy: { createdAt: 'desc' },
    });
    res.json(remarks.map(toLegacyRemark));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/remarks/student/:id
router.get('/student/:id', auth, async (req, res) => {
  try {
    if (!(await canUserAccessStudent(req.user, req.params.id))) {
      return res.status(403).json({ msg: 'Access Denied: Not authorized to view remarks for this student' });
    }

    const remarks = await prisma.remark.findMany({
      where: { studentId: req.params.id },
      include: { teacher: { select: { id: true, name: true } } },
      orderBy: { createdAt: 'desc' },
    });
    res.json(remarks.map(toLegacyRemark));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/remarks
router.get('/', auth, async (req, res) => {
  try {
    if (req.user.role === 'superadmin') {
      const remarks = await prisma.remark.findMany({
        include: {
          teacher: { select: { id: true, name: true } },
          student: { select: { id: true, name: true, admissionNo: true } },
        },
        orderBy: { createdAt: 'desc' },
      });
      return res.json(remarks.map(toLegacyRemark));
    }

    if (req.user.role === 'teacher') {
      const remarks = await prisma.remark.findMany({
        where: { teacherId: req.user.id },
        include: {
          teacher: { select: { id: true, name: true } },
          student: { select: { id: true, name: true, admissionNo: true } },
        },
        orderBy: { createdAt: 'desc' },
      });
      return res.json(remarks.map(toLegacyRemark));
    }

    if (req.user.role === 'student') {
      const student = await prisma.student.findFirst({
        where: { userId: req.user.id },
        select: { id: true },
      });
      if (!student) {
        return res.json([]);
      }

      const remarks = await prisma.remark.findMany({
        where: { studentId: student.id },
        include: { teacher: { select: { id: true, name: true } } },
        orderBy: { createdAt: 'desc' },
      });
      return res.json(remarks.map(toLegacyRemark));
    }

    if (req.user.role === 'parent') {
      const children = await prisma.student.findMany({
        where: { parentUserId: req.user.id },
        select: { id: true },
      });
      const childIds = children.map((child) => child.id);
      if (!childIds.length) {
        return res.json([]);
      }

      const remarks = await prisma.remark.findMany({
        where: { studentId: { in: childIds } },
        include: {
          teacher: { select: { id: true, name: true } },
          student: { select: { id: true, name: true, admissionNo: true } },
        },
        orderBy: { createdAt: 'desc' },
      });
      return res.json(remarks.map(toLegacyRemark));
    }

    res.status(403).json({ msg: 'Access denied' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/remarks/:id (Teacher/Admin edit)
router.put('/:id', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const remark = await prisma.remark.findUnique({ where: { id: req.params.id } });
    if (!remark) return res.status(404).json({ msg: 'Remark not found' });
    if (req.user.role !== 'superadmin' && remark.teacherId !== req.user.id) {
      return res.status(403).json({ msg: 'Not authorized to edit this remark' });
    }
    const updated = await prisma.remark.update({
      where: { id: req.params.id },
      data: { remark: req.body.remark || remark.remark },
    });
    res.json(toLegacyRemark(updated));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// DELETE /api/remarks/:id
router.delete('/:id', auth, roleCheck('teacher', 'superadmin'), async (req, res) => {
  try {
    const remark = await prisma.remark.delete({ where: { id: req.params.id } });
    if (!remark) return res.status(404).json({ msg: 'Remark not found' });
    res.json({ msg: 'Remark deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
