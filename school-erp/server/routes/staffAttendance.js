const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const { withLegacyId, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyStaffAttendance(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    staffId: record.staff ? toLegacyUser(record.staff) : record.staffId,
  });
}

function canManageAllStaffAttendance(user) {
  return ['superadmin', 'hr', 'accounts'].includes(user?.role);
}

function resolveAllowedStaffIds(req) {
  if (canManageAllStaffAttendance(req.user)) {
    return null;
  }

  return [String(req.user.id)];
}

async function saveAttendance(req, res) {
  try {
    const adminMode = canManageAllStaffAttendance(req.user);
    const date = req.body.date;
    let records = Array.isArray(req.body.records)
      ? req.body.records
      : (req.body.staffId ? [{
          staffId: req.body.staffId,
          status: req.body.status,
          remarks: req.body.remarks || '',
        }] : null);

    if (!date || !Array.isArray(records) || records.length === 0) {
      return res.status(400).json({ msg: 'date and records are required' });
    }

    if (!adminMode) {
      records = records.map((record) => ({
        ...record,
        staffId: req.user.id,
      }));
    }

    if (records.some((record) => !record.staffId || !record.status)) {
      return res.status(400).json({ msg: 'staffId and status are required for every record' });
    }

    const targetDate = new Date(date);

    await prisma.$transaction(
      records.map(record => prisma.staffAttendance.upsert({
        where: {
          staffId_date: {
            staffId: record.staffId,
            date: targetDate,
          },
        },
        update: { status: record.status, remarks: record.remarks || '' },
        create: { staffId: record.staffId, date: targetDate, status: record.status, remarks: record.remarks || '' },
      }))
    );

    res.json({ msg: 'Attendance saved successfully' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
}

async function getAttendanceForDate(req, res) {
  try {
    const dateValue = req.params.date || req.query.date;
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = {};
    const allowedStaffIds = resolveAllowedStaffIds(req);

    if (dateValue) {
      const targetDate = new Date(dateValue);
      const startOfDay = new Date(targetDate);
      startOfDay.setHours(0, 0, 0, 0);
      const endOfDay = new Date(targetDate);
      endOfDay.setHours(23, 59, 59, 999);
      where.date = { gte: startOfDay, lte: endOfDay };
    }

    if (allowedStaffIds) {
      where.staffId = { in: allowedStaffIds };
    }

    const [total, records] = await Promise.all([
      prisma.staffAttendance.count({ where }),
      prisma.staffAttendance.findMany({
        where,
        include: {
          staff: { select: { id: true, name: true, role: true } },
        },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: records.map(toLegacyStaffAttendance),
      records: records.map(toLegacyStaffAttendance),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, date: dateValue || null } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
}

// POST /api/staff-attendance (Mark multiple or single)
router.post('/', auth, saveAttendance);

// Legacy alias used by older frontend helpers
router.post('/mark', auth, saveAttendance);

// GET /api/staff-attendance/:date
router.get('/', auth, getAttendanceForDate);
router.get('/:date', auth, getAttendanceForDate);

module.exports = router;
