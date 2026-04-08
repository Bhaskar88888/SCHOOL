const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const smsService = require('../services/smsService');
const { withLegacyId, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyLeave(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    staffId: record.staff ? toLegacyUser(record.staff) : record.staffId,
    reviewedBy: record.reviewedBy ? toLegacyUser(record.reviewedBy) : record.reviewedById,
  });
}

function buildLeaveListResponse(data, page, limit, total, extraQuery = {}) {
  return {
    data,
    leaves: data,
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

// POST /api/leave/request (Any Staff)
router.post('/request', auth, async (req, res) => {
  try {
    const { type, fromDate, toDate, reason } = req.body;
    const leave = await prisma.leave.create({
      data: {
        staffId: req.user.id,
        type,
        fromDate: new Date(fromDate),
        toDate: new Date(toDate),
        reason,
      },
    });
    res.status(201).json(toLegacyLeave(leave));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/', auth, async (req, res) => {
  try {
    const { type, fromDate, toDate, reason } = req.body;
    const leave = await prisma.leave.create({
      data: {
        staffId: req.user.id,
        type,
        fromDate: new Date(fromDate),
        toDate: new Date(toDate),
        reason,
      },
    });
    res.status(201).json(toLegacyLeave(leave));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/leave/my (Staff only)
router.get('/my', auth, async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { staffId: req.user.id };

    const [total, leaves] = await Promise.all([
      prisma.leave.count({ where }),
      prisma.leave.findMany({
        where,
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildLeaveListResponse(leaves.map(toLegacyLeave), page, limit, total, { sort: '-createdAt' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/leave/balance (Current user's configured leave balances)
router.get('/balance', auth, async (req, res) => {
  try {
    const user = await prisma.user.findUnique({
      where: { id: req.user.id },
      select: { casualLeaveBalance: true, earnedLeaveBalance: true, sickLeaveBalance: true },
    });
    if (!user) {
      return res.status(404).json({ msg: 'User not found' });
    }

    res.json({
      casual: user.casualLeaveBalance ?? 0,
      earned: user.earnedLeaveBalance ?? 0,
      sick: user.sickLeaveBalance ?? 0,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/leave
router.get('/', auth, async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const isReviewer = ['hr', 'superadmin'].includes(req.user.role);
    const where = isReviewer ? {} : { staffId: req.user.id };

    const [total, leaves] = await Promise.all([
      prisma.leave.count({ where }),
      prisma.leave.findMany({
        where,
        include: { staff: { select: { id: true, name: true, role: true, phone: true } } },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildLeaveListResponse(leaves.map(toLegacyLeave), page, limit, total, { sort: '-createdAt' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/leave/:id/approve (HR only)
router.put('/:id/approve', auth, roleCheck('hr', 'superadmin'), async (req, res) => {
  try {
    const { status, reviewNote } = req.body;

    // Fetch existing leave to get dates + previous status
    const existingLeave = await prisma.leave.findUnique({
      where: { id: req.params.id },
      include: { staff: { select: { id: true, phone: true, casualLeaveBalance: true, earnedLeaveBalance: true, sickLeaveBalance: true } } },
    });

    if (!existingLeave) return res.status(404).json({ msg: 'Leave not found' });

    const leave = await prisma.leave.update({
      where: { id: req.params.id },
      data: {
        status,
        reviewNote,
        reviewedById: req.user.id,
      },
      include: { staff: { select: { id: true, phone: true } } },
    });

    // Deduct leave balance only when moving to 'approved' for the first time
    if (status === 'approved' && existingLeave.status !== 'approved') {
      const msPerDay = 1000 * 60 * 60 * 24;
      const days = Math.round((new Date(existingLeave.toDate) - new Date(existingLeave.fromDate)) / msPerDay) + 1;
      const type = existingLeave.type?.toLowerCase();

      const balanceField =
        type === 'casual' ? 'casualLeaveBalance' :
        type === 'earned' ? 'earnedLeaveBalance' :
        type === 'sick'   ? 'sickLeaveBalance'   : null;

      if (balanceField) {
        const current = existingLeave.staff[balanceField] ?? 0;
        const newBalance = Math.max(0, current - days);
        await prisma.user.update({
          where: { id: existingLeave.staffId },
          data: { [balanceField]: newBalance },
        });
      }
    }

    if (leave?.staff?.phone) {
      await smsService.send({
        to: leave.staff.phone,
        message: `Your leave request from ${new Date(existingLeave.fromDate).toDateString()} to ${new Date(existingLeave.toDate).toDateString()} has been ${status}. - HR`,
      });
    }

    res.json(toLegacyLeave(leave));
  } catch (err) {
    console.error('Leave approve error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.put('/:id/review', auth, roleCheck('hr', 'superadmin'), async (req, res) => {
  try {
    const { status, reviewNote } = req.body;

    const existingLeave = await prisma.leave.findUnique({
      where: { id: req.params.id },
      include: { staff: { select: { id: true, phone: true, casualLeaveBalance: true, earnedLeaveBalance: true, sickLeaveBalance: true } } },
    });

    if (!existingLeave) return res.status(404).json({ msg: 'Leave not found' });

    const leave = await prisma.leave.update({
      where: { id: req.params.id },
      data: {
        status,
        reviewNote,
        reviewedById: req.user.id,
      },
      include: { staff: { select: { id: true, phone: true } } },
    });

    if (status === 'approved' && existingLeave.status !== 'approved') {
      const msPerDay = 1000 * 60 * 60 * 24;
      const days = Math.round((new Date(existingLeave.toDate) - new Date(existingLeave.fromDate)) / msPerDay) + 1;
      const type = existingLeave.type?.toLowerCase();

      const balanceField =
        type === 'casual' ? 'casualLeaveBalance' :
        type === 'earned' ? 'earnedLeaveBalance' :
        type === 'sick'   ? 'sickLeaveBalance'   : null;

      if (balanceField) {
        const current = existingLeave.staff[balanceField] ?? 0;
        const newBalance = Math.max(0, current - days);
        await prisma.user.update({
          where: { id: existingLeave.staffId },
          data: { [balanceField]: newBalance },
        });
      }
    }

    if (leave?.staff?.phone) {
      await smsService.send({
        to: leave.staff.phone,
        message: `Your leave request from ${new Date(existingLeave.fromDate).toDateString()} to ${new Date(existingLeave.toDate).toDateString()} has been ${status}. - HR`,
      });
    }

    res.json(toLegacyLeave(leave));
  } catch (err) {
    console.error('Leave review error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
