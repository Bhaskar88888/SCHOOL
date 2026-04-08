const express = require('express');
const router = express.Router();
const multer = require('multer');
const XLSX = require('xlsx');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { generateTemporaryPassword, generateReceiptNumber } = require('../utils/security');

const IMPORTS_DIR = path.join(__dirname, '..', 'uploads', 'imports');
const MIN_PASSWORD_LENGTH = parseInt(process.env.MIN_PASSWORD_LENGTH || '8', 10);

function ensureImportsDir() {
  if (!fs.existsSync(IMPORTS_DIR)) {
    fs.mkdirSync(IMPORTS_DIR, { recursive: true });
  }
}

function safeImportFilename(originalname) {
  const ext = path.extname(originalname || '').toLowerCase();
  return `${Date.now()}-${crypto.randomBytes(6).toString('hex')}${ext}`;
}

function validateImportFileContent(filePath, originalName) {
  const ext = path.extname(originalName || filePath).toLowerCase();
  const buffer = fs.readFileSync(filePath);

  if (ext === '.xlsx') {
    const zipSignature = buffer.subarray(0, 4).toString('hex');
    if (zipSignature !== '504b0304') {
      throw new Error('Uploaded .xlsx file content is invalid.');
    }
    return;
  }

  if (ext === '.xls') {
    const oleSignature = buffer.subarray(0, 8).toString('hex');
    if (oleSignature !== 'd0cf11e0a1b11ae1') {
      throw new Error('Uploaded .xls file content is invalid.');
    }
    return;
  }

  if (ext === '.csv') {
    const head = buffer.subarray(0, Math.min(buffer.length, 512)).toString('utf8');
    if (head.includes('\u0000')) {
      throw new Error('Uploaded .csv file content is invalid.');
    }
    return;
  }

  throw new Error('Only Excel (.xls, .xlsx) and CSV files are allowed');
}

function resolveImportFilePath(filepath) {
  const candidate = path.basename(String(filepath || ''));
  if (!candidate) {
    throw new Error('File path required');
  }

  const resolved = path.resolve(IMPORTS_DIR, candidate);
  const normalizedRoot = path.resolve(IMPORTS_DIR) + path.sep;
  if (!resolved.startsWith(normalizedRoot) && resolved !== path.resolve(IMPORTS_DIR)) {
    throw new Error('Invalid import file path');
  }

  if (!fs.existsSync(resolved)) {
    throw new Error('Import file not found');
  }

  return resolved;
}

function buildImportPassword(defaultPassword) {
  if (defaultPassword) {
    if (String(defaultPassword).length < MIN_PASSWORD_LENGTH) {
      throw new Error(`Password must be at least ${MIN_PASSWORD_LENGTH} characters long.`);
    }
    return { password: String(defaultPassword), passwordChangeRequired: false, generated: false };
  }

  return {
    password: generateTemporaryPassword(),
    passwordChangeRequired: true,
    generated: true,
  };
}

const upload = multer({
  storage: multer.diskStorage({
    destination: (_req, _file, cb) => {
      ensureImportsDir();
      cb(null, IMPORTS_DIR);
    },
    filename: (_req, file, cb) => {
      cb(null, safeImportFilename(file.originalname));
    },
  }),
  fileFilter: (_req, file, cb) => {
    const allowedTypes = ['.xls', '.xlsx', '.csv'];
    const extname = allowedTypes.some(type => file.originalname.toLowerCase().endsWith(type));
    if (extname) {
      cb(null, true);
    } else {
      cb(new Error('Only Excel (.xls, .xlsx) and CSV files are allowed'));
    }
  },
  limits: {
    fileSize: 10 * 1024 * 1024,
  },
});

router.post('/upload', auth, roleCheck('superadmin'), upload.single('file'), async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({ msg: 'No file uploaded' });
    }

    validateImportFileContent(req.file.path, req.file.originalname);

    const workbook = XLSX.readFile(req.file.path);
    const sheetName = workbook.SheetNames[0];
    const worksheet = workbook.Sheets[sheetName];
    const data = XLSX.utils.sheet_to_json(worksheet);

    const importData = {
      filename: req.file.filename,
      filepath: req.file.filename,
      recordCount: data.length,
      columns: data.length > 0 ? Object.keys(data[0]) : [],
      preview: data.slice(0, 5),
      uploadedAt: new Date(),
    };

    res.json({
      msg: 'File uploaded successfully',
      importData,
    });
  } catch (err) {
    console.error('Upload error:', err);
    if (req.file?.path && fs.existsSync(req.file.path)) {
      fs.unlink(req.file.path, () => {});
    }
    res.status(500).json({ msg: 'File upload failed' });
  }
});

