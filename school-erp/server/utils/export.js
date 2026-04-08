const jspdfModule = require('jspdf');
const jsPDF = jspdfModule.jsPDF || jspdfModule;
const { autoTable } = require('jspdf-autotable');
const crypto = require('crypto');

// Fix: Add counter to prevent filename collisions
let exportCounter = 0;
function generateUniqueFilename(type, extension) {
  const timestamp = Date.now();
  const counter = (++exportCounter % 10000).toString().padStart(4, '0');
  return `${type}_${timestamp}_${counter}.${extension}`;
}

function createDoc(options = {}) {
  const orientation = options.orientation === 'portrait' ? 'p' : 'l';
  return new jsPDF({ orientation, unit: 'mm', format: 'a4' });
}

function bufferFromDoc(doc) {
  return Buffer.from(doc.output('arraybuffer'));
}

function sendPdf(res, doc, filename) {
  res.setHeader('Content-Type', 'application/pdf');
  res.setHeader('Content-Disposition', `attachment; filename=${filename}`);
  res.send(bufferFromDoc(doc));
}

function addFooter(doc) {
  const pageCount = doc.getNumberOfPages();
  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();

  for (let page = 1; page <= pageCount; page += 1) {
    doc.setPage(page);
    doc.setFontSize(8);
    doc.text(`Page ${page} of ${pageCount}`, pageWidth / 2, pageHeight - 8, { align: 'center' });
  }
}

function addTitleBlock(doc, title, subtitle, schoolName) {
  const pageWidth = doc.internal.pageSize.getWidth();
  doc.setFontSize(18);
  doc.text(title, pageWidth / 2, 16, { align: 'center' });
  doc.setFontSize(11);
  doc.text(subtitle, pageWidth / 2, 24, { align: 'center' });
  doc.setFontSize(10);
  doc.text(schoolName || 'School ERP System', pageWidth / 2, 30, { align: 'center' });
}

function tableConfig(doc, options = {}) {
  return {
    startY: 36,
    theme: 'grid',
    styles: { fontSize: 9, cellPadding: 2.5 },
    headStyles: { fillColor: [79, 70, 229], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [245, 247, 250] },
    margin: { left: 10, right: 10, top: 36, bottom: 14 },
    ...options,
  };
}

function normalizeCellValue(value) {
  if (value === null || value === undefined || value === '') return 'N/A';
  if (value instanceof Date) return value.toLocaleDateString();
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}

