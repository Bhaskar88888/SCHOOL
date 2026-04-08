const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

// GET /api/audit — SuperAdmin only
router.get('/', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { page, limit, search } = parsePaginationParams(req.query);
    const { module, action } = req.query;

    const where = {};

    if (search) {
      where.OR = [
        { user: { name: { contains: search } } },
        { user: { email: { contains: search } } },
      ];
    }
    
    if (module) where.module = module;
    if (action) where.action = action;

    const skip = (page - 1) * limit;

    const [total, logs] = await Promise.all([
      prisma.auditLog.count({ where }),
      prisma.auditLog.findMany({
        where,
        include: {
          user: { select: { name: true, email: true, role: true } },
        },
        orderBy: { timestamp: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: logs,
      logs,
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, search, module, action } },
    });
  } catch (err) {
    console.error('Audit Log Fetch Error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/logs', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { page, limit, search } = parsePaginationParams(req.query);
    const { module, action } = req.query;

    const where = {};

    if (search) {
      where.OR = [
        { user: { name: { contains: search } } },
        { user: { email: { contains: search } } },
      ];
    }

    if (module) where.module = module;
    if (action) where.action = action;

    const skip = (page - 1) * limit;

    const [total, logs] = await Promise.all([
      prisma.auditLog.count({ where }),
      prisma.auditLog.findMany({
        where,
        include: {
          user: { select: { name: true, email: true, role: true } },
        },
        orderBy: { timestamp: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: logs,
      logs,
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, search, module, action } },
    });
  } catch (err) {
    console.error('Audit Log Fetch Error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
