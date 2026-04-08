const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { withLegacyId, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyPayroll(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    staffId: record.staff ? toLegacyUser(record.staff) : record.staffId,
  });
}

function getWorkingDays(year, month) {
  const daysInMonth = new Date(year, month, 0).getDate();
  let workingDays = 0;
  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month - 1, day);
    if (date.getDay() !== 0) {
      workingDays++;
    }
  }
  return workingDays;
}

function buildPayrollListResponse(data, page, limit, total, extraQuery = {}) {
  return {
    data,
    records: data,
    payroll: data,
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

// POST /api/payroll/generate-batch (Admin batch generation)
router.post('/generate-batch', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { monthNumber, year, targetStaffId } = req.body;
    if (!monthNumber || !year) {
      return res.status(400).json({ msg: 'monthNumber and year are required.' });
    }

    const firstDay = new Date(year, monthNumber - 1, 1);
    const lastDay = new Date(year, monthNumber, 0, 23, 59, 59);

    const workingDays = getWorkingDays(year, monthNumber);
    let generated = 0;
    let skipped = 0;

    const userWhere = {
      role: { in: ['teacher', 'staff', 'hr', 'accounts', 'canteen', 'conductor'] },
      isActive: true,
      ...(targetStaffId ? { id: targetStaffId } : {}),
    };

    const staffMembers = await prisma.user.findMany({ where: userWhere });

    if (staffMembers.length === 0) {
      return res.status(400).json({ msg: 'No eligible staff found.' });
    }

    for (const staff of staffMembers) {
      const existing = await prisma.payroll.findUnique({
        where: {
          staffId_month_year: {
            staffId: staff.id,
            month: Number(monthNumber),
            year: Number(year),
          },
        },
      });
      if (existing) {
        skipped++;
        continue;
      }

      const salaryData = await prisma.salaryStructure.findFirst({
        where: {
          staffId: staff.id,
          effectiveFrom: { lte: lastDay },
        },
        orderBy: { effectiveFrom: 'desc' },
      });

      if (!salaryData) {
        skipped++;
        continue;
      }

      const attendanceCount = await prisma.staffAttendance.count({
        where: {
          staffId: staff.id,
          date: { gte: firstDay, lte: lastDay },
          status: { in: ['present', 'late', 'half-day'] },
        },
      });

      let factor = attendanceCount / workingDays;
      if (factor > 1) factor = 1;

      const prBasic = salaryData.basicSalary * factor;
      const prHra = (salaryData.hra || 0) * factor;
      const prDa = (salaryData.da || 0) * factor;
      const prConv = (salaryData.conveyance || 0) * factor;
      const prMed = (salaryData.medicalAllowance || 0) * factor;
      const prSpec = (salaryData.specialAllowance || 0) * factor;
      const prPf = (salaryData.pfDeduction || 0) * factor;
      const prTax = (salaryData.taxDeduction || 0) * factor;
      const prOther = (salaryData.otherDeductions || 0) * factor;

      const totalEarnings = prBasic + prHra + prDa + prConv + prMed + prSpec;
      const totalDeductions = prPf + prTax + prOther;
      const netPay = totalEarnings - totalDeductions;

      await prisma.payroll.create({
        data: {
          staffId: staff.id,
          month: Number(monthNumber),
          year: Number(year),
          basicSalary: prBasic,
          hra: prHra,
          da: prDa,
          conveyance: prConv,
          medicalAllowance: prMed,
          specialAllowance: prSpec,
          totalEarnings,
          pfDeduction: prPf,
          taxDeduction: prTax,
          otherDeductions: prOther,
          totalDeductions,
          netPay,
          isPaid: false,
        },
      });
      generated++;
    }

    res.json({ msg: `Payroll generated: ${generated} created, ${skipped} skipped (already exists or no salary structure).` });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/generate', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const monthNumber = req.body.monthNumber || req.body.month;
    const { year, targetStaffId } = req.body;
    if (!monthNumber || !year) {
      return res.status(400).json({ msg: 'monthNumber and year are required.' });
    }

    const firstDay = new Date(year, monthNumber - 1, 1);
    const lastDay = new Date(year, monthNumber, 0, 23, 59, 59);

    const workingDays = getWorkingDays(year, monthNumber);
    let generated = 0;
    let skipped = 0;

    const userWhere = {
      role: { in: ['teacher', 'staff', 'hr', 'accounts', 'canteen', 'conductor'] },
      isActive: true,
      ...(targetStaffId ? { id: targetStaffId } : {}),
    };

    const staffMembers = await prisma.user.findMany({ where: userWhere });

    if (staffMembers.length === 0) {
      return res.status(400).json({ msg: 'No eligible staff found.' });
    }

    for (const staff of staffMembers) {
      const existing = await prisma.payroll.findUnique({
        where: {
          staffId_month_year: {
            staffId: staff.id,
            month: Number(monthNumber),
            year: Number(year),
          },
        },
      });
      if (existing) {
        skipped++;
        continue;
      }

      const salaryData = await prisma.salaryStructure.findFirst({
        where: {
          staffId: staff.id,
          effectiveFrom: { lte: lastDay },
        },
        orderBy: { effectiveFrom: 'desc' },
      });

      if (!salaryData) {
        skipped++;
        continue;
      }

      const attendanceCount = await prisma.staffAttendance.count({
        where: {
          staffId: staff.id,
          date: { gte: firstDay, lte: lastDay },
          status: { in: ['present', 'late', 'half-day'] },
        },
      });

      let factor = attendanceCount / workingDays;
      if (factor > 1) factor = 1;

      const prBasic = salaryData.basicSalary * factor;
      const prHra = (salaryData.hra || 0) * factor;
      const prDa = (salaryData.da || 0) * factor;
      const prConv = (salaryData.conveyance || 0) * factor;
      const prMed = (salaryData.medicalAllowance || 0) * factor;
      const prSpec = (salaryData.specialAllowance || 0) * factor;
      const prPf = (salaryData.pfDeduction || 0) * factor;
      const prTax = (salaryData.taxDeduction || 0) * factor;
      const prOther = (salaryData.otherDeductions || 0) * factor;

      const totalEarnings = prBasic + prHra + prDa + prConv + prMed + prSpec;
      const totalDeductions = prPf + prTax + prOther;
      const netPay = totalEarnings - totalDeductions;

      await prisma.payroll.create({
        data: {
          staffId: staff.id,
          month: Number(monthNumber),
          year: Number(year),
          basicSalary: prBasic,
          hra: prHra,
          da: prDa,
          conveyance: prConv,
          medicalAllowance: prMed,
          specialAllowance: prSpec,
          totalEarnings,
          pfDeduction: prPf,
          taxDeduction: prTax,
          otherDeductions: prOther,
          totalDeductions,
          netPay,
          isPaid: false,
        },
      });
      generated++;
    }

    res.json({ msg: `Payroll generated: ${generated} created, ${skipped} skipped (already exists or no salary structure).` });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/payroll/slip/:id
router.get('/slip/:id', auth, async (req, res) => {
  try {
    const payroll = await prisma.payroll.findUnique({
      where: { id: req.params.id },
      include: { staff: { select: { id: true, name: true, employeeId: true, department: true, designation: true, role: true } } },
    });
    if (!payroll) return res.status(404).json({ msg: 'Payslip not found' });
    if (req.user.role !== 'superadmin' && req.user.role !== 'accounts' && req.user.role !== 'hr' && req.user.id !== payroll.staffId) {
      return res.status(403).json({ msg: 'Access Denied' });
    }
    res.json(toLegacyPayroll(payroll));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/payroll/:staffId
router.get('/:staffId', auth, async (req, res, next) => {
  try {
    if (req.params.staffId === 'structures') {
      return next();
    }
    if (req.user.role !== 'superadmin' && req.user.role !== 'accounts' && req.user.role !== 'hr' && req.user.id !== req.params.staffId) {
      return res.status(403).json({ msg: 'Access Denied' });
    }
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;
    const where = { staffId: req.params.staffId };

    const [total, payrolls] = await Promise.all([
      prisma.payroll.count({ where }),
      prisma.payroll.findMany({
        where,
        include: { staff: { select: { id: true, name: true, employeeId: true, department: true, designation: true } } },
        orderBy: [{ year: 'desc' }, { month: 'desc' }],
        skip,
        take: limit,
      }),
    ]);

    res.json(buildPayrollListResponse(payrolls.map(toLegacyPayroll), page, limit, total, { sort: '-year,-month' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/payroll/:payrollId/pay
router.put('/:payrollId/pay', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const payroll = await prisma.payroll.update({
      where: { id: req.params.payrollId },
      data: { isPaid: true, paidOn: new Date() },
    });
    res.json(toLegacyPayroll(payroll));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/payroll/batch-pay
router.put('/batch-pay/:year/:monthNumber', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    await prisma.payroll.updateMany({
      where: { year: Number(req.params.year), month: Number(req.params.monthNumber), isPaid: false },
      data: { isPaid: true, paidOn: new Date() },
    });
    res.json({ msg: `All pending payrolls for ${req.params.year}-${req.params.monthNumber} marked as paid` });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/payroll
router.get('/', auth, roleCheck('superadmin', 'accounts', 'hr'), async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, payrolls] = await Promise.all([
      prisma.payroll.count(),
      prisma.payroll.findMany({
        include: { staff: { select: { id: true, name: true, role: true } } },
        orderBy: [{ year: 'desc' }, { month: 'desc' }],
        skip,
        take: limit,
      }),
    ]);

    res.json(buildPayrollListResponse(payrolls.map(toLegacyPayroll), page, limit, total, { sort: '-year,-month' }));
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

    res.json({
      data: structures.map((structure) => withLegacyId({
        ...structure,
        staffId: structure.staff ? toLegacyUser(structure.staff) : structure.staffId,
      })),
      structures: structures.map((structure) => withLegacyId({
        ...structure,
        staffId: structure.staff ? toLegacyUser(structure.staff) : structure.staffId,
      })),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-effectiveFrom' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/structures', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { staffId, effectiveFrom } = req.body;
    if (!staffId) {
      return res.status(400).json({ msg: 'staffId is required.' });
    }

    const staff = await prisma.user.findUnique({
      where: { id: staffId },
      select: { id: true },
    });

    if (!staff) {
      return res.status(400).json({ msg: 'Invalid staffId.' });
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
        effectiveFrom: effectiveFrom ? new Date(effectiveFrom) : new Date(),
      },
      include: { staff: { select: { id: true, name: true, role: true } } },
    });

    res.status(201).json(withLegacyId({
      ...structure,
      staffId: structure.staff ? toLegacyUser(structure.staff) : structure.staffId,
    }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