function exportToPDF(res, type, data, options = {}) {
  try {
    const doc = createDoc(options);
    addTitleBlock(
      doc,
      options.title || `${type.toUpperCase()} REPORT`,
      options.subtitle || `Generated on ${new Date().toLocaleString()}`,
      options.schoolName || process.env.SCHOOL_NAME
    );

    const config = tableConfig(doc, options.tableOptions);

    switch (type) {
      case 'students': {
        autoTable(doc, {
          ...config,
          head: [['Admission No', 'Name', 'Class', 'Section', 'Gender', 'Parent Phone', 'Email']],
          body: data.map((student) => ([
            normalizeCellValue(student.admissionNo),
            normalizeCellValue(student.name),
            normalizeCellValue(student.class?.name || student.classId?.name || student.className),
            normalizeCellValue(student.section),
            normalizeCellValue(student.gender),
            normalizeCellValue(student.parentPhone),
            normalizeCellValue(student.user?.email || student.userId?.email || student.email),
          ])),
        });
        break;
      }
      case 'attendance': {
        autoTable(doc, {
          ...config,
          head: [['Student Name', 'Admission No', 'Date', 'Status', 'Subject']],
          body: data.map((attendance) => ([
            normalizeCellValue(attendance.student?.name || attendance.studentId?.name),
            normalizeCellValue(attendance.student?.admissionNo || attendance.studentId?.admissionNo),
            normalizeCellValue(attendance.date),
            normalizeCellValue(attendance.status),
            normalizeCellValue(attendance.subject),
          ])),
        });
        break;
      }
      case 'fees': {
        autoTable(doc, {
          ...config,
          head: [['Receipt No', 'Student', 'Fee Type', 'Amount', 'Mode', 'Date']],
          body: data.map((payment) => ([
            normalizeCellValue(payment.receiptNo),
            normalizeCellValue(payment.student?.name || payment.studentId?.name),
            normalizeCellValue(payment.feeType),
            `Rs ${Number(payment.amountPaid || 0).toFixed(2)}`,
            normalizeCellValue(payment.paymentMode),
            normalizeCellValue(payment.paymentDate),
          ])),
        });

        const total = data.reduce((sum, payment) => sum + Number(payment.amountPaid || 0), 0);
        const pageWidth = doc.internal.pageSize.getWidth();
        // Fix: Guard against NaN from undefined finalY
        const finalY = Number.isFinite(doc.lastAutoTable?.finalY) ? doc.lastAutoTable.finalY : 36;
        doc.setFontSize(11);
        doc.text(`Total Amount: Rs ${total.toFixed(2)}`, pageWidth - 10, finalY + 8, { align: 'right' });
        break;
      }
      case 'exams': {
        autoTable(doc, {
          ...config,
          head: [['Exam Name', 'Type', 'Subject', 'Date', 'Total Marks']],
          body: data.map((exam) => ([
            normalizeCellValue(exam.name),
            normalizeCellValue(exam.examType),
            normalizeCellValue(exam.subject),
            normalizeCellValue(exam.date),
            normalizeCellValue(exam.totalMarks),
          ])),
        });
        break;
      }
      case 'exam-results': {
        autoTable(doc, {
          ...config,
          head: [['Student', 'Exam', 'Marks', 'Total', 'Grade', 'Remarks']],
          body: data.map((result) => ([
            normalizeCellValue(result.student?.name || result.studentId?.name),
            normalizeCellValue(result.exam?.name || result.examId?.name),
            normalizeCellValue(result.marksObtained),
            normalizeCellValue(result.totalMarks),
            normalizeCellValue(result.grade),
            normalizeCellValue(result.remarks),
          ])),
        });
        break;
      }
      case 'library': {
        autoTable(doc, {
          ...config,
          head: [['Title', 'Author', 'ISBN', 'Category', 'Available', 'Total']],
          body: data.map((book) => ([
            normalizeCellValue(book.title),
            normalizeCellValue(book.author),
            normalizeCellValue(book.isbn),
            normalizeCellValue(book.category),
            normalizeCellValue(book.availableCopies),
            normalizeCellValue(book.totalCopies),
          ])),
        });
        break;
      }
      case 'staff': {
        autoTable(doc, {
          ...config,
          head: [['Employee ID', 'Name', 'Email', 'Phone', 'Role', 'Department']],
          body: data.map((staff) => ([
            normalizeCellValue(staff.employeeId),
            normalizeCellValue(staff.name),
            normalizeCellValue(staff.email),
            normalizeCellValue(staff.phone),
            normalizeCellValue(staff.role),
            normalizeCellValue(staff.department),
          ])),
        });
        break;
      }
      default: {
        if (data.length > 0) {
          const headers = Object.keys(data[0]);
          autoTable(doc, {
            ...config,
            head: [headers],
            body: data.map((item) => headers.map((header) => normalizeCellValue(item[header]))),
          });
        }
      }
    }

    addFooter(doc);
    sendPdf(res, doc, generateUniqueFilename(type, 'pdf'));
  } catch (err) {
    console.error('PDF export error:', err);
    res.status(500).json({ msg: 'PDF generation failed', error: err.message });
  }
}

