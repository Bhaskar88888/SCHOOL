const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const smsService = require('../services/smsService');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const jspdfModule = require('jspdf');
const jsPDF = jspdfModule.jsPDF || jspdfModule;
const { canUserAccessStudent } = require('../utils/accessScope');
const { generateReceiptNumber } = require('../utils/security');
const { withLegacyId, toLegacyClass, toLegacyStudent, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyFeeStructure(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    classId: record.class ? toLegacyClass(record.class) : record.classId,
  });
}

function toLegacyFeePayment(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    feeStructureId: record.feeStructure ? toLegacyFeeStructure(record.feeStructure) : record.feeStructureId,
    collectedBy: record.collectedBy ? toLegacyUser(record.collectedBy) : record.collectedById,
  });
}

async function createPaymentWithUniqueReceipt(paymentData) {
  let lastError = null;
  for (let attempt = 0; attempt < 5; attempt += 1) {
    try {
      return await prisma.feePayment.create({
        data: {
          ...paymentData,
          receiptNo: generateReceiptNumber(),
        },
      });
    } catch (err) {
      lastError = err;
      if (err?.code !== 'P2002') {
        throw err;
      }
    }
  }

  throw lastError || new Error('Could not generate a unique receipt number.');
}

// POST /api/fee/structure - Create fee structure
router.post('/structure', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const {
      classId,
      feeType,
      amount,
      academicYear,
      term,
      description,
      dueDate,
      lateFee,
    } = req.body;

    if (!classId || !feeType || !amount) {
      return res.status(400).json({ msg: 'classId, feeType, and amount are required' });
    }

    const fee = await prisma.feeStructure.create({
      data: {
        classId,
        feeType,
        amount: Number(amount),
        academicYear: academicYear || new Date().getFullYear().toString(),
        term: term || 'Annual',
        description: description || '',
        dueDate: dueDate ? new Date(dueDate) : null,
        lateFee: Number(lateFee || 0),
      },
      include: {
        class: { select: { id: true, name: true, section: true } },
      },
    });

    res.status(201).json({ msg: 'Fee structure created', feeStructure: toLegacyFeeStructure(fee) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

router.post('/structures', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const {
      classId,
      feeType,
      amount,
      academicYear,
      term,
      description,
      dueDate,
      lateFee,
    } = req.body;

    if (!classId || !feeType || !amount) {
      return res.status(400).json({ msg: 'classId, feeType, and amount are required' });
    }

    const fee = await prisma.feeStructure.create({
      data: {
        classId,
        feeType,
        amount: Number(amount),
        academicYear: academicYear || new Date().getFullYear().toString(),
        term: term || 'Annual',
        description: description || '',
        dueDate: dueDate ? new Date(dueDate) : null,
        lateFee: Number(lateFee || 0),
      },
      include: {
        class: { select: { id: true, name: true, section: true } },
      },
    });

    res.status(201).json({ msg: 'Fee structure created', feeStructure: toLegacyFeeStructure(fee) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/fee/structures - Get all fee structures
router.get('/structures', auth, async (req, res) => {
  try {
    const { classId, academicYear, feeType } = req.query;

    const where = {};
    if (classId) where.classId = classId;
    if (academicYear) where.academicYear = academicYear;
    if (feeType) where.feeType = feeType;

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, structures] = await Promise.all([
      prisma.feeStructure.count({ where }),
      prisma.feeStructure.findMany({
        where,
        include: {
          class: { select: { id: true, name: true, section: true } },
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: structures.map(toLegacyFeeStructure),
      feeStructures: structures.map(toLegacyFeeStructure),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// PUT /api/fee/structure/:id - Update fee structure
router.put('/structure/:id', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const fee = await prisma.feeStructure.update({
      where: { id: req.params.id },
      data: req.body,
      include: {
        class: { select: { id: true, name: true, section: true } },
      },
    });

    res.json({ msg: 'Fee structure updated', feeStructure: toLegacyFeeStructure(fee) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// DELETE /api/fee/structures/:id - Delete fee structure
router.delete('/structures/:id', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const paymentCount = await prisma.feePayment.count({ where: { feeStructureId: req.params.id } });

    if (paymentCount > 0) {
      return res.status(400).json({
        msg: `Cannot delete. ${paymentCount} payment(s) are linked to this structure.`,
      });
    }

    await prisma.feeStructure.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Fee structure deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// POST /api/fee/collect - Collect fee payment
router.post('/collect', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const {
      studentId,
      feeStructureId,
      amountPaid,
      paymentMode,
      paymentDate,
      remarks,
      discount,
    } = req.body;

    if (!studentId || !amountPaid) {
      return res.status(400).json({ msg: 'studentId and amountPaid are required' });
    }

    const [student, feeStructure] = await Promise.all([
      prisma.student.findUnique({
        where: { id: studentId },
        include: {
          user: { select: { id: true, name: true, phone: true } },
          parentUser: { select: { id: true, name: true, phone: true } },
          class: { select: { id: true, name: true, section: true } },
        },
      }),
      feeStructureId ? prisma.feeStructure.findUnique({ where: { id: feeStructureId } }) : Promise.resolve(null),
    ]);

    const numericAmount = Number(amountPaid);
    const numericDiscount = Number(discount || 0);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      return res.status(400).json({ msg: 'amountPaid must be a positive number' });
    }

    if (!Number.isFinite(numericDiscount) || numericDiscount < 0 || numericDiscount > numericAmount) {
      return res.status(400).json({ msg: 'discount must be between 0 and amountPaid' });
    }

    const finalAmount = numericAmount - numericDiscount;

    const payment = await createPaymentWithUniqueReceipt({
      studentId,
      feeStructureId: feeStructureId || null,
      amountPaid: finalAmount,
      originalAmount: numericAmount,
      discount: numericDiscount,
      paymentMode: paymentMode || 'cash',
      paymentDate: paymentDate ? new Date(paymentDate) : new Date(),
      collectedById: req.user.id,
      remarks: remarks || '',
      feeType: feeStructure?.feeType || 'General',
      academicYear: feeStructure?.academicYear || new Date().getFullYear().toString(),
    });

    if (student?.parentPhone || student?.parentUser?.phone) {
      const parentPhone = student.parentPhone || student.parentUser?.phone;
      await smsService.send({
        to: parentPhone,
        message: `Fee of Rs.${finalAmount} received for ${student.name}. Receipt No: ${payment.receiptNo}. Balance: Rs.${(feeStructure?.amount - finalAmount) || 0}. - ${process.env.SCHOOL_NAME}`,
      }).catch(() => {});
    }

    const populated = await prisma.feePayment.findUnique({
      where: { id: payment.id },
      include: {
        student: {
          include: {
            class: { select: { id: true, name: true, section: true } },
            user: { select: { id: true, name: true } },
            parentUser: { select: { id: true, name: true } },
          },
        },
        collectedBy: { select: { id: true, name: true } },
        feeStructure: true,
      },
    });

    res.status(201).json({ msg: 'Payment collected successfully', payment: toLegacyFeePayment(populated) });
  } catch (err) {
    console.error('Fee collection error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/payments', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const {
      studentId,
      feeStructureId,
      amountPaid,
      paymentMode,
      paymentDate,
      remarks,
      discount,
    } = req.body;

    if (!studentId || !amountPaid) {
      return res.status(400).json({ msg: 'studentId and amountPaid are required' });
    }

    const [student, feeStructure] = await Promise.all([
      prisma.student.findUnique({
        where: { id: studentId },
        include: {
          user: { select: { id: true, name: true, phone: true } },
          parentUser: { select: { id: true, name: true, phone: true } },
          class: { select: { id: true, name: true, section: true } },
        },
      }),
      feeStructureId ? prisma.feeStructure.findUnique({ where: { id: feeStructureId } }) : Promise.resolve(null),
    ]);

    const numericAmount = Number(amountPaid);
    const numericDiscount = Number(discount || 0);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      return res.status(400).json({ msg: 'amountPaid must be a positive number' });
    }

    if (!Number.isFinite(numericDiscount) || numericDiscount < 0 || numericDiscount > numericAmount) {
      return res.status(400).json({ msg: 'discount must be between 0 and amountPaid' });
    }

    const finalAmount = numericAmount - numericDiscount;

    const payment = await createPaymentWithUniqueReceipt({
      studentId,
      feeStructureId: feeStructureId || null,
      amountPaid: finalAmount,
      originalAmount: numericAmount,
      discount: numericDiscount,
      paymentMode: paymentMode || 'cash',
      paymentDate: paymentDate ? new Date(paymentDate) : new Date(),
      collectedById: req.user.id,
      remarks: remarks || '',
      feeType: feeStructure?.feeType || 'General',
      academicYear: feeStructure?.academicYear || new Date().getFullYear().toString(),
    });

    if (student?.parentPhone || student?.parentUser?.phone) {
      const parentPhone = student.parentPhone || student.parentUser?.phone;
      await smsService.send({
        to: parentPhone,
        message: `Fee of Rs.${finalAmount} received for ${student.name}. Receipt No: ${payment.receiptNo}. Balance: Rs.${(feeStructure?.amount - finalAmount) || 0}. - ${process.env.SCHOOL_NAME}`,
      }).catch(() => {});
    }

    const populated = await prisma.feePayment.findUnique({
      where: { id: payment.id },
      include: {
        student: {
          include: {
            class: { select: { id: true, name: true, section: true } },
            user: { select: { id: true, name: true } },
            parentUser: { select: { id: true, name: true } },
          },
        },
        collectedBy: { select: { id: true, name: true } },
        feeStructure: true,
      },
    });

    res.status(201).json({ msg: 'Payment collected successfully', payment: toLegacyFeePayment(populated) });
  } catch (err) {
    console.error('Fee collection error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/fee/payments - Get all payments (with filters)
router.get('/payments', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { studentId, startDate, endDate, feeType, classId } = req.query;

    const where = {};
    if (studentId) where.studentId = studentId;
    if (startDate || endDate) {
      where.paymentDate = {};
      if (startDate) where.paymentDate.gte = new Date(startDate);
      if (endDate) where.paymentDate.lte = new Date(endDate);
    }
    if (feeType) where.feeType = feeType;

    if (classId) {
      const studentsInClass = await prisma.student.findMany({
        where: { classId },
        select: { id: true },
      });
      where.studentId = { in: studentsInClass.map(item => item.id) };
    }

    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = Math.min(100, Math.max(1, parseInt(req.query.limit) || 50));
    const skip = (page - 1) * limit;

    const [total, payments] = await Promise.all([
      prisma.feePayment.count({ where }),
      prisma.feePayment.findMany({
        where,
        include: {
          student: { include: { class: { select: { id: true, name: true, section: true } } } },
          collectedBy: { select: { id: true, name: true } },
          feeStructure: true,
        },
        orderBy: { paymentDate: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: payments.map(toLegacyFeePayment),
      payments: payments.map(toLegacyFeePayment),
      pagination: {
        total,
        totalPages: Math.ceil(total / limit) || 1,
        currentPage: page,
        hasNextPage: page * limit < total,
        hasPrevPage: page > 1,
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/fee/my - Get own payments (for students/parents)
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
      studentIds = children.map(child => child.id);
    } else {
      return res.json([]);
    }

    if (studentIds.length === 0) return res.json([]);

    const payments = await prisma.feePayment.findMany({
      where: { studentId: { in: studentIds } },
      include: {
        feeStructure: true,
        student: { include: { class: { select: { id: true, name: true, section: true } } } },
      },
      orderBy: { paymentDate: 'desc' },
    });

    res.json(payments.map(toLegacyFeePayment));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/fee/student/:id - Get payment history for a student
router.get('/student/:id', auth, async (req, res) => {
  try {
    const hasAccess = await canUserAccessStudent(req.user, req.params.id);
    if (!hasAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const [payments, student] = await Promise.all([
      prisma.feePayment.findMany({
        where: { studentId: req.params.id },
        include: {
          feeStructure: true,
          collectedBy: { select: { id: true, name: true } },
        },
        orderBy: { paymentDate: 'desc' },
      }),
      prisma.student.findUnique({
        where: { id: req.params.id },
        include: { class: { select: { id: true, name: true, section: true } } },
      }),
    ]);

    if (!student) return res.status(404).json({ msg: 'Student not found' });

    const feeStructures = await prisma.feeStructure.findMany({
      where: { classId: student.classId },
    });

    const totalPaid = payments.reduce((sum, p) => sum + p.amountPaid, 0);
    const totalDue = feeStructures.reduce((sum, f) => sum + f.amount, 0);

    res.json({
      student: toLegacyStudent(student),
      payments: payments.map(toLegacyFeePayment),
      summary: {
        totalPaid,
        totalDue,
        pending: totalDue - totalPaid,
        paymentCount: payments.length,
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/fee/receipt/:id - Generate receipt PDF
router.get('/receipt/:id', auth, async (req, res) => {
  try {
    const payment = await prisma.feePayment.findUnique({
      where: { id: req.params.id },
      include: {
        student: {
          include: {
            class: { select: { id: true, name: true, section: true } },
          },
        },
        collectedBy: { select: { id: true, name: true } },
        feeStructure: true,
      },
    });

    if (!payment) {
      return res.status(404).json({ msg: 'Payment not found' });
    }

    const hasAccess = await canUserAccessStudent(req.user, payment.studentId);
    if (!hasAccess) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const doc = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();

    doc.setFontSize(20);
    doc.text(process.env.SCHOOL_NAME || 'School ERP', pageWidth / 2, 18, { align: 'center' });
    doc.setFontSize(12);
    doc.text('Fee Payment Receipt', pageWidth / 2, 26, { align: 'center' });

    doc.setFontSize(10);
    doc.text(`Receipt No: ${payment.receiptNo}`, pageWidth - 16, 36, { align: 'right' });
    doc.text(`Date: ${new Date(payment.paymentDate).toLocaleDateString()}`, pageWidth - 16, 42, { align: 'right' });

    doc.setFontSize(12);
    doc.text('Received From', 16, 54);
    doc.setLineWidth(0.4);
    doc.line(16, 56, 70, 56);

    doc.setFontSize(10);
    doc.text(payment.student?.name || 'N/A', 16, 64);
    if (payment.student?.structuredAddress) {
      const addr = payment.student.structuredAddress;
      const addressParts = [addr.line1, addr.city, addr.state, addr.pincode].filter(Boolean);
      if (addressParts.length) {
        doc.text(addressParts.join(', '), 16, 70, { maxWidth: 178 });
      }
    }

    doc.setFontSize(12);
    doc.text('Payment Details', 16, 86);
    doc.line(16, 88, 72, 88);

    doc.setFontSize(10);
    const detailLines = [
      `Fee Type: ${payment.feeType || payment.feeStructure?.feeType || 'General'}`,
      `Academic Year: ${payment.academicYear}`,
      `Amount Paid: Rs. ${Number(payment.amountPaid || 0).toFixed(2)}`,
      `Payment Mode: ${String(payment.paymentMode || '').toUpperCase()}`,
      `Collected By: ${payment.collectedBy?.name || 'Admin'}`,
    ];

    if (payment.discount > 0) {
      detailLines.splice(2, 0, `Original Amount: Rs. ${Number(payment.originalAmount || 0).toFixed(2)}`);
      detailLines.splice(3, 0, `Discount: Rs. ${Number(payment.discount || 0).toFixed(2)}`);
    }
    if (payment.remarks) {
      detailLines.push(`Remarks: ${payment.remarks}`);
    }

    detailLines.forEach((line, index) => {
      doc.text(line, 16, 96 + (index * 8), { maxWidth: 178 });
    });

    doc.setFontSize(8);
    doc.text('This is a computer-generated receipt and does not require a signature.', pageWidth / 2, 282, { align: 'center' });
    doc.text('Thank you for your payment!', pageWidth / 2, 287, { align: 'center' });

    const buffer = Buffer.from(doc.output('arraybuffer'));
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `inline; filename=Receipt_${payment.receiptNo}.pdf`);
    res.send(buffer);
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// DELETE /api/fee/payment/:id - Void/delete payment
router.delete('/payment/:id', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const payment = await prisma.feePayment.findUnique({ where: { id: req.params.id } });

    if (!payment) {
      return res.status(404).json({ msg: 'Payment not found' });
    }

    await prisma.feePayment.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Payment voided successfully', receiptNo: payment.receiptNo });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/fee/defaulters - Get students who haven't paid
router.get('/defaulters', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { classId, academicYear, feeType } = req.query;
    const daysThreshold = 30;
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - daysThreshold);

    const students = await prisma.student.findMany({
      where: classId ? { classId } : {},
      include: {
        class: { select: { id: true, name: true, section: true } },
        user: { select: { id: true, name: true, phone: true } },
        parentUser: { select: { id: true, name: true, phone: true } },
      },
    });

    if (students.length === 0) return res.json({ count: 0, defaulters: [] });

    const feeWhere = {};
    if (classId) feeWhere.classId = classId;
    if (academicYear) feeWhere.academicYear = academicYear;
    if (feeType) feeWhere.feeType = feeType;

    const allFeeStructures = await prisma.feeStructure.findMany({ where: feeWhere });

    const classDueMap = {};
    for (const fs of allFeeStructures) {
      const cid = String(fs.classId);
      classDueMap[cid] = (classDueMap[cid] || 0) + fs.amount;
    }

    const studentIds = students.map(s => s.id);
    const recentPayers = await prisma.feePayment.findMany({
      where: {
        studentId: { in: studentIds },
        paymentDate: { gte: cutoffDate },
      },
      select: { studentId: true },
      distinct: ['studentId'],
    });
    const recentPayerSet = new Set(recentPayers.map(item => String(item.studentId)));

    const defaulters = [];
    for (const student of students) {
      const cid = String(student.classId);
      if (!classDueMap[cid]) continue;
      if (recentPayerSet.has(String(student.id))) continue;

      defaulters.push({
        student: {
          _id: student.id,
          name: student.name,
          admissionNo: student.admissionNo,
          class: student.class ? toLegacyClass(student.class) : student.classId,
          parentPhone: student.parentPhone,
          parent: student.parentUser ? toLegacyUser(student.parentUser) : student.parentUserId,
        },
        totalDue: classDueMap[cid],
        lastPaymentDate: null,
      });
    }

    res.json({ count: defaulters.length, defaulters });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

// GET /api/fee/collection-report - Fee collection report
router.get('/collection-report', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { startDate, endDate, classId } = req.query;

    const where = {};
    if (startDate || endDate) {
      where.paymentDate = {};
      if (startDate) where.paymentDate.gte = new Date(startDate);
      if (endDate) where.paymentDate.lte = new Date(endDate);
    }

    if (classId) {
      const studentsInClass = await prisma.student.findMany({
        where: { classId },
        select: { id: true },
      });
      where.studentId = { in: studentsInClass.map(item => item.id) };
    }

    const payments = await prisma.feePayment.findMany({
      where,
      include: {
        student: { select: { id: true, classId: true } },
        feeStructure: { select: { feeType: true } },
      },
    });

    const totalCollected = payments.reduce((sum, p) => sum + p.amountPaid, 0);
    const totalDiscount = payments.reduce((sum, p) => sum + (p.discount || 0), 0);

    const byFeeType = {};
    payments.forEach(p => {
      const type = p.feeType || p.feeStructure?.feeType || 'General';
      if (!byFeeType[type]) {
        byFeeType[type] = { count: 0, amount: 0 };
      }
      byFeeType[type].count += 1;
      byFeeType[type].amount += p.amountPaid;
    });

    const byPaymentMode = {};
    payments.forEach(p => {
      const mode = p.paymentMode;
      if (!byPaymentMode[mode]) {
        byPaymentMode[mode] = { count: 0, amount: 0 };
      }
      byPaymentMode[mode].count += 1;
      byPaymentMode[mode].amount += p.amountPaid;
    });

    res.json({
      period: { startDate, endDate },
      summary: {
        totalCollected,
        totalDiscount,
        totalTransactions: payments.length,
      },
      byFeeType: Object.entries(byFeeType).map(([type, data]) => ({
        type,
        ...data,
      })),
      byPaymentMode: Object.entries(byPaymentMode).map(([mode, data]) => ({
        mode,
        ...data,
      })),
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', error: err.message });
  }
});

module.exports = router;