router.post('/students', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { filepath, defaultPassword, academicYear } = req.body;

    if (!filepath) {
      return res.status(400).json({ msg: 'File path required' });
    }

    const resolvedPath = resolveImportFilePath(filepath);
    const workbook = XLSX.readFile(resolvedPath);
    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
    const data = XLSX.utils.sheet_to_json(worksheet);

    const results = {
      success: [],
      failed: [],
      total: data.length,
    };

    for (let i = 0; i < data.length; i++) {
      const row = data[i];
      try {
        const studentData = {
          name: row['Student Name'] || row['Name'] || row['name'],
          admissionNo: row['Admission No'] || row['Admission Number'] || row['admission_no'] || `ADM${Date.now()}${i}`,
          classId: row['Class'] || row['Class ID'] || row['class_id'],
          section: row['Section'] || row['section'] || 'A',
          rollNumber: row['Roll No'] || row['Roll Number'] || row['roll_no'],
          parentPhone: row['Parent Phone'] || row['Father Phone'] || row['parent_phone'],
          parentEmail: row['Parent Email'] || row['parent_email'],
          dob: parseDate(row['DOB'] || row['Date of Birth'] || row['dob']),
          gender: row['Gender'] || row['gender'] || 'male',
          phone: row['Phone'] || row['Student Phone'] || `9999999${String(i).padStart(3, '0')}`,
          email: row['Email'] || row['student_email'] || `${row['Admission No'] || `student${i}`}@school.com`,
          password: defaultPassword,
          academicYear: academicYear || new Date().getFullYear().toString(),
          aadhaar: row['Aadhaar'] || row['aadhaar'],
          bloodGroup: row['Blood Group'] || row['blood_group'],
          fatherName: row['Father Name'] || row['father_name'],
          motherName: row['Mother Name'] || row['mother_name'],
          address: row['Address'] || row['address'],
        };

        if (!studentData.name || !studentData.parentPhone || !studentData.dob) {
          results.failed.push({
            row: i + 1,
            data: row,
            error: 'Missing required fields (Name, Parent Phone, DOB)',
          });
          continue;
        }

        let classObj = await prisma.class.findFirst({
          where: { name: String(studentData.classId || '').trim() },
        });

        if (!classObj) {
          classObj = await prisma.class.create({
            data: {
              name: String(studentData.classId || '').trim(),
              section: studentData.section || 'A',
              sections: [studentData.section || 'A'],
              capacity: 60,
            },
          });
        }

        const passwordConfig = buildImportPassword(defaultPassword);
        const salt = await bcrypt.genSalt(10);
        const hashedPassword = await bcrypt.hash(passwordConfig.password, salt);

        const { user, student } = await prisma.$transaction(async (tx) => {
          const createdUser = await tx.user.create({
            data: {
              name: studentData.name,
              email: studentData.email,
              password: hashedPassword,
              role: 'student',
              phone: studentData.phone,
              passwordChangeRequired: passwordConfig.passwordChangeRequired,
            },
          });

          const createdStudent = await tx.student.create({
            data: {
              userId: createdUser.id,
              name: studentData.name,
              admissionNo: studentData.admissionNo,
              classId: classObj.id,
              section: studentData.section,
              rollNumber: studentData.rollNumber,
              parentPhone: studentData.parentPhone,
              parentEmail: studentData.parentEmail,
              dob: studentData.dob,
              gender: studentData.gender,
              academicYear: studentData.academicYear,
              aadhaar: studentData.aadhaar,
              bloodGroup: studentData.bloodGroup,
              fatherName: studentData.fatherName,
              motherName: studentData.motherName,
              address: studentData.address,
            },
          });

          return { user: createdUser, student: createdStudent };
        });

        results.success.push({
          row: i + 1,
          admissionNo: student.admissionNo,
          name: student.name,
          generatedCredentials: (passwordConfig.generated && process.env.ALLOW_IMPORT_PASSWORD_EXPORT === 'true')
            ? { email: user.email, password: passwordConfig.password }
            : (passwordConfig.generated ? { email: user.email } : null),
        });
      } catch (err) {
        results.failed.push({
          row: i + 1,
          data: row,
          error: err.message,
        });
      }
    }

    fs.unlink(resolvedPath, (err) => {
      if (err) console.error('File deletion error:', err);
    });

    res.json({
      msg: `Imported ${results.success.length} students. ${results.failed.length} failed.`,
      results,
    });
  } catch (err) {
    console.error('Student import error:', err);
    res.status(500).json({ msg: 'Student import failed' });
  }
});

