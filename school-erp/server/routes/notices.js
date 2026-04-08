const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { getTeacherClassIds, getStudentRecordsForUser } = require('../utils/accessScope');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');
const { withLegacyId, toLegacyClass, toLegacyStudent, toLegacyUser } = require('../utils/prismaCompat');

function isAudienceMatch(notice, role) {
  const audience = notice.audience || ['all'];
  return audience.includes('all') || audience.includes(role);
}

function toLegacyNotice(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    createdBy: record.createdBy ? toLegacyUser(record.createdBy) : record.createdById,
    relatedClassId: record.relatedClass ? toLegacyClass(record.relatedClass) : record.relatedClassId,
    relatedStudentId: record.relatedStudent ? toLegacyStudent(record.relatedStudent) : record.relatedStudentId,
  });
}

router.post('/', auth, roleCheck('superadmin', 'teacher'), async (req, res) => {
  try {
    // Teachers can only post to specific classes they teach, not school-wide
    if (req.user.role === 'teacher') {
      const { relatedClassId } = req.body;
      if (!relatedClassId) {
        return res.status(403).json({ msg: 'Teachers must select a specific class for notices.' });
      }
      const allowedClassIds = await getTeacherClassIds(req.user.id);
      if (!allowedClassIds.includes(String(relatedClassId))) {
        return res.status(403).json({ msg: 'You can only post notices for your assigned classes.' });
      }
      // Override audience to students+parents of that class only
      req.body.audience = req.body.audience || ['student', 'parent'];
      req.body.relatedStudentId = null; // teachers can't target individual students
    }

    const notice = await prisma.notice.create({
      data: {
        ...req.body,
        createdById: req.user.id,
      },
      include: {
        createdBy: { select: { id: true, name: true, role: true } },
        relatedClass: { select: { id: true, name: true, section: true } },
        relatedStudent: { select: { id: true, name: true, admissionNo: true, studentId: true } },
      },
    });
    res.status(201).json(toLegacyNotice(notice));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});


router.get('/', auth, async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const safeLimit = limit || 50;
    const fetchLimit = Math.min(500, safeLimit * 10);

    const notices = await prisma.notice.findMany({
      where: { published: true },
      include: {
        createdBy: { select: { id: true, name: true, role: true } },
        relatedClass: { select: { id: true, name: true, section: true } },
        relatedStudent: { select: { id: true, name: true, admissionNo: true, studentId: true } },
      },
      orderBy: { createdAt: 'desc' },
      take: fetchLimit,
    });

    if (req.user.role === 'superadmin') {
      const pagination = getPaginationData(page, safeLimit, notices.length);
      const data = notices.slice((page - 1) * safeLimit, page * safeLimit).map(toLegacyNotice);
      return res.json({ data, notices: data, pagination });
    }

    let relatedClassIds = [];
    let relatedStudentIds = [];

    if (req.user.role === 'teacher') {
      relatedClassIds = await getTeacherClassIds(req.user.id);
    } else if (['student', 'parent'].includes(req.user.role)) {
      const records = await getStudentRecordsForUser(req.user);
      relatedStudentIds = records.map(item => String(item._id || item.id));
      relatedClassIds = records
        .map(item => String(item.classId?._id || item.classId?.id || item.classId))
        .filter(Boolean);
    }

    const filtered = notices.filter((notice) => {
      if (!isAudienceMatch(notice, req.user.role)) return false;
      if (notice.relatedStudentId && relatedStudentIds.length) {
        return relatedStudentIds.includes(String(notice.relatedStudentId.id || notice.relatedStudentId));
      }
      if (notice.relatedClassId && relatedClassIds.length) {
        return relatedClassIds.includes(String(notice.relatedClassId.id || notice.relatedClassId));
      }
      if (notice.relatedStudentId || notice.relatedClassId) {
        return false;
      }
      return true;
    });

    const pagination = getPaginationData(page, safeLimit, filtered.length);
    const data = filtered.slice((page - 1) * safeLimit, page * safeLimit).map(toLegacyNotice);

    res.json({
      data,
      notices: data,
      pagination,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.put('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const notice = await prisma.notice.update({
      where: { id: req.params.id },
      data: req.body,
      include: {
        createdBy: { select: { id: true, name: true, role: true } },
        relatedClass: { select: { id: true, name: true, section: true } },
        relatedStudent: { select: { id: true, name: true, admissionNo: true, studentId: true } },
      },
    });
    res.json(toLegacyNotice(notice));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.delete('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    await prisma.notice.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Notice deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
