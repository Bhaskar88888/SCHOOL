const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');

// POST /api/tally/export-fees
router.post('/export-fees', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { startDate, endDate, format = 'xml' } = req.body;

    const payments = await prisma.feePayment.findMany({
      where: {
        paymentDate: { gte: new Date(startDate), lte: new Date(endDate) },
      },
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
        collectedBy: { select: { id: true, name: true } },
      },
    });

    if (payments.length === 0) {
      return res.status(400).json({ msg: 'No fee payments found in selected date range' });
    }

    let exportData;
    let filename;
    let contentType;

    if (format === 'xml') {
      exportData = generateTallyXML(payments, 'Fees');
      filename = `Tally_Fees_${startDate}_to_${endDate}.xml`;
      contentType = 'application/xml';
    } else if (format === 'json') {
      exportData = generateTallyJSON(payments, 'Fees');
      filename = `Tally_Fees_${startDate}_to_${endDate}.json`;
      contentType = 'application/json';
    } else {
      exportData = generateTallyCSV(payments, 'Fees');
      filename = `Tally_Fees_${startDate}_to_${endDate}.csv`;
      contentType = 'text/csv';
    }

    res.setHeader('Content-Type', contentType);
    res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);

    if (format === 'json') {
      res.send(JSON.stringify(exportData, null, 2));
    } else {
      res.send(exportData);
    }
  } catch (err) {
    console.error('Tally export error:', err);
    res.status(500).json({ msg: 'Tally export failed', error: err.message });
  }
});

// POST /api/tally/export-payroll
router.post('/export-payroll', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { month, year, format = 'xml' } = req.body;

    const payrolls = await prisma.payroll.findMany({
      where: { month: Number(month), year: Number(year) },
      include: { staff: { select: { id: true, name: true, employeeId: true, department: true, designation: true } } },
    });

    if (payrolls.length === 0) {
      return res.status(400).json({ msg: 'No payroll records found' });
    }

    let exportData;
    let filename;
    let contentType;

    if (format === 'xml') {
      exportData = generateTallyXML(payrolls, 'Payroll');
      filename = `Tally_Payroll_${year}_${month}.xml`;
      contentType = 'application/xml';
    } else if (format === 'json') {
      exportData = generateTallyJSON(payrolls, 'Payroll');
      filename = `Tally_Payroll_${year}_${month}.json`;
      contentType = 'application/json';
    } else {
      exportData = generateTallyCSV(payrolls, 'Payroll');
      filename = `Tally_Payroll_${year}_${month}.csv`;
      contentType = 'text/csv';
    }

    res.setHeader('Content-Type', contentType);
    res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);

    if (format === 'json') {
      res.send(JSON.stringify(exportData, null, 2));
    } else {
      res.send(exportData);
    }
  } catch (err) {
    console.error('Payroll export error:', err);
    res.status(500).json({ msg: 'Payroll export failed', error: err.message });
  }
});

