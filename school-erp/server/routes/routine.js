const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const buildRoutine = require('../services/routineEngine');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { withLegacyId } = require('../utils/prismaCompat');

const days = buildRoutine.days;
const periods = buildRoutine.periods;
const makeSlot = buildRoutine.makeSlot;

async function findTeacherSlotConflict({ classId, teacherId, slot }) {
  if (!teacherId) return null;

  const routines = await prisma.routine.findMany({
    where: { classId: { not: classId } },
    select: { classId: true, timetable: true },
  });

  return routines.find((routine) => {
    const entry = routine.timetable?.[slot];
    return entry && String(entry.teacherId) === String(teacherId);
  }) || null;
}

async function findSameDaySubjectConflict({ classId, day, subjectName, slot }) {
  const routine = await prisma.routine.findFirst({
    where: { classId },
    select: { timetable: true },
  });
  if (!routine?.timetable) return null;

  return Object.entries(routine.timetable).find(([entrySlot, entryValue]) => {
    if (entrySlot === slot) return false;
    if (!entrySlot.startsWith(`${day}-`)) return false;
    const entrySubject = entryValue?.subject || entryValue?.name;
    return entrySubject === subjectName;
  });
}

async function buildManualEntry({ classId, subjectName, teacherId }) {
  const cls = await prisma.class.findUnique({
    where: { id: classId },
    include: {
      subjects: { include: { teacher: { select: { id: true, name: true } } } },
    },
  });
  if (!cls) {
    return { error: { status: 404, msg: 'Class not found' } };
  }

  const subject = (cls.subjects || []).find((item) => {
    const itemName = item.subject || item.name;
    return itemName === subjectName;
  });

  const resolvedTeacherId = teacherId || subject?.teacherId || null;
  if (!resolvedTeacherId) {
    return { error: { status: 400, msg: 'Teacher is required for manual routine entry.' } };
  }

  const teacher = await prisma.user.findFirst({
    where: {
      id: resolvedTeacherId,
      role: { in: ['teacher', 'staff'] },
      isActive: true,
    },
    select: { id: true, name: true },
  });

  if (!teacher) {
    return { error: { status: 404, msg: 'Teacher not found' } };
  }

  return {
    cls,
    entry: {
      name: subject?.name || subject?.subject || subjectName || 'Subject',
      subject: subject?.subject || subject?.name || subjectName || 'Subject',
      teacherId: String(teacher.id),
      teacherName: teacher.name,
    },
  };
}

// POST /api/routine/generate
router.post('/generate', auth, roleCheck('superadmin'), async (_req, res) => {
  try {
    const routines = await buildRoutine();
    res.status(201).json(routines);
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/manual', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { classId, day, period, subjectName, teacherId } = req.body;
    if (!classId || !day || !period || !subjectName) {
      return res.status(400).json({ msg: 'Class, day, period, and subject are required.' });
    }
    if (!days.includes(day) || !periods.includes(period)) {
      return res.status(400).json({ msg: 'Invalid day or period.' });
    }

    const { error, entry } = await buildManualEntry({ classId, subjectName, teacherId });
    if (error) {
      return res.status(error.status).json({ msg: error.msg });
    }

    const slot = makeSlot(day, period);
    const sameDaySubjectConflict = await findSameDaySubjectConflict({
      classId,
      day,
      subjectName: entry.subject,
      slot,
    });

    if (sameDaySubjectConflict) {
      return res.status(400).json({ msg: 'This subject is already assigned once on the selected day for this class.' });
    }

    const teacherConflict = await findTeacherSlotConflict({
      classId,
      teacherId: entry.teacherId,
      slot,
    });

    if (teacherConflict) {
      return res.status(400).json({ msg: 'Teacher is already assigned to another class in this slot.' });
    }

    const existing = await prisma.routine.findFirst({
      where: { classId },
      select: { id: true, timetable: true },
    });
    const timetable = { ...(existing?.timetable || {}) };
    timetable[slot] = entry;

    const routine = existing
      ? await prisma.routine.update({
          where: { id: existing.id },
          data: { timetable },
        })
      : await prisma.routine.create({
          data: { classId, timetable },
        });

    res.status(201).json(withLegacyId(routine));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.delete('/manual', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { classId, day, period } = req.body;
    if (!classId || !day || !period) {
      return res.status(400).json({ msg: 'Class, day, and period are required.' });
    }

    const slot = makeSlot(day, period);
    const existing = await prisma.routine.findFirst({
      where: { classId },
      select: { id: true, timetable: true },
    });
    const timetable = { ...(existing?.timetable || {}) };
    delete timetable[slot];

    const routine = existing
      ? await prisma.routine.update({
          where: { id: existing.id },
          data: { timetable },
        })
      : await prisma.routine.create({
          data: { classId, timetable },
        });

    res.json(withLegacyId(routine));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// POST /manual/delete - Alternative endpoint for frontend compatibility
router.post('/manual/delete', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { classId, day, period } = req.body;
    if (!classId || !day || !period) {
      return res.status(400).json({ msg: 'Class, day, and period are required.' });
    }

    const slot = makeSlot(day, period);
    const existing = await prisma.routine.findFirst({
      where: { classId },
      select: { id: true, timetable: true },
    });
    const timetable = { ...(existing?.timetable || {}) };
    delete timetable[slot];

    const routine = existing
      ? await prisma.routine.update({
          where: { id: existing.id },
          data: { timetable },
        })
      : await prisma.routine.create({
          data: { classId, timetable },
        });

    res.json(withLegacyId(routine));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/routine/:classId
router.get('/:classId', auth, async (req, res) => {
  try {
    const routine = await prisma.routine.findFirst({ where: { classId: req.params.classId } });
    res.json(routine ? withLegacyId(routine) : { timetable: {} });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
