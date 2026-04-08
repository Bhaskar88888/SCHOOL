const express = require('express');
const router = express.Router();
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const prisma = require('../config/prisma');

let jsPDF;
try {
  const jspdfModule = require('jspdf');
  jsPDF = jspdfModule.jsPDF || jspdfModule;
} catch (e) {
  jsPDF = null;
}

function notAvailable(res) {
  res.status(503).json({ msg: 'PDF generation not available. Run: npm install jspdf' });
}

router.post('/payslip', auth, async (req, res) => {
  if (!jsPDF) return notAvailable(res);
  try {
    const { payrollId } = req.body;
    if (!payrollId) {
      return res.status(400).json({ msg: 'payrollId is required' });
    }

    const payroll = await prisma.payroll.findUnique({
      where: { id: payrollId },
      include: {
        staff: {
          select: {
            id: true,
            name: true,
            email: true,
            department: true,
            designation: true,
            basicPay: true,
            hra: true,
            da: true,
            conveyance: true,
            pfDeduction: true,
            esiDeduction: true,
          },
        },
      },
    });
    if (!payroll) {
      return res.status(404).json({ msg: 'Payroll record not found' });
    }

    const allowedRoles = new Set(['superadmin', 'accounts', 'hr']);
    const isOwner = payroll.staffId && String(payroll.staffId) === String(req.user.id);
    if (!allowedRoles.has(req.user.role) && !isOwner) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const doc = new jsPDF('p', 'mm', 'a4');
    const staff = payroll.staff || {};

    doc.setFontSize(22);
    doc.text('EduGlass School ERP — Official Payslip', 105, 20, { align: 'center' });

    doc.setFontSize(12);
    doc.text(`Employee : ${staff.name || 'N/A'}`, 20, 38);
    doc.text(`Department: ${staff.department || 'N/A'}`, 20, 46);
    doc.text(`Designation: ${staff.designation || 'N/A'}`, 20, 54);
    doc.text(`Month : ${payroll?.month || 'N/A'} ${payroll?.year || ''}`, 20, 62);
    doc.text(`Generated : ${new Date().toLocaleDateString('en-IN')}`, 20, 70);

    doc.setLineWidth(0.5);
    doc.line(20, 75, 190, 75);

    doc.setFontSize(14);
    doc.text('Earnings', 20, 85);
    doc.text('Deductions', 120, 85);

    doc.setFontSize(11);
    doc.text(`Basic Pay    : Rs. ${staff.basicPay || payroll?.basicSalary || 0}`, 20, 95);
    doc.text(`HRA          : Rs. ${staff.hra || payroll?.hra || 0}`, 20, 103);
    doc.text(`DA           : Rs. ${staff.da || payroll?.da || 0}`, 20, 111);
    doc.text(`Conveyance   : Rs. ${staff.conveyance || payroll?.conveyance || 0}`, 20, 119);

    doc.text(`PF           : Rs. ${staff.pfDeduction || payroll?.pfDeduction || 0}`, 120, 95);
    doc.text(`ESI          : Rs. ${staff.esiDeduction || payroll?.esiDeduction || 0}`, 120, 103);
    doc.text(`LOP          : Rs. ${payroll?.lossOfPay || 0}`, 120, 111);

    doc.setFontSize(14);
    const resolvedNetPay = payroll?.netPay ?? 'N/A';
    doc.text(`Net Pay      : Rs. ${resolvedNetPay}`, 20, 140);

    doc.setFontSize(9);
    doc.text('This is a computer-generated document and does not require a signature.', 105, 280, { align: 'center' });

    const buffer = Buffer.from(doc.output('arraybuffer'));
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="payslip_${payrollId || 'latest'}.pdf"`);
    res.send(buffer);
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'PDF generation failed.' });
  }
});

router.post('/transfer-certificate', auth, roleCheck('superadmin', 'teacher', 'accounts', 'hr'), async (req, res) => {
  if (!jsPDF) return notAvailable(res);
  try {
    const { studentId } = req.body;
    if (!studentId) {
      return res.status(400).json({ msg: 'studentId is required' });
    }

    const student = await prisma.student.findUnique({
      where: { id: studentId },
      include: { class: { select: { id: true, name: true, section: true } } },
    });
    if (!student) {
      return res.status(404).json({ msg: 'Student record not found' });
    }

    const doc = new jsPDF('p', 'mm', 'a4');
    doc.setFontSize(24);
    doc.text('TRANSFER CERTIFICATE', 105, 30, { align: 'center' });

    doc.setFontSize(14);
    doc.text(`School Name: ${process.env.SCHOOL_NAME || 'EduGlass School'}`, 105, 50, { align: 'center' });

    doc.setFontSize(12);
    doc.text('This is to certify that the following student has been a bonafide student of this school', 20, 68);
    doc.text('and has paid all dues. Transfer Certificate is hereby issued.', 20, 76);

    doc.setLineWidth(0.3);
    doc.line(20, 82, 190, 82);

    doc.text(`Student Name : ${student?.name || 'N/A'}`, 20, 92);
    doc.text(`Admission No : ${student?.admissionNo || 'N/A'}`, 20, 100);
    doc.text(`Class        : ${student?.class?.name || 'N/A'} ${student?.class?.section || ''}`, 20, 108);
    doc.text(`Date of Birth: ${student?.dob ? new Date(student.dob).toLocaleDateString('en-IN') : 'N/A'}`, 20, 116);
    doc.text(`Date of Issue: ${new Date().toLocaleDateString('en-IN')}`, 20, 124);

    doc.setFontSize(9);
    doc.text('Principal Signature: ____________________', 20, 270);
    doc.text('School Seal', 150, 270);

    const buffer = Buffer.from(doc.output('arraybuffer'));
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="TC_${studentId || 'student'}.pdf"`);
    res.send(buffer);
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'PDF generation failed.' });
  }
});

module.exports = router;