// GET /api/tally/vouchers
router.get('/vouchers', auth, async (req, res) => {
  try {
    const { startDate, endDate } = req.query;

    const where = {};
    if (startDate || endDate) {
      where.paymentDate = {};
      if (startDate) where.paymentDate.gte = new Date(startDate);
      if (endDate) where.paymentDate.lte = new Date(endDate);
    }

    const payments = await prisma.feePayment.findMany({
      where,
      select: {
        receiptNo: true,
        amountPaid: true,
        paymentMode: true,
        paymentDate: true,
        feeType: true,
        student: { select: { id: true, name: true, admissionNo: true } },
      },
    });

    const vouchers = payments.map(p => ({
      voucherNo: p.receiptNo,
      date: p.paymentDate,
      partyName: p.student?.name,
      amount: p.amountPaid,
      mode: p.paymentMode,
      feeType: p.feeType,
    }));

    res.json({
      totalVouchers: vouchers.length,
      totalAmount: vouchers.reduce((sum, v) => sum + v.amount, 0),
      vouchers,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to fetch vouchers', error: err.message });
  }
});

function generateTallyXML(data, type) {
  let xml = `<?xml version="1.0" encoding="UTF-8"?>\n`;
  xml += `<ENVELOPE>\n`;
  xml += `  <HEADER>\n`;
  xml += `    <TALLYREQUEST>Import Data</TALLYREQUEST>\n`;
  xml += `  </HEADER>\n`;
  xml += `  <BODY>\n`;
  xml += `    <IMPORTDATA>\n`;
  xml += `      <REQUESTDESC>\n`;
  xml += `        <REPORTNAME>Vouchers</REPORTNAME>\n`;
  xml += `      </REQUESTDESC>\n`;
  xml += `      <REQUESTDATA>\n`;

  data.forEach((item) => {
    xml += `        <TALLYMESSAGE xmlns:UDF="TallyUDF">\n`;
    xml += `          <VOUCHER VCHTYPE="${type === 'Fees' ? 'Receipt' : 'Payment'}" ACTION="Create">\n`;

    if (type === 'Fees') {
      xml += `            <DATE>${formatTallyDate(item.paymentDate)}</DATE>\n`;
      xml += `            <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>\n`;
      xml += `            <VOUCHERNUMBER>${item.receiptNo}</VOUCHERNUMBER>\n`;
      xml += `            <PARTYLEDGERNAME>${item.student?.name || 'Student'}</PARTYLEDGERNAME>\n`;
      xml += `            <AMOUNT>${item.amountPaid}</AMOUNT>\n`;
      xml += `            <NARRATION>${item.feeType} - ${item.student?.name}</NARRATION>\n`;
    } else {
      xml += `            <DATE>${formatTallyDate(item.month + '/01/' + item.year)}</DATE>\n`;
      xml += `            <VOUCHERTYPENAME>Payment</VOUCHERTYPENAME>\n`;
      xml += `            <VOUCHERNUMBER>SAL-${item.year}-${String(item.month).padStart(2, '0')}</VOUCHERNUMBER>\n`;
      xml += `            <PARTYLEDGERNAME>${item.staff?.name || 'Staff'}</PARTYLEDGERNAME>\n`;
      xml += `            <AMOUNT>-${item.basicSalary + item.hra + item.da - item.pfDeduction - item.taxDeduction}</AMOUNT>\n`;
      xml += `            <NARRATION>Salary for ${item.month}/${item.year}</NARRATION>\n`;
    }

    xml += `          </VOUCHER>\n`;
    xml += `        </TALLYMESSAGE>\n`;
  });

  xml += `      </REQUESTDATA>\n`;
  xml += `    </IMPORTDATA>\n`;
  xml += `  </BODY>\n`;
  xml += `</ENVELOPE>\n`;

  return xml;
}

function generateTallyJSON(data, type) {
  return {
    envelope: {
      header: {
        tallyRequest: 'Import Data',
      },
      body: {
        importData: {
          requestDesc: {
            reportName: 'Vouchers',
          },
          requestData: data.map(item => ({
            tallyMessage: {
              voucher: {
                vchType: type === 'Fees' ? 'Receipt' : 'Payment',
                action: 'Create',
                date: type === 'Fees' ? formatTallyDate(item.paymentDate) : formatTallyDate(item.month + '/01/' + item.year),
                voucherTypeName: type === 'Fees' ? 'Receipt' : 'Payment',
                voucherNumber: type === 'Fees' ? item.receiptNo : `SAL-${item.year}-${String(item.month).padStart(2, '0')}`,
                partyLedgerName: type === 'Fees' ? item.student?.name : item.staff?.name,
                amount: type === 'Fees' ? item.amountPaid : (item.basicSalary + item.hra + item.da - item.pfDeduction - item.taxDeduction),
                narration: type === 'Fees' ? `${item.feeType} - ${item.student?.name}` : `Salary for ${item.month}/${item.year}`,
              },
            },
          })),
        },
      },
    },
  };
}

function generateTallyCSV(data, type) {
  let csv = 'Date,Voucher No,Party Name,Amount,Mode,Fee Type,Narration\n';

  data.forEach(item => {
    const date = type === 'Fees' ? formatDate(item.paymentDate) : `${item.month}/01/${item.year}`;
    const partyName = type === 'Fees' ? item.student?.name : item.staff?.name;
    const amount = type === 'Fees' ? item.amountPaid : (item.basicSalary + item.hra + item.da - item.pfDeduction - item.taxDeduction);
    const narration = type === 'Fees' ? `${item.feeType}` : `Salary for ${item.month}/${item.year}`;

    csv += `${date},${item.receiptNo || `SAL-${item.year}-${item.month}`},"${partyName}",${amount},${item.paymentMode || 'Cash'},${item.feeType || 'Salary'},"${narration}"\n`;
  });

  return csv;
}

function formatTallyDate(date) {
  if (!date) return new Date().toISOString().split('T')[0].split('-').reverse().join('-');
  const d = new Date(date);
  return d.toISOString().split('T')[0].split('-').reverse().join('-');
}

function formatDate(date) {
  if (!date) return new Date().toLocaleDateString();
  return new Date(date).toLocaleDateString();
}

module.exports = router;