function exportToExcel(res, type, data) {
  try {
    if (!data || data.length === 0) {
      const csv = 'Message\r\nNo data available\r\n';
      res.setHeader('Content-Type', 'text/csv');
      res.setHeader('Content-Disposition', `attachment; filename="${generateUniqueFilename(type, 'csv')}"`);
      res.send(csv);
      return;
    }

    const headers = Object.keys(data[0]);
    let csv = headers.join(',') + '\n';

    function sanitizeCsvCell(rawValue) {
      if (rawValue === null || rawValue === undefined) return '';
      let value = rawValue;
      if (typeof value === 'object') {
        value = JSON.stringify(value);
      }
      let str = String(value);
      // Fix: More complete CSV injection prevention
      if (/^[=+\-@|]/.test(str) || /^(DDE|CMD|REGEDIT|SCHTASKS)/i.test(str)) {
        str = `'${str}`;
      }
      if (str.includes('"')) {
        str = str.replace(/"/g, '""');
      }
      if (str.includes(',') || str.includes('\n') || str.includes('\r')) {
        return `"${str}"`;
      }
      return str;
    }

    data.forEach((item) => {
      const row = headers.map((header) => {
        return sanitizeCsvCell(item[header]);
      });
      csv += row.join(',') + '\n';
    });

    // Fix: Use unique filename to prevent collisions
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', `attachment; filename="${generateUniqueFilename(type, 'csv')}"`);
    res.send(csv);
  } catch (err) {
    console.error('Excel export error:', err);
    res.status(500).json({ msg: 'Excel export failed', error: err.message });
  }
}

function exportReportCard(res, student, results, attendanceStats, feePayments) {
  try {
    const doc = createDoc({ orientation: 'portrait' });
    const pageWidth = doc.internal.pageSize.getWidth();

    addTitleBlock(
      doc,
      'SCHOOL REPORT CARD',
      `Academic Year ${student.academicYear || `${new Date().getFullYear()}-${new Date().getFullYear() + 1}`}`,
      process.env.SCHOOL_NAME
    );

    doc.setFontSize(11);
    doc.text(`Student Name: ${student.name}`, 14, 42);
    doc.text(`Admission No: ${student.admissionNo}`, 110, 42);
    doc.text(`Class: ${student.class?.name || student.classId?.name || 'N/A'}`, 14, 50);
    doc.text(`Section: ${student.section || student.class?.section || student.classId?.section || 'N/A'}`, 110, 50);
    doc.text(`Roll No: ${student.rollNumber || 'N/A'}`, 14, 58);
    doc.text(`Date of Birth: ${student.dob ? new Date(student.dob).toLocaleDateString() : 'N/A'}`, 110, 58);
    doc.text(`Parent Contact: ${student.parentPhone || student.parentUser?.phone || student.parentUserId?.phone || 'N/A'}`, 14, 66);
    doc.text(`Attendance: ${attendanceStats.percentage || 0}%`, 110, 66);

    autoTable(doc, {
      startY: 74,
      theme: 'grid',
      styles: { fontSize: 9, cellPadding: 2.5 },
      headStyles: { fillColor: [79, 70, 229], textColor: 255, fontStyle: 'bold' },
      head: [['Exam', 'Subject', 'Marks Obtained', 'Total Marks', 'Grade', 'Remarks']],
      body: results.length
        ? results.map((result) => ([
          normalizeCellValue(result.exam?.name || result.examId?.name),
          normalizeCellValue(result.exam?.subject || result.examId?.subject),
          normalizeCellValue(result.marksObtained),
          normalizeCellValue(result.totalMarks),
          normalizeCellValue(result.grade),
          normalizeCellValue(result.remarks),
        ]))
        : [['No exam results available', '', '', '', '', '']],
      margin: { left: 10, right: 10, bottom: 14 },
    });

    // Fix: Use Number.isFinite to guard against NaN from undefined finalY
    const afterResultsY = (Number.isFinite(doc.lastAutoTable?.finalY) ? doc.lastAutoTable.finalY : 74) + 10;
    doc.setFontSize(12);
    doc.text('Attendance Summary', 14, afterResultsY);
    doc.setFontSize(10);
    doc.text(`Total Days: ${attendanceStats.total || 0}`, 14, afterResultsY + 8);
    doc.text(`Present: ${attendanceStats.present || 0}`, 14, afterResultsY + 14);
    doc.text(`Absent: ${attendanceStats.absent || 0}`, 14, afterResultsY + 20);
    doc.text(`Attendance Percentage: ${attendanceStats.percentage || 0}%`, 14, afterResultsY + 26);

    // Fix: Guard against undefined feePayments
    const payments = feePayments || [];
    const totalPaid = payments.reduce((sum, payment) => sum + Number(payment.amountPaid || 0), 0);
    const feeStartY = afterResultsY + (afterResultsY + 34 > 270 ? 20 : 8);

    doc.setFontSize(12);
    doc.text('Fee Payment Summary', 110, afterResultsY);
    doc.setFontSize(10);
    doc.text(`Total Fees Paid: Rs ${totalPaid.toFixed(2)}`, 110, afterResultsY + 8);
    doc.text(`Number of Payments: ${payments.length}`, 110, afterResultsY + 14);
    doc.text(
      `Last Payment Date: ${payments[0]?.paymentDate ? new Date(payments[0].paymentDate).toLocaleDateString() : 'N/A'}`,
      110,
      afterResultsY + 20
    );

    doc.setFontSize(9);
    doc.text('This is a computer-generated report card.', pageWidth / 2, 286, { align: 'center' });
    doc.text('For any queries, please contact the school office.', pageWidth / 2, 291, { align: 'center' });

    addFooter(doc);
    sendPdf(res, doc, `ReportCard_${student.admissionNo}.pdf`);
  } catch (err) {
    console.error('Report card export error:', err);
    res.status(500).json({ msg: 'Report card generation failed', error: err.message });
  }
}

module.exports = {
  exportToPDF,
  exportToExcel,
  exportReportCard,
};
