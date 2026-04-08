const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { withLegacyId, toLegacyStudent } = require('../utils/prismaCompat');
const { canUserAccessStudent } = require('../utils/accessScope');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyCanteenItem(record) {
  return record ? withLegacyId(record) : null;
}

function toLegacyCanteenSale(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    soldBy: record.soldBy ? withLegacyId(record.soldBy) : record.soldById,
    items: Array.isArray(record.items) ? record.items.map(withLegacyId) : [],
  });
}

// POST /api/canteen/items
router.post('/items', auth, roleCheck('canteen', 'superadmin'), async (req, res) => {
  try {
    const item = await prisma.canteenItem.create({
      data: {
        name: req.body.name,
        price: Number(req.body.price || 0),
        quantityAvailable: Number(req.body.quantityAvailable || 0),
        category: req.body.category || null,
        isAvailable: req.body.isAvailable !== false,
      },
    });
    res.status(201).json(toLegacyCanteenItem(item));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/canteen/items
router.get('/items', auth, async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, items] = await Promise.all([
      prisma.canteenItem.count(),
      prisma.canteenItem.findMany({
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: items.map(toLegacyCanteenItem),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// POST /api/canteen/sell
router.post('/sell', auth, roleCheck('canteen', 'superadmin'), async (req, res) => {
  try {
    const { items, total, soldTo, paymentMode } = req.body;
    if (!Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ msg: 'items are required' });
    }

    const sale = await prisma.$transaction(async (tx) => {
      for (const item of items) {
        if (item.itemId) {
          const qty = Math.max(0, Number(item.quantity || 0));
          const updateResult = await tx.canteenItem.updateMany({
            where: { id: item.itemId, quantityAvailable: { gte: qty } },
            data: { quantityAvailable: { decrement: qty } },
          });
          if (updateResult.count === 0) {
            const err = new Error('INSUFFICIENT_STOCK');
            err.code = 'INSUFFICIENT_STOCK';
            throw err;
          }
        }
      }

      const created = await tx.canteenSale.create({
        data: {
          total: Number(total || 0),
          soldTo: soldTo || null,
          soldById: req.user.id,
          paymentMode: paymentMode || 'Cash',
          items: {
            create: items.map(item => ({
              itemId: item.itemId || null,
              quantity: Number(item.quantity || 0),
              price: item.price !== undefined ? Number(item.price) : null,
            })),
          },
        },
        include: {
          soldBy: { select: { id: true, name: true } },
          items: true,
        },
      });

      return created;
    });

    res.status(201).json(toLegacyCanteenSale(sale));
  } catch (err) {
    if (err?.code === 'INSUFFICIENT_STOCK') {
      return res.status(400).json({ msg: 'Insufficient stock for one or more items.' });
    }
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/canteen/items/:id
router.put('/items/:id', auth, roleCheck('canteen', 'superadmin'), async (req, res) => {
  try {
    const item = await prisma.canteenItem.update({
      where: { id: req.params.id },
      data: req.body,
    });
    res.json(toLegacyCanteenItem(item));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/canteen/items/:id/restock
router.put('/items/:id/restock', auth, roleCheck('canteen', 'superadmin'), async (req, res) => {
  try {
    const { amount } = req.body;
    const item = await prisma.canteenItem.update({
      where: { id: req.params.id },
      data: { quantityAvailable: { increment: Number(amount || 0) } },
    });
    res.json(toLegacyCanteenItem(item));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// DELETE /api/canteen/items/:id
router.delete('/items/:id', auth, roleCheck('canteen', 'superadmin'), async (req, res) => {
  try {
    await prisma.canteenItem.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Item deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/canteen/sales
router.get('/sales', auth, roleCheck('canteen', 'superadmin', 'accounts'), async (req, res) => {
  try {
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = Math.min(100, Math.max(1, parseInt(req.query.limit) || 50));
    const skip = (page - 1) * limit;

    const [total, sales] = await Promise.all([
      prisma.canteenSale.count(),
      prisma.canteenSale.findMany({
        include: { soldBy: { select: { id: true, name: true } }, items: true },
        orderBy: { date: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: sales.map(toLegacyCanteenSale),
      pagination: {
        total,
        totalPages: Math.ceil(total / limit) || 1,
        currentPage: page,
        hasNextPage: page * limit < total,
        hasPrevPage: page > 1,
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/wallet/:studentId', auth, async (req, res) => {
  try {
    const allowedRoles = new Set(['superadmin', 'accounts', 'canteen']);
    if (!allowedRoles.has(req.user.role)) {
      const canAccess = await canUserAccessStudent(req.user, req.params.studentId);
      if (!canAccess) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    const student = await prisma.student.findUnique({
      where: { id: req.params.studentId },
      select: { id: true, name: true, canteenBalance: true, rfidTagHex: true },
    });
    if (!student) return res.status(404).json({ msg: 'Student not found.' });
    res.json({
      name: student.name,
      wallet: { balance: Number(student.canteenBalance || 0), rfidTagHex: student.rfidTagHex || null },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/wallet/topup', auth, roleCheck('superadmin', 'accounts', 'canteen'), async (req, res) => {
  try {
    const { studentId, amount } = req.body;
    if (!studentId || !amount || Number(amount) <= 0) {
      return res.status(400).json({ msg: 'studentId and a positive amount are required.' });
    }
    const student = await prisma.student.update({
      where: { id: studentId },
      data: { canteenBalance: { increment: Number(amount) } },
      select: { id: true, canteenBalance: true, rfidTagHex: true },
    });
    res.json({ msg: `Wallet topped up by ₹${amount}.`, wallet: { balance: Number(student.canteenBalance || 0), rfidTagHex: student.rfidTagHex || null } });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/wallet/assign-rfid', auth, roleCheck('superadmin', 'canteen'), async (req, res) => {
  try {
    const { studentId, rfidTagHex } = req.body;
    if (!studentId || !rfidTagHex) {
      return res.status(400).json({ msg: 'studentId and rfidTagHex are required.' });
    }
    const student = await prisma.student.update({
      where: { id: studentId },
      data: { rfidTagHex },
      select: { id: true, canteenBalance: true, rfidTagHex: true },
    });
    res.json({ msg: 'RFID assigned.', wallet: { balance: Number(student.canteenBalance || 0), rfidTagHex: student.rfidTagHex || null } });
  } catch (err) {
    if (err?.code === 'P2002') {
      return res.status(400).json({ msg: 'RFID tag already assigned to another student.' });
    }
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/rfid-pay', auth, roleCheck('canteen', 'superadmin'), async (req, res) => {
  try {
    const { rfidTagHex, items, totalAmount } = req.body;
    if (!rfidTagHex || !items || !totalAmount) {
      return res.status(400).json({ msg: 'rfidTagHex, items and totalAmount are required.' });
    }
    if (!Array.isArray(items) || Number(totalAmount) <= 0) {
      return res.status(400).json({ msg: 'Invalid items or total amount.' });
    }

    const student = await prisma.student.findUnique({
      where: { rfidTagHex },
      select: { id: true, name: true, canteenBalance: true },
    });
    if (!student) {
      return res.status(404).json({ msg: 'Invalid smart card — wallet not found.' });
    }

    await prisma.$transaction(async (tx) => {
      const balanceUpdate = await tx.student.updateMany({
        where: {
          id: student.id,
          canteenBalance: { gte: Number(totalAmount) },
        },
        data: { canteenBalance: { decrement: Number(totalAmount) } },
      });

      if (balanceUpdate.count === 0) {
        const err = new Error('INSUFFICIENT_BALANCE');
        err.code = 'INSUFFICIENT_BALANCE';
        throw err;
      }

      for (const item of items) {
        if (item.itemId) {
          const qty = Math.max(0, Number(item.quantity || 0));
          const updateResult = await tx.canteenItem.updateMany({
            where: {
              id: item.itemId,
              quantityAvailable: { gte: qty },
            },
            data: { quantityAvailable: { decrement: qty } },
          });
          if (updateResult.count === 0) {
            const err = new Error('INSUFFICIENT_STOCK');
            err.code = 'INSUFFICIENT_STOCK';
            throw err;
          }
        }
      }

      await tx.canteenSale.create({
        data: {
          items: {
            create: items.map(i => ({
              itemId: i.itemId || null,
              quantity: Number(i.quantity || 0),
              price: i.price !== undefined ? Number(i.price) : null,
            })),
          },
          total: Number(totalAmount),
          soldTo: student.id,
          soldById: req.user.id,
          paymentMode: 'rfid-wallet',
        },
      });
    });

    const refreshed = await prisma.student.findUnique({
      where: { id: student.id },
      select: { canteenBalance: true, rfidTagHex: true },
    });

    res.json({
      msg: 'POS Transaction Approved.',
      remainingBalance: Number(refreshed?.canteenBalance || 0),
    });
  } catch (err) {
    if (err?.code === 'INSUFFICIENT_BALANCE') {
      return res.status(400).json({ msg: 'Insufficient wallet balance.' });
    }
    if (err?.code === 'INSUFFICIENT_STOCK') {
      return res.status(400).json({ msg: 'Insufficient stock for one or more items.' });
    }
    console.error(err);
    res.status(500).json({ msg: 'Server Error during RFID payment.' });
  }
});

module.exports = router;