router.post('/staff', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { filepath, defaultPassword } = req.body;

    if (!filepath) {
      return res.status(400).json({ msg: 'File path required' });
    }

    const resolvedPath = resolveImportFilePath(filepath);
    const workbook = XLSX.readFile(resolvedPath);
    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
    const data = XLSX.utils.sheet_to_json(worksheet);

    const results = {
      success: [],
      failed: [],
      total: data.length,
    };

    for (let i = 0; i < data.length; i++) {
      const row = data[i];
      try {
        const staffData = {
          name: row['Name'] || row['Staff Name'] || row['name'],
          email: row['Email'] || row['email'],
          role: row['Role'] || row['role'] || 'staff',
          phone: row['Phone'] || row['phone'],
          employeeId: row['Employee ID'] || row['employee_id'] || `EMP${Date.now()}${i}`,
          department: row['Department'] || row['department'],
          designation: row['Designation'] || row['designation'],
          doj: parseDate(row['DOJ'] || row['Joining Date'] || row['doj']),
          qualification: row['Qualification'] || row['qualification'],
          experience: row['Experience'] || row['experience'],
          salary: row['Salary'] || row['salary'],
        };

        if (!staffData.name || !staffData.email) {
          results.failed.push({
            row: i + 1,
            data: row,
            error: 'Missing required fields (Name, Email)',
          });
          continue;
        }

        const existing = await prisma.user.findUnique({ where: { email: staffData.email } });
        if (existing) {
          results.failed.push({
            row: i + 1,
            data: row,
            error: 'Email already exists',
          });
          continue;
        }

        const passwordConfig = buildImportPassword(defaultPassword);
        const salt = await bcrypt.genSalt(10);
        const hashedPassword = await bcrypt.hash(passwordConfig.password, salt);

        const user = await prisma.user.create({
          data: {
            name: staffData.name,
            email: staffData.email,
            role: staffData.role,
            phone: staffData.phone,
            employeeId: staffData.employeeId,
            department: staffData.department,
            designation: staffData.designation,
            joiningDate: staffData.doj,
            highestQualification: staffData.qualification,
            experienceYears: staffData.experience ? Number(staffData.experience) : null,
            password: hashedPassword,
            passwordChangeRequired: passwordConfig.passwordChangeRequired,
          },
        });

        results.success.push({
          row: i + 1,
          employeeId: user.employeeId,
          name: user.name,
          role: user.role,
          generatedCredentials: passwordConfig.generated ? {
            email: user.email,
            password: passwordConfig.password,
          } : null,
        });
      } catch (err) {
        results.failed.push({
          row: i + 1,
          data: row,
          error: err.message,
        });
      }
    }

    fs.unlink(resolvedPath, (err) => {
      if (err) console.error('File deletion error:', err);
    });

    res.json({
      msg: `Imported ${results.success.length} staff. ${results.failed.length} failed.`,
      results,
    });
  } catch (err) {
    console.error('Staff import error:', err);
    res.status(500).json({ msg: 'Staff import failed' });
  }
});

