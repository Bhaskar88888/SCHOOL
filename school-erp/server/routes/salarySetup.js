const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { withLegacyId, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacySalaryStructure(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    staffId: record.staff ? toLegacyUser(record.staff) : record.staffId,
  });
}

function buildSalaryStructureListResponse(data, page, limit, total, extraQuery = {}) {
  return {
    data,
    structures: data,
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

// POST /api/salary-setup
router.post('/', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { staffId, effectiveFrom } = req.body;
    if (!staffId || !effectiveFrom) {
      return res.status(400).json({ msg: 'staffId and effectiveFrom are required.' });
    }

    const existing = await prisma.salaryStructure.findUnique({
      where: {
        staffId_effectiveFrom: {
          staffId,
          effectiveFrom: new Date(effectiveFrom),
        },
      },
    });
    if (existing) {
      return res.status(400).json({ msg: 'A salary structure already exists for this employee for this effective date.' });
    }

    const structure = await prisma.salaryStructure.create({
      data: {
        staffId,
        basicSalary: Number(req.body.basicSalary || 0),
        hra: Number(req.body.hra || 0),
        conveyance: Number(req.body.conveyance || 0),
        da: Number(req.body.da || 0),
        medicalAllowance: Number(req.body.medicalAllowance || 0),
        specialAllowance: Number(req.body.specialAllowance || 0),
        pfDeduction: Number(req.body.pfDeduction || 0),
        taxDeduction: Number(req.body.taxDeduction || 0),
        otherDeductions: Number(req.body.otherDeductions || 0),
        effectiveFrom: new Date(effectiveFrom),
      },
      include: { staff: { select: { id: true, name: true, role: true } } },
    });
    res.status(201).json(toLegacySalaryStructure(structure));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/salary-setup
router.get('/', auth, roleCheck('superadmin', 'accounts', 'hr'), async (req, res) => {
  try {
    const { staffId } = req.query;
    const where = staffId ? { staffId } : {};
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, structures] = await Promise.all([
      prisma.salaryStructure.count({ where }),
      prisma.salaryStructure.findMany({
        where,
        include: { staff: { select: { id: true, name: true, role: true } } },
        orderBy: { effectiveFrom: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildSalaryStructureListResponse(structures.map(toLegacySalaryStructure), page, limit, total, { sort: '-effectiveFrom' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/structures', auth, roleCheck('superadmin', 'accounts', 'hr'), async (req, res) => {
  try {
    const { staffId } = req.query;
    const where = staffId ? { staffId } : {};
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, structures] = await Promise.all([
      prisma.salaryStructure.count({ where }),
      prisma.salaryStructure.findMany({
        where,
        include: { staff: { select: { id: true, name: true, role: true } } },
        orderBy: { effectiveFrom: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildSalaryStructureListResponse(structures.map(toLegacySalaryStructure), page, limit, total, { sort: '-effectiveFrom' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/salary-setup/:staffId
router.get('/:staffId', auth, roleCheck('superadmin', 'accounts', 'hr'), async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { staffId: req.params.staffId };

    const [total, structures] = await Promise.all([
      prisma.salaryStructure.count({ where }),
      prisma.salaryStructure.findMany({
        where,
        include: { staff: { select: { id: true, name: true, role: true } } },
        orderBy: { effectiveFrom: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildSalaryStructureListResponse(structures.map(toLegacySalaryStructure), page, limit, total, { sort: '-effectiveFrom' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/salary-setup/:id
router.put('/:id', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const structure = await prisma.salaryStructure.update({
      where: { id: req.params.id },
      data: req.body,
      include: { staff: { select: { id: true, name: true, role: true } } },
    });
    res.json(toLegacySalaryStructure(structure));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