router.post('/fees', auth, roleCheck('superadmin', 'accounts'), async (req, res) => {
  try {
    const { filepath } = req.body;

    if (!filepath) {
      return res.status(400).json({ msg: 'File path required' });
    }

    const resolvedPath = resolveImportFilePath(filepath);
    const workbook = XLSX.readFile(resolvedPath);
    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
    const data = XLSX.utils.sheet_to_json(worksheet);

    const results = {
      success: [],
      failed: [],
      total: data.length,
      totalAmount: 0,
    };

    for (let i = 0; i < data.length; i++) {
      const row = data[i];
      try {
        const student = await prisma.student.findUnique({
          where: { admissionNo: row['Admission No'] || row['admission_no'] },
        });

        if (!student) {
          results.failed.push({
            row: i + 1,
            data: row,
            error: 'Student not found',
          });
          continue;
        }

        const paymentData = {
          studentId: student.id,
          amountPaid: Number(row['Amount'] || row['Fee Amount'] || row['amount'] || 0),
          paymentMode: row['Payment Mode'] || row['payment_mode'] || 'cash',
          paymentDate: parseDate(row['Date'] || row['Payment Date'] || row['date']) || new Date(),
          receiptNo: row['Receipt No'] || row['receipt_no'] || generateReceiptNumber(),
          feeType: row['Fee Type'] || row['fee_type'] || 'Tuition Fee',
          academicYear: row['Academic Year'] || row['academic_year'] || new Date().getFullYear().toString(),
          remarks: row['Remarks'] || row['remarks'],
          collectedById: req.user.id,
        };

        const payment = await prisma.feePayment.create({ data: paymentData });

        results.success.push({
          row: i + 1,
          receiptNo: payment.receiptNo,
          studentName: student.name,
          amount: payment.amountPaid,
        });

        results.totalAmount += payment.amountPaid;
      } catch (err) {
        results.failed.push({
          row: i + 1,
          data: row,
          error: err.message,
        });
      }
    }

    fs.unlink(resolvedPath, (err) => {
      if (err) console.error('File deletion error:', err);
    });

    res.json({
      msg: `Imported ${results.success.length} fee payments. Total: ₹${results.totalAmount}`,
      results,
    });
  } catch (err) {
    console.error('Fee import error:', err);
    res.status(500).json({ msg: 'Fee import failed' });
  }
});

router.get('/templates/:type', auth, async (req, res) => {
  try {
    const { type } = req.params;

    let templateData = [];
    let filename = '';

    if (type === 'students') {
      filename = 'Student_Import_Template.xlsx';
      templateData = [{
        'Student Name': 'Rajesh Kumar',
        'Admission No': 'ADM2024001',
        'Class': '10',
        'Section': 'A',
        'Roll No': '1',
        'Parent Phone': '9876543210',
        'Parent Email': 'parent@email.com',
        'DOB': '2010-05-15',
        'Gender': 'male',
        'Phone': '9876543211',
        'Email': 'rajesh@school.com',
        'Aadhaar': '123456789012',
        'Blood Group': 'B+',
        'Father Name': 'Ramesh Kumar',
        'Mother Name': 'Sunita Devi',
        'Address': 'MG Road, Mumbai',
      }];
    } else if (type === 'staff') {
      filename = 'Staff_Import_Template.xlsx';
      templateData = [{
        'Name': 'John Teacher',
        'Email': 'john@school.com',
        'Role': 'teacher',
        'Phone': '9876543212',
        'Employee ID': 'EMP001',
        'Department': 'Science',
        'Designation': 'Senior Teacher',
        'DOJ': '2020-06-01',
        'Qualification': 'M.Sc, B.Ed',
        'Experience': '5',
        'Salary': '45000',
      }];
    } else if (type === 'fees') {
      filename = 'Fee_Import_Template.xlsx';
      templateData = [{
        'Admission No': 'ADM2024001',
        'Amount': '5000',
        'Payment Mode': 'cash',
        'Date': '2024-04-01',
        'Receipt No': 'REC001',
        'Fee Type': 'Tuition Fee',
        'Academic Year': '2024-2025',
        'Remarks': 'Annual fee',
      }];
    } else {
      return res.status(400).json({ msg: 'Invalid template type' });
    }

    const worksheet = XLSX.utils.json_to_sheet(templateData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Template');

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);

    const buffer = XLSX.write(workbook, { type: 'buffer', bookType: 'xlsx' });
    res.send(buffer);
  } catch (err) {
    console.error('Template error:', err);
    res.status(500).json({ msg: 'Template generation failed' });
  }
});

function parseDate(dateValue) {
  if (!dateValue) return null;

  if (dateValue instanceof Date) {
    return dateValue;
  }

  if (typeof dateValue === 'number') {
    return new Date(Math.round((dateValue - 25569) * 86400 * 1000));
  }

  if (typeof dateValue === 'string') {
    const date = new Date(dateValue);
    if (!isNaN(date.getTime())) {
      return date;
    }

    const parts = dateValue.split('/');
    if (parts.length === 3) {
      return new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
    }
  }

  return null;
}

module.exports = router;
