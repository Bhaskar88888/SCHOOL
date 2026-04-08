/**
 * Comprehensive Mock Data Generator for School ERP
 * Generates 10,000+ records across all modules
 */

require('dotenv').config();
const prisma = require('./config/prisma');
const connectDB = require('./config/db');

// Import all models
const User = require('./models/User');
const Student = require('./models/Student');
const Class = require('./models/Class');
const Attendance = require('./models/Attendance');
const StaffAttendance = require('./models/StaffAttendance');
const FeeStructure = require('./models/FeeStructure');
const FeePayment = require('./models/FeePayment');
const Exam = require('./models/Exam');
const ExamResult = require('./models/ExamResult');
const Homework = require('./models/Homework');
const Notice = require('./models/Notice');
const Leave = require('./models/Leave');
const Payroll = require('./models/Payroll');
const SalaryStructure = require('./models/SalaryStructure');
const Complaint = require('./models/Complaint');
const Remark = require('./models/Remark');
const Routine = require('./models/Routine');
const LibraryBook = require('./models/LibraryBook');
const LibraryTransaction = require('./models/LibraryTransaction');
const BusRoute = require('./models/BusRoute');
const TransportVehicle = require('./models/TransportVehicle');
const TransportAttendance = require('./models/TransportAttendance');
const { CanteenItem, CanteenSale } = require('./models/Canteen');
const HostelRoomType = require('./models/HostelRoomType');
const HostelRoom = require('./models/HostelRoom');
const HostelAllocation = require('./models/HostelAllocation');
const HostelFeeStructure = require('./models/HostelFeeStructure');
const Notification = require('./models/Notification');
const Counter = require('./models/Counter');
const { trainChatbot } = require('./ai/nlpEngine');

// Faker-like data generators
const firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan', 'Ananya', 'Diya', 'Saanvi', 'Aadhya', 'Kavya', 'Anika', 'Meera', 'Riya', 'Pari', 'Tara', 'Rahul', 'Rohit', 'Pooja', 'Neha', 'Amit', 'Sunita', 'Rajesh', 'Kavita', 'Vikram', 'Priya'];
const lastNames = ['Sharma', 'Verma', 'Gupta', 'Singh', 'Kumar', 'Patel', 'Reddy', 'Nair', 'Pillai', 'Chatterjee', 'Mukherjee', 'Banerjee', 'Joshi', 'Rao', 'Menon', 'Iyer', 'Agarwal', 'Bhatia', 'Khanna', 'Mehta'];
const cities = ['Mumbai', 'Delhi', 'Bangalore', 'Kolkata', 'Chennai', 'Hyderabad', 'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow', 'Guwahati', 'Dibrugarh', 'Jorhat', 'Tezpur', 'Nagaon'];
const states = ['Maharashtra', 'Delhi', 'Karnataka', 'West Bengal', 'Tamil Nadu', 'Telangana', 'Maharashtra', 'Gujarat', 'Rajasthan', 'Uttar Pradesh', 'Assam', 'Assam', 'Assam', 'Assam', 'Assam'];
const bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const subjects = ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'English', 'Hindi', 'Assamese', 'History', 'Geography', 'Computer Science', 'Economics', 'Political Science', 'Accountancy', 'Business Studies'];
const examTypes = ['Unit Test 1', 'Unit Test 2', 'Mid-Term', 'Unit Test 3', 'Unit Test 4', 'Final Term'];
const feeTypes = ['Tuition Fee', 'Transport Fee', 'Library Fee', 'Sports Fee', 'Exam Fee', 'Laboratory Fee', 'Computer Fee', 'Annual Day Fee', 'Development Fee', 'Admission Fee'];
const paymentModes = ['cash', 'online', 'cheque'];
const leaveTypes = ['sick', 'casual', 'earned'];
const noticePriorities = ['normal', 'important', 'urgent'];
const complaintTypes = ['teacher_to_parent', 'parent_to_teacher', 'student_to_admin', 'parent_to_admin', 'general'];
const complaintStatuses = ['open', 'in_progress', 'resolved', 'closed'];
const bookTitles = ['The Discovery of India', 'Train to Pakistan', 'The Guide', 'Malgudi Days', 'God of Small Things', 'The White Tiger', 'Indian Stories', 'Gitanjali', 'The Immortals of Meluha', 'Two States', 'Rich Like Us', 'The Inheritance of Loss', 'Midnight\'s Children', 'The Palace of Illusions', 'The Serpent\'s Revenge', 'Animal Farm', 'To Kill a Mockingbird', 'The Great Gatsby', '1984', 'Pride and Prejudice'];
const bookAuthors = ['Jawaharlal Nehru', 'Khushwant Singh', 'R.K. Narayan', 'R.K. Narayan', 'Arundhati Roy', 'Aravind Adiga', 'Various Authors', 'Rabindranath Tagore', 'Amish Tripathi', 'Chetan Bhagat', 'Nayantara Sahgal', 'Kiran Desai', 'Salman Rushdie', 'Chitra Banerjee', 'Sudha Murty', 'George Orwell', 'Harper Lee', 'F. Scott Fitzgerald', 'George Orwell', 'Jane Austen'];
const canteenNames = ['Samosa', 'Kachori', 'Puri', 'Paratha', 'Dosa', 'Idli', 'Vada', 'Pav Bhaji', 'Burger', 'Pizza', 'Sandwich', 'Noodles', 'Fried Rice', 'Chowmein', 'Momos', 'Spring Roll', 'Cutlet', 'Tikki', 'Jalebi', 'Gulab Jamun', 'Ice Cream', 'Cold Drink', 'Tea', 'Coffee', 'Lassi', 'Milkshake'];
const categories = ['General', 'OBC', 'SC', 'ST', 'EWS'];
const religions = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist'];
const genders = ['male', 'female', 'other'];
const occupations = ['Teacher', 'Engineer', 'Doctor', 'Business', 'Farmer', 'Government Service', 'Private Job', 'Self Employed', 'Housewife', 'Retired'];
const hostelRoomNames = ['Single Occupancy', 'Double Occupancy', 'Triple Occupancy', 'Dormitory'];
const vehicleTypes = ['AC Bus', 'Non-AC Bus', 'Van', 'Mini Bus'];
const routes = ['Route A - North', 'Route B - South', 'Route C - East', 'Route D - West', 'Route E - Central', 'Route F - Market', 'Route G - Station', 'Route H - Hospital'];

function randomChoice(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function randomFloat(min, max, decimals = 2) {
  return parseFloat((Math.random() * (max - min) + min).toFixed(decimals));
}

function randomDate(start, end) {
  return new Date(start.getTime() + Math.random() * (end.getTime() - start.getTime()));
}

function generateEmail(name, index) {
  const emailDomains = ['gmail.com', 'yahoo.com', 'rediffmail.com', 'outlook.com', 'hotmail.com'];
  return `${name.toLowerCase().replace(/\s+/g, '.')}${index}@user${index}.${randomChoice(emailDomains)}`;
}

function generatePhone() {
  const prefixes = ['9', '8', '7', '6'];
  return randomChoice(prefixes) + randomInt(100000000, 999999999).toString();
}

function generateAddress() {
  return {
    line1: `${randomInt(1, 999)} ${randomChoice(['Main Road', 'Park Street', 'MG Road', 'Station Road', 'Market Road'])}`,
    line2: `${randomChoice(['Near', 'Opposite', 'Behind', 'Beside'])} ${randomChoice(['Temple', 'School', 'Hospital', 'Market', 'Park', 'Station'])}`,
    city: randomChoice(cities),
    state: randomChoice(states),
    pincode: randomInt(100000, 999999).toString()
  };
}

function hashPassword(password) {
  const bcrypt = require('bcryptjs');
  return bcrypt.hashSync(password, 10);
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function getInsertedCount(error) {
  if (!error) return 0;
  if (Array.isArray(error.insertedDocs)) return error.insertedDocs.length;
  if (typeof error.insertedCount === 'number') return error.insertedCount;
  if (typeof error.result?.insertedCount === 'number') return error.result.insertedCount;
  return 0;
}

function logInsertError(label, error) {
  if (!error) return;
  if (error.code === 11000) {
    console.warn(`   ⚠️ ${label}: duplicate key collisions detected`);
    return;
  }

  console.warn(`   ⚠️ ${label}: ${error.message}`);
}

async function insertManyRobust(Model, docs, {
  chunkSize = 500,
  ordered = false,
  label = Model.modelName,
  fallbackCreate = false,
} = {}) {
  let insertedCount = 0;

  for (let i = 0; i < docs.length; i += chunkSize) {
    const chunk = docs.slice(i, i + chunkSize);

    try {
      const inserted = await Model.insertMany(chunk, { ordered });
      insertedCount += inserted.length;
    } catch (error) {
      insertedCount += getInsertedCount(error);
      logInsertError(label, error);

      if (fallbackCreate) {
        for (const doc of chunk) {
          try {
            await Model.create(doc);
            insertedCount++;
          } catch (innerError) {
            if (innerError.code !== 11000) {
              console.warn(`   ⚠️ ${label} single insert skipped: ${innerError.message}`);
            }
          }
        }
      }
    }

    await sleep(25);
  }

  return insertedCount;
}

// Connect to database
connectDB();

async function generateMockData() {
  console.log('🚀 Starting comprehensive mock data generation...\n');

  const startTime = Date.now();
  const stats = {};

  try {
    // Clear existing data (except superadmin)
    console.log('🗑️  Clearing existing data...');
    await User.deleteMany({ role: { $ne: 'superadmin' } });
    await Student.deleteMany({});
    await Class.deleteMany({});
    await Attendance.deleteMany({});
    await StaffAttendance.deleteMany({});
    await FeeStructure.deleteMany({});
    await FeePayment.deleteMany({});
    await Exam.deleteMany({});
    await ExamResult.deleteMany({});
    await Homework.deleteMany({});
    await Notice.deleteMany({});
    await Leave.deleteMany({});
    await Payroll.deleteMany({});
    await SalaryStructure.deleteMany({});
    await Complaint.deleteMany({});
    await Remark.deleteMany({});
    await Routine.deleteMany({});
    await LibraryBook.deleteMany({});
    await LibraryTransaction.deleteMany({});
    await BusRoute.deleteMany({});
    await TransportVehicle.deleteMany({});
    await TransportAttendance.deleteMany({});
    await CanteenItem.deleteMany({});
    await CanteenSale.deleteMany({});
    await HostelRoomType.deleteMany({});
    await HostelRoom.deleteMany({});
    await HostelAllocation.deleteMany({});
    await HostelFeeStructure.deleteMany({});
    await Notification.deleteMany({});
    console.log('✅ Cleared existing data\n');

    // ========================================
    // 1. GENERATE USERS (10,000 across all roles)
    // ========================================
    console.log('👥 Generating 10,000 Users...');
    const roles = ['teacher', 'student', 'parent', 'staff', 'hr', 'accounts', 'canteen', 'conductor', 'driver'];
    const roleDistribution = {
      teacher: 2000,
      student: 1000,
      parent: 2000,
      staff: 2000,
      hr: 500,
      accounts: 500,
      canteen: 500,
      conductor: 500,
      driver: 1000
    };

    const allUsers = [];
    const usersByRole = {};
    let globalUserIndex = 0;

    for (const [role, count] of Object.entries(roleDistribution)) {
      usersByRole[role] = [];
      for (let i = 0; i < count; i++) {
        const firstName = randomChoice(firstNames);
        const lastName = randomChoice(lastNames);
        const name = `${firstName} ${lastName}`;
        const user = {
          name,
          email: generateEmail(name, globalUserIndex),
          password: hashPassword('password123'),
          role,
          phone: generatePhone(),
          employeeId: role !== 'parent' ? `EMP${role.toUpperCase().slice(0, 3)}${String(i).padStart(5, '0')}` : undefined,
          department: randomChoice(['Academic', 'Administrative', 'Transport', 'Hostel', 'Finance', '']),
          designation: role === 'teacher' ? randomChoice(['TGT', 'PGT', 'PRT', 'Senior Teacher', 'Head Teacher']) : randomChoice(['Staff', 'Assistant', 'Coordinator', 'Supervisor', '']),
          isActive: Math.random() > 0.05,
          dateOfBirth: randomDate(new Date(1960, 0, 1), new Date(2000, 11, 31)),
          bloodGroup: randomChoice(bloodGroups),
          highestQualification: role === 'teacher' ? randomChoice(['B.Ed', 'M.Ed', 'M.Sc', 'MA', 'PhD', '']) : randomChoice(['Graduate', 'Post Graduate', '12th', '10th', '']),
          experienceYears: randomInt(0, 30),
          joiningDate: randomDate(new Date(2015, 0, 1), new Date(2025, 11, 31)),
          emergencyContactName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
          emergencyContactPhone: generatePhone(),
          staffAddress: generateAddress(),
          basicPay: randomInt(15000, 80000),
          hra: randomInt(5000, 20000),
          da: randomInt(2000, 10000),
          conveyance: randomInt(1000, 5000),
          pfDeduction: randomInt(1000, 5000),
          esiDeduction: randomInt(500, 2000),
          bankName: randomChoice(['SBI', 'HDFC', 'ICICI', 'PNB', 'BOB', 'Axis Bank']),
          accountNumber: randomInt(10000000000, 99999999999).toString(),
          ifscCode: randomChoice(['SBIN0001234', 'HDFC0001234', 'ICIC0001234', 'PUNB0001234', 'BARB0001234']),
          casualLeaveBalance: randomInt(0, 12),
          earnedLeaveBalance: randomInt(0, 15),
          sickLeaveBalance: randomInt(0, 10)
        };
        allUsers.push(user);
        globalUserIndex++;
      }

      // Batch insert users for this role in smaller chunks
      const chunkSize = 500;
      const roleUsers = allUsers.slice(-count);
      let insertedCount = 0;

      for (let i = 0; i < roleUsers.length; i += chunkSize) {
        const chunk = roleUsers.slice(i, i + chunkSize);
        try {
          const inserted = await User.insertMany(chunk, { ordered: false });
          insertedCount += inserted.length;
        } catch (err) {
          // Insert one by one if batch fails
          for (const user of chunk) {
            try {
              await User.create(user);
              insertedCount++;
            } catch (e) {
              // Skip duplicates
            }
          }
        }
      }

      usersByRole[role] = await User.find({ role }).limit(count);
      console.log(`   ✅ ${role}: ${usersByRole[role].length} users created`);
    }

    stats.users = await User.countDocuments();
    console.log(`✅ Total Users in DB: ${stats.users}\n`);

    if ((usersByRole.student || []).length < 5000) {
      console.log('   Top-up student user accounts so each student can map to a unique login...');
      const extraStudentUsers = [];

      for (let i = usersByRole.student.length; i < 5000; i++) {
        const firstName = randomChoice(firstNames);
        const lastName = randomChoice(lastNames);
        const name = `${firstName} ${lastName}`;

        extraStudentUsers.push({
          name,
          email: generateEmail(`${name}.student`, globalUserIndex),
          password: hashPassword('password123'),
          role: 'student',
          phone: generatePhone(),
          isActive: true,
        });
        globalUserIndex++;
      }

      await insertManyRobust(User, extraStudentUsers, {
        chunkSize: 500,
        label: 'Student user top-up',
        fallbackCreate: true,
      });

      usersByRole.student = await User.find({ role: 'student' }).sort({ createdAt: 1, _id: 1 }).limit(5000);
      stats.users = await User.countDocuments();
      console.log(`   ✅ Student users available: ${usersByRole.student.length}`);
      console.log(`   ✅ Total Users in DB after top-up: ${stats.users}\n`);
    }

    // ========================================
    // 2. GENERATE CLASSES (500)
    // ========================================
    console.log('🏫 Generating 500 Classes...');
    const classes = [];
    const classNames = ['Nursery', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
    const sections = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    const academicYears = ['2024-2025', '2025-2026'];

    while (classes.length < 500) {
      const className = classNames[classes.length % classNames.length];
      const streamChoices = Number(className) >= 11 ? ['Science', 'Commerce', 'Arts'] : [''];
      const stream = streamChoices[Math.floor(classes.length / classNames.length) % streamChoices.length];
      const section = sections[classes.length % sections.length];

      classes.push({
        name: stream ? `${className}-${stream}` : className,
        section,
        sections: sections.slice(0, 10),
        classTeacher: usersByRole.teacher.length > 0 ? randomChoice(usersByRole.teacher)._id : null,
        subjects: subjects.slice(0, randomInt(4, 8)).map(sub => ({
          name: sub,
          subject: sub,
          teacherId: usersByRole.teacher.length > 0 ? randomChoice(usersByRole.teacher)._id : null,
          periodsPerWeek: randomInt(3, 6)
        })),
        capacity: randomInt(40, 60),
        academicYear: academicYears[classes.length % academicYears.length]
      });
    }

    await insertManyRobust(Class, classes.slice(0, 500), {
      chunkSize: 100,
      label: 'Classes',
      fallbackCreate: true,
    });
    const insertedClasses = await Class.find().sort({ createdAt: 1, _id: 1 }).limit(500);
    stats.classes = insertedClasses.length;
    console.log(`✅ Classes: ${stats.classes}\n`);

    // ========================================
    // 3. GENERATE STUDENTS (5,000)
    // ========================================
    console.log('🎓 Generating 5,000 Students...');
    const students = [];

    // Get all parent users to link as parentUserId
    const allParents = await User.find({ role: 'parent' }).limit(2000);

    for (let i = 0; i < 5000; i++) {
      const firstName = randomChoice(firstNames);
      const lastName = randomChoice(lastNames);
      const classDoc = randomChoice(insertedClasses);
      const parentUser = allParents.length > 0 ? randomChoice(allParents) : null;
      const studentUser = usersByRole.student[i] || null;

      students.push({
        userId: studentUser?._id || null,
        name: `${firstName} ${lastName}`,
        admissionNo: `ADM${String(i + 1).padStart(6, '0')}`,
        classId: classDoc._id,
        parentPhone: parentUser?.phone || generatePhone(),
        parentEmail: parentUser?.email || `parent${i}@mock.com`,
        parentUserId: parentUser?._id || null,
        dob: randomDate(new Date(2005, 0, 1), new Date(2018, 11, 31)),
        admissionDate: randomDate(new Date(2024, 3, 1), new Date(2025, 5, 30)),
        gender: randomChoice(genders),
        aadhaar: randomInt(100000000000, 999999999999).toString(),
        apaarId: `APAAR${randomInt(100000, 999999)}`,
        pen: `PEN${randomInt(100000, 999999)}`,
        enrollmentNo: `ENR${randomInt(100000, 999999)}`,
        section: classDoc.section,
        rollNumber: String(randomInt(1, 60)),
        academicYear: randomChoice(academicYears),
        bloodGroup: randomChoice(bloodGroups),
        nationality: 'Indian',
        religion: randomChoice(religions),
        category: randomChoice(categories),
        motherTongue: randomChoice(['Assamese', 'Hindi', 'Bengali', 'English']),
        previousSchool: randomChoice(['ABC School', 'XYZ School', 'PQR School', '']),
        fatherName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
        fatherOccupation: randomChoice(occupations),
        motherName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
        motherPhone: generatePhone(),
        motherOccupation: randomChoice(occupations),
        guardianName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
        guardianRelation: randomChoice(['Father', 'Mother', 'Uncle', 'Aunt', 'Grandparent']),
        guardianPhone: generatePhone(),
        emergencyContactName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
        emergencyContactPhone: generatePhone(),
        structuredAddress: generateAddress(),
        transportRequired: Math.random() > 0.6,
        hostelRequired: Math.random() > 0.8,
        medicalConditions: Math.random() > 0.9 ? randomChoice(['Asthma', 'Diabetes', 'Epilepsy', 'None']) : '',
        allergies: Math.random() > 0.9 ? randomChoice(['Peanuts', 'Dust', 'Pollens', 'None']) : '',
        admissionNotes: '',
        canteenWallet: {
          balance: randomFloat(100, 5000),
          rfidTagHex: `RFID${randomInt(100000, 999999).toString(16).toUpperCase()}`
        }
      });
    }

    // Insert students in chunks
    const studentCount = await insertManyRobust(Student, students, {
      chunkSize: 500,
      label: 'Students',
      fallbackCreate: true,
    });

    stats.students = studentCount;
    console.log(`✅ Students: ${stats.students}\n`);

    // Get all students for use in other modules
    const allStudents = await Student.find().limit(stats.students);
    console.log(`   Loaded ${allStudents.length} students for other modules\n`);

    // ========================================
    // 4. GENERATE ATTENDANCE (10,000)
    // ========================================
    console.log('📅 Generating 10,000 Attendance Records...');
    const attendanceRecords = [];
    const statuses = ['present', 'absent', 'late', 'half-day'];
    const attendanceWeights = [0.75, 0.15, 0.05, 0.05];

    function weightedRandom(values, weights) {
      const rand = Math.random();
      let sum = 0;
      for (let i = 0; i < values.length; i++) {
        sum += weights[i];
        if (rand < sum) return values[i];
      }
      return values[values.length - 1];
    }

    const usedAttendanceKeys = new Set();
    while (attendanceRecords.length < 10000 && allStudents.length > 0) {
      const student = randomChoice(allStudents);
      const rawDate = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));
      const normalizedDate = new Date(Date.UTC(rawDate.getFullYear(), rawDate.getMonth(), rawDate.getDate()));
      const subject = randomChoice(subjects);
      const attendanceKey = `${student._id}|${student.classId}|${normalizedDate.toISOString()}|${subject}`;

      if (usedAttendanceKeys.has(attendanceKey)) {
        continue;
      }
      usedAttendanceKeys.add(attendanceKey);

      attendanceRecords.push({
        studentId: student._id,
        classId: student.classId,
        teacherId: randomChoice(usersByRole.teacher)._id,
        date: normalizedDate,
        status: weightedRandom(statuses, attendanceWeights),
        smsSent: Math.random() > 0.5,
        subject,
        remarks: Math.random() > 0.9 ? randomChoice(['Late arrival', 'Early departure', 'Medical leave', '']) : ''
      });
    }

    const attendanceCount = await insertManyRobust(Attendance, attendanceRecords, {
      chunkSize: 500,
      label: 'Attendance',
    });

    stats.attendance = attendanceCount;
    console.log(`✅ Attendance Records: ${stats.attendance}\n`);

    // ========================================
    // 5. GENERATE STAFF ATTENDANCE (10,000)
    // ========================================
    console.log('👨‍💼 Generating 10,000 Staff Attendance Records...');
    const staffAttendanceRecords = [];

    for (let i = 0; i < 10000; i++) {
      const staff = randomChoice([...usersByRole.teacher, ...usersByRole.staff]);
      staffAttendanceRecords.push({
        staffId: staff._id,
        date: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        status: randomChoice(statuses),
        remarks: Math.random() > 0.9 ? randomChoice(['On leave', 'Late arrival', 'Half day', '']) : ''
      });
    }

    let staffAttCount = 0;
    for (let i = 0; i < staffAttendanceRecords.length; i += 1000) {
      try {
        const inserted = await StaffAttendance.insertMany(staffAttendanceRecords.slice(i, i + 1000), { ordered: false });
        staffAttCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.staffAttendance = staffAttCount;
    console.log(`✅ Staff Attendance: ${stats.staffAttendance}\n`);

    // ========================================
    // 6. GENERATE FEE STRUCTURES (5,000)
    // ========================================
    console.log('💰 Generating 5,000 Fee Structures...');
    const feeStructures = [];

    for (let i = 0; i < 5000; i++) {
      const classDoc = randomChoice(insertedClasses);
      feeStructures.push({
        classId: classDoc._id,
        feeType: randomChoice(feeTypes),
        amount: randomInt(500, 25000),
        dueDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        term: randomChoice(['Annual', 'Quarterly', 'Monthly', 'Half-Yearly']),
        description: `Fee for ${randomChoice(feeTypes)}`,
        lateFee: randomInt(0, 500),
        type: randomChoice(['monthly', 'annual', 'exam']),
        academicYear: randomChoice(academicYears)
      });
    }

    const feeStructCount = await insertManyRobust(FeeStructure, feeStructures, {
      chunkSize: 500,
      label: 'Fee structures',
    });
    stats.feeStructures = feeStructCount;
    console.log(`✅ Fee Structures: ${stats.feeStructures}\n`);

    // ========================================
    // 7. GENERATE FEE PAYMENTS (10,000)
    // ========================================
    console.log('💳 Generating 10,000 Fee Payments...');
    const feePayments = [];

    const insertedFeeStructures = await FeeStructure.find().select('_id feeType').lean();
    const receiptSeed = Date.now();

    for (let i = 0; i < 10000; i++) {
      if (allStudents.length === 0) break;
      const student = randomChoice(allStudents);
      feePayments.push({
        studentId: student._id,
        feeStructureId: insertedFeeStructures.length > 0 && Math.random() > 0.3 ? randomChoice(insertedFeeStructures)._id : null,
        amountPaid: randomInt(500, 25000),
        originalAmount: randomInt(500, 25000),
        discount: randomFloat(0, 5000),
        paymentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        receiptNo: `REC${receiptSeed}${String(i + 1).padStart(6, '0')}`,
        collectedBy: randomChoice([...usersByRole.accounts, ...usersByRole.staff])._id,
        paymentMode: randomChoice(paymentModes),
        remarks: Math.random() > 0.8 ? randomChoice(['Partial payment', 'Late payment', 'Discount applied', '']) : '',
        feeType: randomChoice(feeTypes),
        academicYear: randomChoice(academicYears)
      });
    }

    const feePayCount = await insertManyRobust(FeePayment, feePayments, {
      chunkSize: 500,
      label: 'Fee payments',
    });
    stats.feePayments = feePayCount;
    console.log(`✅ Fee Payments: ${stats.feePayments}\n`);

    // ========================================
    // 8. GENERATE EXAMS (5,000)
    // ========================================
    console.log('📝 Generating 5,000 Exams...');
    const exams = [];

    for (let i = 0; i < 5000; i++) {
      const classDoc = randomChoice(insertedClasses);
      const examDate = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));
      exams.push({
        name: `${randomChoice(examTypes)} - ${randomChoice(subjects)}`,
        examType: randomChoice(examTypes),
        classId: classDoc._id,
        subject: randomChoice(subjects),
        date: examDate,
        time: `${randomInt(9, 15)}:${randomChoice(['00', '15', '30', '45'])}`,
        startTime: `${randomInt(9, 12)}:${randomChoice(['00', '15', '30', '45'])}`,
        endTime: `${randomInt(12, 16)}:${randomChoice(['00', '15', '30', '45'])}`,
        roomNumber: `${randomInt(1, 50)}`,
        instructions: randomChoice(['Bring your own stationery', 'No calculators allowed', 'Report 15 minutes early', 'Follow exam rules']),
        totalMarks: randomChoice([50, 100, 25, 75]),
        passingMarks: randomChoice([20, 35, 15, 30])
      });
    }

    let examCount = 0;
    for (let i = 0; i < exams.length; i += 1000) {
      try {
        const inserted = await Exam.insertMany(exams.slice(i, i + 1000), { ordered: false });
        examCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.exams = examCount;
    console.log(`✅ Exams: ${stats.exams}\n`);

    // Get all exams for results
    const allExams = await Exam.find().limit(examCount);

    // ========================================
    // 9. GENERATE EXAM RESULTS (10,000)
    // ========================================
    console.log('📊 Generating 10,000 Exam Results...');
    const examResults = [];

    for (let i = 0; i < 10000; i++) {
      if (allExams.length === 0 || allStudents.length === 0) break;
      const exam = randomChoice(allExams);
      const student = randomChoice(allStudents);
      const totalMarks = exam.totalMarks || 100;
      const marksObtained = randomInt(0, totalMarks);
      const percentage = (marksObtained / totalMarks) * 100;

      let grade;
      if (percentage >= 90) grade = 'A+';
      else if (percentage >= 80) grade = 'A';
      else if (percentage >= 70) grade = 'B+';
      else if (percentage >= 60) grade = 'B';
      else if (percentage >= 50) grade = 'C';
      else if (percentage >= 40) grade = 'D';
      else grade = 'F';

      examResults.push({
        examId: exam._id,
        studentId: student._id,
        marksObtained,
        totalMarks,
        grade,
        remarks: Math.random() > 0.8 ? randomChoice(['Excellent', 'Good', 'Needs Improvement', 'Satisfactory', '']) : ''
      });
    }

    let examResCount = 0;
    for (let i = 0; i < examResults.length; i += 1000) {
      try {
        const inserted = await ExamResult.insertMany(examResults.slice(i, i + 1000), { ordered: false });
        examResCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.examResults = examResCount;
    console.log(`✅ Exam Results: ${stats.examResults}\n`);

    // ========================================
    // 10. GENERATE HOMEWORK (5,000)
    // ========================================
    console.log('📚 Generating 5,000 Homework Assignments...');
    const homeworks = [];

    for (let i = 0; i < 5000; i++) {
      const classDoc = randomChoice(insertedClasses);
      homeworks.push({
        classId: classDoc._id,
        teacherId: randomChoice(usersByRole.teacher)._id,
        subject: randomChoice(subjects),
        title: `${randomChoice(['Chapter', 'Exercise', 'Assignment', 'Practice'])} ${randomInt(1, 20)} - ${randomChoice(subjects)}`,
        description: randomChoice([
          'Complete all questions from the chapter',
          'Solve the exercises and submit by due date',
          'Read the chapter and answer the questions',
          'Write a summary of the topic',
          'Practice the numerical problems',
          'Prepare notes on the topic'
        ]),
        dueDate: randomDate(new Date(2025, 3, 1), new Date(2025, 11, 31))
      });
    }

    let hwCount = 0;
    for (let i = 0; i < homeworks.length; i += 1000) {
      try {
        const inserted = await Homework.insertMany(homeworks.slice(i, i + 1000), { ordered: false });
        hwCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.homeworks = hwCount;
    console.log(`✅ Homeworks: ${stats.homeworks}\n`);

    // ========================================
    // 11. GENERATE NOTICES (3,000)
    // ========================================
    console.log('📢 Generating 3,000 Notices...');
    const notices = [];

    for (let i = 0; i < 3000; i++) {
      notices.push({
        title: randomChoice([
          'School Holiday Notice',
          'Exam Schedule Notice',
          'Fee Payment Reminder',
          'Annual Day Celebration',
          'Sports Day Event',
          'Parent-Teacher Meeting',
          'Holiday Notice',
          'School Timing Change',
          'Uniform Code Reminder',
          'Library Book Return Reminder'
        ]),
        content: randomChoice([
          'This is to inform all students and parents about the upcoming event. Please note the date and time.',
          'All students are required to follow the instructions mentioned below for the smooth conduct of the event.',
          'Dear Parents, we request your presence at the school for the scheduled meeting. Your cooperation is appreciated.',
          'Notice for all concerned. Please take note of the details and act accordingly.',
          'Important announcement for all stakeholders. Please read carefully and follow the guidelines.'
        ]),
        audience: randomChoice([['all'], ['student'], ['teacher'], ['parent'], ['staff'], ['student', 'parent']]),
        createdBy: randomChoice([...usersByRole.teacher, ...usersByRole.hr, ...usersByRole.staff])._id,
        priority: randomChoice(noticePriorities),
        published: Math.random() > 0.1
      });
    }

    let noticeCount = 0;
    for (let i = 0; i < notices.length; i += 1000) {
      try {
        const inserted = await Notice.insertMany(notices.slice(i, i + 1000), { ordered: false });
        noticeCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.notices = noticeCount;
    console.log(`✅ Notices: ${stats.notices}\n`);

    // ========================================
    // 12. GENERATE NOTIFICATIONS (3,000)
    // ========================================
    console.log('🔔 Generating 3,000 Notifications...');
    const notifications = [];
    const notificationTypes = ['homework', 'complaint_to_parent', 'complaint_to_teacher', 'attendance_alert', 'fee_alert', 'general'];

    for (let i = 0; i < 3000; i++) {
      const allStaffUsers = [...usersByRole.teacher.slice(0, 100), ...usersByRole.parent.slice(0, 100), ...usersByRole.staff.slice(0, 100)];
      notifications.push({
        recipientId: randomChoice(allStaffUsers)._id,
        senderId: randomChoice(allStaffUsers)._id,
        title: randomChoice([
          'New Homework Assigned',
          'Attendance Alert',
          'Fee Payment Due',
          'Complaint Update',
          'Notice Published',
          'Exam Schedule Updated'
        ]),
        message: randomChoice([
          'A new homework has been assigned to your class. Please check the details.',
          'Your attendance for today has been marked. Contact office if there is any discrepancy.',
          'Your fee payment is due soon. Please pay before the due date to avoid late fees.',
          'A complaint has been raised and is being processed. You will be updated on the status.',
          'A new notice has been published. Please review it at your earliest convenience.'
        ]),
        type: randomChoice(notificationTypes),
        read: Math.random() > 0.6
      });
    }

    let notifCount = 0;
    for (let i = 0; i < notifications.length; i += 1000) {
      try {
        const inserted = await Notification.insertMany(notifications.slice(i, i + 1000), { ordered: false });
        notifCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.notifications = notifCount;
    console.log(`✅ Notifications: ${stats.notifications}\n`);

    // ========================================
    // 13. GENERATE LIBRARY BOOKS (2,000)
    // ========================================
    console.log('📖 Generating 2,000 Library Books...');
    const libraryBooks = [];

    for (let i = 0; i < 2000; i++) {
      const titleIndex = i % bookTitles.length;
      libraryBooks.push({
        title: `${bookTitles[titleIndex]} - Vol ${randomInt(1, 10)}`,
        author: randomChoice(bookAuthors),
        isbn: `978${randomInt(1000000000, 9999999999).toString()}`,
        totalCopies: randomInt(1, 10),
        availableCopies: randomInt(0, 10)
      });
    }

    let libBookCount = 0;
    for (let i = 0; i < libraryBooks.length; i += 1000) {
      try {
        const inserted = await LibraryBook.insertMany(libraryBooks.slice(i, i + 1000), { ordered: false });
        libBookCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.libraryBooks = libBookCount;
    console.log(`✅ Library Books: ${stats.libraryBooks}\n`);

    const allLibraryBooks = await LibraryBook.find().limit(libBookCount);

    // ========================================
    // 14. GENERATE LIBRARY TRANSACTIONS (5,000)
    // ========================================
    console.log('📤 Generating 5,000 Library Transactions...');
    const libraryTransactions = [];
    const transactionStatuses = ['BORROWED', 'RETURNED', 'LOST'];

    for (let i = 0; i < 5000; i++) {
      if (allStudents.length === 0 || allLibraryBooks.length === 0) break;
      const student = randomChoice(allStudents);
      const book = randomChoice(allLibraryBooks);
      const issueDate = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));
      const dueDate = new Date(issueDate);
      dueDate.setDate(dueDate.getDate() + randomInt(7, 30));
      const status = randomChoice(transactionStatuses);

      libraryTransactions.push({
        studentId: student._id,
        bookId: book._id,
        issueDate,
        dueDate,
        returnDate: status === 'RETURNED' ? new Date(dueDate.getTime() + randomInt(-5, 10) * 86400000) : null,
        status,
        fineAmount: status === 'RETURNED' ? randomInt(0, 500) : status === 'LOST' ? randomInt(200, 2000) : 0,
        remarks: ''
      });
    }

    let libTransCount = 0;
    for (let i = 0; i < libraryTransactions.length; i += 1000) {
      try {
        const inserted = await LibraryTransaction.insertMany(libraryTransactions.slice(i, i + 1000), { ordered: false });
        libTransCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.libraryTransactions = libTransCount;
    console.log(`✅ Library Transactions: ${stats.libraryTransactions}\n`);

    // ========================================
    // 15. GENERATE CANTEEN ITEMS (1,000)
    // ========================================
    console.log('🍔 Generating 1,000 Canteen Items...');
    const canteenItems = [];

    for (let i = 0; i < 1000; i++) {
      const nameIndex = i % canteenNames.length;
      canteenItems.push({
        name: `${canteenNames[nameIndex]} ${randomChoice(['', '- Small', '- Medium', '- Large', '- Combo'])}`,
        price: randomFloat(10, 200),
        quantityAvailable: randomInt(0, 100),
        category: randomChoice(['Snacks', 'Meals', 'Beverages', 'Sweets', 'Fast Food', 'South Indian', 'Chinese']),
        isAvailable: Math.random() > 0.2
      });
    }

    let cantItemCount = 0;
    for (let i = 0; i < canteenItems.length; i += 500) {
      try {
        const inserted = await CanteenItem.insertMany(canteenItems.slice(i, i + 500), { ordered: false });
        cantItemCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.canteenItems = cantItemCount;
    console.log(`✅ Canteen Items: ${stats.canteenItems}\n`);

    const allCanteenItems = await CanteenItem.find().limit(cantItemCount);

    // ========================================
    // 16. GENERATE CANTEEN SALES (5,000)
    // ========================================
    console.log('💵 Generating 5,000 Canteen Sales...');
    const canteenSales = [];

    for (let i = 0; i < 5000; i++) {
      if (allStudents.length === 0 || allCanteenItems.length === 0) break;
      const itemCount = randomInt(1, 3);
      const items = [];
      let total = 0;

      for (let j = 0; j < itemCount; j++) {
        const item = randomChoice(allCanteenItems);
        const quantity = randomInt(1, 5);
        const price = item.price;
        items.push({ itemId: item._id, quantity, price });
        total += price * quantity;
      }

      canteenSales.push({
        items,
        total,
        soldTo: randomChoice(allStudents)._id.toString(),
        soldBy: randomChoice(usersByRole.canteen)._id,
        date: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31))
      });
    }

    let cantSaleCount = 0;
    for (let i = 0; i < canteenSales.length; i += 1000) {
      try {
        const inserted = await CanteenSale.insertMany(canteenSales.slice(i, i + 1000), { ordered: false });
        cantSaleCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.canteenSales = cantSaleCount;
    console.log(`✅ Canteen Sales: ${stats.canteenSales}\n`);

    // ========================================
    // 17. GENERATE BUS ROUTES (500)
    // ========================================
    console.log('🚌 Generating 500 Bus Routes...');
    const busRoutes = [];

    for (let i = 0; i < 500; i++) {
      const stopCount = randomInt(5, 10);
      const stops = [];
      for (let j = 0; j < stopCount; j++) {
        stops.push({
          stopName: `${randomChoice(cities)} Stop ${j + 1}`,
          stopCode: `STOP${String(i).padStart(3, '0')}${String(j).padStart(2, '0')}`,
          sequence: j + 1,
          arrivalTime: `${randomInt(6, 9).toString().padStart(2, '0')}:${randomInt(0, 59).toString().padStart(2, '0')}`,
          departureTime: `${randomInt(6, 9).toString().padStart(2, '0')}:${randomInt(0, 59).toString().padStart(2, '0')}`,
          distance: randomFloat(1, 20),
          landmark: randomChoice(['Near Temple', 'Opposite Market', 'Beside Park', 'Near Hospital', '']),
          isPickup: Math.random() > 0.3,
          isDrop: Math.random() > 0.3
        });
      }

      busRoutes.push({
        routeName: `${randomChoice(routes)} - ${String(i + 1).padStart(3, '0')}`,
        routeCode: `RT${String(i + 1).padStart(4, '0')}`,
        routeNumber: `R${i + 1}`,
        stops,
        totalDistance: randomFloat(10, 50),
        totalDuration: randomInt(30, 120),
        departureTime: `${randomInt(6, 8).toString().padStart(2, '0')}:${randomInt(0, 59).toString().padStart(2, '0')}`,
        returnTime: `${randomInt(15, 17).toString().padStart(2, '0')}:${randomInt(0, 59).toString().padStart(2, '0')}`,
        vehicleType: randomChoice(vehicleTypes),
        capacity: randomInt(30, 60),
        activeStudents: randomInt(10, 50),
        feePerStudent: randomInt(500, 3000),
        totalCollection: randomInt(10000, 150000),
        isActive: Math.random() > 0.1
      });
    }

    const busRouteCount = await insertManyRobust(BusRoute, busRoutes, {
      chunkSize: 100,
      label: 'Bus routes',
    });
    stats.busRoutes = busRouteCount;
    console.log(`✅ Bus Routes: ${stats.busRoutes}\n`);

    // ========================================
    // 18. GENERATE TRANSPORT VEHICLES (500)
    // ========================================
    console.log('🚐 Generating 500 Transport Vehicles...');
    const transportVehicles = [];

    for (let i = 0; i < 500; i++) {
      transportVehicles.push({
        busNumber: `BUS-${String(i + 1).padStart(4, '0')}`,
        numberPlate: `AS${String((i % 30) + 1).padStart(2, '0')}${String.fromCharCode(65 + (i % 26))}${String(1000 + i).padStart(4, '0')}`,
        driverId: usersByRole.driver.length > 0 ? randomChoice(usersByRole.driver)._id : null,
        conductorId: usersByRole.conductor.length > 0 ? randomChoice(usersByRole.conductor)._id : null,
        route: randomChoice(routes),
        capacity: randomInt(30, 60)
      });
    }

    const transVehCount = await insertManyRobust(TransportVehicle, transportVehicles, {
      chunkSize: 100,
      label: 'Transport vehicles',
      fallbackCreate: true,
    });
    stats.transportVehicles = transVehCount;
    console.log(`✅ Transport Vehicles: ${stats.transportVehicles}\n`);

    const allTransportVehicles = await TransportVehicle.find().limit(transVehCount);

    // ========================================
    // 19. GENERATE TRANSPORT ATTENDANCE (5,000)
    // ========================================
    console.log('🚌 Generating 5,000 Transport Attendance...');
    const transportAttendances = [];
    const transportStatuses = ['boarded', 'dropped_off', 'absent'];

    for (let i = 0; i < 5000; i++) {
      if (allStudents.length === 0 || allTransportVehicles.length === 0) break;
      const student = randomChoice(allStudents);
      const vehicle = randomChoice(allTransportVehicles);
      const date = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));

      transportAttendances.push({
        busId: vehicle._id,
        studentId: student._id,
        date: date.toISOString().split('T')[0],
        status: randomChoice(transportStatuses)
      });
    }

    let transAttCount = 0;
    for (let i = 0; i < transportAttendances.length; i += 1000) {
      try {
        const inserted = await TransportAttendance.insertMany(transportAttendances.slice(i, i + 1000), { ordered: false });
        transAttCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.transportAttendances = transAttCount;
    console.log(`✅ Transport Attendance: ${stats.transportAttendances}\n`);

    // ========================================
    // 20-23. HOSTEL MODULES
    // ========================================
    console.log('🏠 Generating Hostel Data...');

    // 20. Hostel Room Types (50)
    const hostelRoomTypes = [];
    for (let i = 0; i < 50; i++) {
      const nameIndex = i % hostelRoomNames.length;
      hostelRoomTypes.push({
        name: `${hostelRoomNames[nameIndex]} - Block ${String.fromCharCode(65 + (i % 10))} - ${String(i + 1).padStart(2, '0')}`,
        code: `RT${String(i + 1).padStart(3, '0')}`,
        occupancy: randomInt(1, 6),
        genderPolicy: randomChoice(['boys', 'girls', 'mixed']),
        defaultFee: randomInt(10000, 50000),
        amenities: randomChoice([['AC', 'WiFi', 'Attached Bathroom'], ['Fan', 'WiFi', 'Common Bathroom']]),
        description: `${hostelRoomNames[nameIndex]} type room`
      });
    }
    await insertManyRobust(HostelRoomType, hostelRoomTypes, {
      chunkSize: 50,
      label: 'Hostel room types',
      fallbackCreate: true,
    });
    const insertedHostelRoomTypes = await HostelRoomType.find().sort({ createdAt: 1, _id: 1 }).limit(50);
    stats.hostelRoomTypes = insertedHostelRoomTypes.length;

    // 21. Hostel Rooms (200)
    const hostelRooms = [];
    for (let i = 0; i < 200 && insertedHostelRoomTypes.length > 0; i++) {
      const roomType = randomChoice(insertedHostelRoomTypes);
      hostelRooms.push({
        roomTypeId: roomType._id,
        roomNumber: `${randomChoice(['A', 'B', 'C', 'D'])}${String(i + 101).padStart(3, '0')}`,
        block: randomChoice(['A', 'B', 'C', 'D']),
        floor: randomChoice(['Ground', 'First', 'Second']),
        capacity: roomType.occupancy,
        occupiedBeds: randomInt(0, roomType.occupancy),
        status: randomChoice(['AVAILABLE', 'LIMITED', 'FULL'])
      });
    }
    const hostelRoomCount = await insertManyRobust(HostelRoom, hostelRooms, {
      chunkSize: 100,
      label: 'Hostel rooms',
      fallbackCreate: true,
    });
    stats.hostelRooms = hostelRoomCount;

    // 22. Hostel Fee Structures (50)
    const hostelFeeStructures = [];
    for (let i = 0; i < 50 && insertedHostelRoomTypes.length > 0; i++) {
      const roomType = randomChoice(insertedHostelRoomTypes);
      hostelFeeStructures.push({
        roomTypeId: roomType._id,
        academicYear: randomChoice(academicYears),
        term: randomChoice(['Annual', 'Quarterly', 'Monthly']),
        billingCycle: randomChoice(['monthly', 'quarterly', 'annual']),
        amount: randomInt(10000, 100000),
        cautionDeposit: randomInt(5000, 20000),
        messCharge: randomInt(3000, 10000),
        maintenanceCharge: randomInt(1000, 5000)
      });
    }
    await insertManyRobust(HostelFeeStructure, hostelFeeStructures, {
      chunkSize: 50,
      label: 'Hostel fee structures',
      fallbackCreate: true,
    });
    const insertedHostelFeeStructures = await HostelFeeStructure.find().sort({ createdAt: 1, _id: 1 }).limit(50);
    stats.hostelFeeStructures = insertedHostelFeeStructures.length;

    // 23. Hostel Allocations (200)
    const hostelAllocations = [];
    const hostelStudents = allStudents.filter(s => s.hostelRequired).slice(0, 200);
    const allHostelRooms = await HostelRoom.find().limit(200);

    for (let i = 0; i < Math.min(200, hostelStudents.length); i++) {
      if (allHostelRooms.length === 0 || insertedHostelRoomTypes.length === 0) break;
      const student = hostelStudents[i];
      const room = randomChoice(allHostelRooms);
      const roomType = insertedHostelRoomTypes.find(rt => rt._id.toString() === room.roomTypeId.toString()) || randomChoice(insertedHostelRoomTypes);

      hostelAllocations.push({
        studentId: student._id,
        roomTypeId: roomType._id,
        roomId: room._id,
        academicYear: randomChoice(academicYears),
        bedLabel: `Bed ${randomInt(1, room.capacity)}`,
        allotmentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        status: Math.random() > 0.2 ? 'ACTIVE' : 'VACATED',
        guardianContactName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
        guardianContactPhone: generatePhone()
      });
    }

    const hostelAllocCount = await insertManyRobust(HostelAllocation, hostelAllocations, {
      chunkSize: 100,
      label: 'Hostel allocations',
      fallbackCreate: true,
    });
    stats.hostelAllocations = hostelAllocCount;
    console.log(`✅ Hostel: ${stats.hostelRoomTypes} room types, ${stats.hostelRooms} rooms, ${stats.hostelAllocations} allocations\n`);

    // ========================================
    // 24. GENERATE COMPLAINTS (2,000)
    // ========================================
    console.log('⚠️ Generating 2,000 Complaints...');
    const complaints = [];

    for (let i = 0; i < 2000; i++) {
      if (allStudents.length === 0) break;
      const allUsers = [...usersByRole.teacher.slice(0, 100), ...usersByRole.parent.slice(0, 100), ...usersByRole.staff.slice(0, 100)];
      const user = randomChoice(allUsers);
      complaints.push({
        userId: user._id,
        type: randomChoice(complaintTypes),
        studentId: Math.random() > 0.5 ? randomChoice(allStudents)._id : undefined,
        subject: randomChoice(['Infrastructure Issue', 'Behavioral Concern', 'Transport Issue', 'Canteen Quality', 'Academic Concern']),
        description: randomChoice([
          'This is to bring to your attention an issue that needs immediate resolution.',
          'We have received feedback regarding the matter and request prompt action.',
          'The concerned parties have raised a complaint that requires attention.',
          'Please look into this matter at the earliest and take necessary action.'
        ]),
        status: randomChoice(complaintStatuses),
        raisedByRole: user.role,
        assignedToRole: randomChoice(['teacher', 'staff', 'hr']),
        resolutionNote: Math.random() > 0.5 ? randomChoice(['Issue resolved', 'Under investigation', 'Action taken', '']) : ''
      });
    }

    let complaintCount = 0;
    for (let i = 0; i < complaints.length; i += 1000) {
      try {
        const inserted = await Complaint.insertMany(complaints.slice(i, i + 1000), { ordered: false });
        complaintCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.complaints = complaintCount;
    console.log(`✅ Complaints: ${stats.complaints}\n`);

    // ========================================
    // 25. GENERATE REMARKS (3,000)
    // ========================================
    console.log('📝 Generating 3,000 Remarks...');
    const remarks = [];

    for (let i = 0; i < 3000; i++) {
      if (allStudents.length === 0) break;
      const student = randomChoice(allStudents);
      remarks.push({
        studentId: student._id,
        teacherId: randomChoice(usersByRole.teacher)._id,
        remark: randomChoice([
          'Excellent performance in class',
          'Needs to improve attendance',
          'Very active in extracurricular activities',
          'Should focus on studies more',
          'Good behavior and discipline',
          'Needs improvement in homework submission',
          'Outstanding contribution in sports',
          'Regular and punctual'
        ])
      });
    }

    let remarkCount = 0;
    for (let i = 0; i < remarks.length; i += 1000) {
      try {
        const inserted = await Remark.insertMany(remarks.slice(i, i + 1000), { ordered: false });
        remarkCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.remarks = remarkCount;
    console.log(`✅ Remarks: ${stats.remarks}\n`);

    // ========================================
    // 26. GENERATE LEAVES (1,000)
    // ========================================
    console.log('🏖️ Generating 1,000 Leave Applications...');
    const leaves = [];
    const leaveStatuses = ['pending', 'approved', 'rejected'];

    for (let i = 0; i < 1000; i++) {
      const staff = randomChoice([...usersByRole.teacher.slice(0, 100), ...usersByRole.staff.slice(0, 100)]);
      const fromDate = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));
      const toDate = new Date(fromDate);
      toDate.setDate(toDate.getDate() + randomInt(1, 10));

      leaves.push({
        staffId: staff._id,
        type: randomChoice(leaveTypes),
        fromDate,
        toDate,
        reason: randomChoice(['Personal work', 'Medical appointment', 'Family function', 'Illness', 'Emergency']),
        status: randomChoice(leaveStatuses),
        reviewedBy: Math.random() > 0.3 ? randomChoice(usersByRole.hr)._id : undefined,
        reviewNote: Math.random() > 0.5 ? randomChoice(['Approved', 'Please provide medical certificate', 'Not approved', '']) : ''
      });
    }

    let leaveCount = 0;
    for (let i = 0; i < leaves.length; i += 500) {
      try {
        const inserted = await Leave.insertMany(leaves.slice(i, i + 500), { ordered: false });
        leaveCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.leaves = leaveCount;
    console.log(`✅ Leaves: ${stats.leaves}\n`);

    // ========================================
    // 27. GENERATE SALARY STRUCTURES (1,000)
    // ========================================
    console.log('💼 Generating 1,000 Salary Structures...');
    const salaryStructures = [];

    const salaryStaffPool = [
      ...usersByRole.teacher,
      ...usersByRole.staff,
      ...usersByRole.hr,
      ...usersByRole.accounts,
      ...usersByRole.canteen,
      ...usersByRole.conductor,
      ...usersByRole.driver,
    ].slice(0, 1000);

    for (const staff of salaryStaffPool) {
      salaryStructures.push({
        staffId: staff._id,
        basicSalary: randomInt(15000, 80000),
        hra: randomInt(5000, 20000),
        da: randomInt(2000, 10000),
        conveyance: randomInt(1000, 5000),
        medicalAllowance: randomInt(1000, 5000),
        specialAllowance: randomInt(2000, 10000),
        pfDeduction: randomInt(1000, 5000),
        taxDeduction: randomInt(500, 5000),
        otherDeductions: randomInt(0, 2000),
        effectiveFrom: randomDate(new Date(2024, 0, 1), new Date(2025, 11, 31))
      });
    }

    const salaryStructCount = await insertManyRobust(SalaryStructure, salaryStructures, {
      chunkSize: 250,
      label: 'Salary structures',
      fallbackCreate: true,
    });
    stats.salaryStructures = salaryStructCount;
    console.log(`✅ Salary Structures: ${stats.salaryStructures}\n`);

    // ========================================
    // 28. GENERATE PAYROLL (2,000)
    // ========================================
    console.log('💰 Generating 2,000 Payroll Records...');
    const payrolls = [];

    for (let i = 0; i < 2000; i++) {
      const staff = randomChoice([...usersByRole.teacher.slice(0, 200), ...usersByRole.staff.slice(0, 200)]);
      const basicSalary = randomInt(15000, 80000);
      const hra = randomInt(5000, 20000);
      const da = randomInt(2000, 10000);
      const conveyance = randomInt(1000, 5000);
      const medicalAllowance = randomInt(1000, 5000);
      const specialAllowance = randomInt(2000, 10000);
      const totalEarnings = basicSalary + hra + da + conveyance + medicalAllowance + specialAllowance;
      const pfDeduction = randomInt(1000, 5000);
      const taxDeduction = randomInt(500, 5000);
      const otherDeductions = randomInt(0, 2000);
      const totalDeductions = pfDeduction + taxDeduction + otherDeductions;

      payrolls.push({
        staffId: staff._id,
        month: randomInt(1, 12),
        year: randomChoice([2024, 2025, 2026]),
        basicSalary,
        hra,
        da,
        conveyance,
        medicalAllowance,
        specialAllowance,
        totalEarnings,
        pfDeduction,
        taxDeduction,
        otherDeductions,
        totalDeductions,
        netPay: totalEarnings - totalDeductions,
        generatedDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        isPaid: Math.random() > 0.3
      });
    }

    let payrollCount = 0;
    for (let i = 0; i < payrolls.length; i += 500) {
      try {
        const inserted = await Payroll.insertMany(payrolls.slice(i, i + 500), { ordered: false });
        payrollCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.payrolls = payrollCount;
    console.log(`✅ Payrolls: ${stats.payrolls}\n`);

    // ========================================
    // 29. GENERATE ROUTINES (100)
    // ========================================
    console.log('📅 Generating 100 Class Routines...');
    const routines = [];
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const periods = ['Period 1', 'Period 2', 'Period 3', 'Period 4', 'Period 5', 'Period 6'];

    for (let i = 0; i < 100; i++) {
      const classDoc = randomChoice(insertedClasses);
      const timetable = {};

      for (const day of days) {
        for (const period of periods.slice(0, randomInt(4, 6))) {
          timetable[`${day}-${period}`] = {
            subject: randomChoice(subjects),
            teacherId: randomChoice(usersByRole.teacher)._id.toString(),
            teacherName: randomChoice(usersByRole.teacher).name
          };
        }
      }

      routines.push({
        classId: classDoc._id,
        timetable
      });
    }

    let routineCount = 0;
    for (let i = 0; i < routines.length; i += 50) {
      try {
        const inserted = await Routine.insertMany(routines.slice(i, i + 50), { ordered: false });
        routineCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }
    stats.routines = routineCount;
    console.log(`✅ Routines: ${stats.routines}\n`);

    console.log('🧠 Retraining chatbot NLP model with generated entities...');
    await trainChatbot({ forceRetrain: true, forceEntityReload: true });
    console.log('✅ Chatbot model refreshed\n');

    // ========================================
    // FINAL SUMMARY
    // ========================================
    const totalTime = ((Date.now() - startTime) / 1000).toFixed(2);
    const totalRecords = Object.values(stats).reduce((sum, val) => sum + val, 0);

    console.log('\n╔═══════════════════════════════════════════════════════╗');
    console.log('║        MOCK DATA GENERATION COMPLETE!                 ║');
    console.log('╠═══════════════════════════════════════════════════════╣');

    const entries = Object.entries(stats);
    for (const [key, value] of entries) {
      const paddedKey = key.charAt(0).toUpperCase() + key.slice(1).replace(/([A-Z])/g, ' $1');
      console.log(`║   ${paddedKey.padEnd(35)} ${String(value).padStart(8)}           ║`);
    }

    console.log('║                                                       ║');
    console.log(`║   ${'TOTAL RECORDS'.padEnd(35)} ${String(totalRecords).padStart(8)}           ║`);
    console.log(`║   ${'TIME TAKEN'.padEnd(35)} ${String(totalTime + 's').padStart(8)}           ║`);
    console.log('╚═══════════════════════════════════════════════════════╝\n');

    // Verify all collections
    console.log('🔍 Verifying data integrity...\n');
    const verificationTargets = [
      ['users', prisma.user],
      ['students', prisma.student],
      ['classes', prisma.class],
      ['attendance', prisma.attendance],
      ['staffAttendance', prisma.staffAttendance],
      ['feeStructures', prisma.feeStructure],
      ['feePayments', prisma.feePayment],
      ['exams', prisma.exam],
      ['examResults', prisma.examResult],
      ['homeworks', prisma.homework],
      ['notices', prisma.notice],
      ['leaves', prisma.leave],
      ['payrolls', prisma.payroll],
      ['salaryStructures', prisma.salaryStructure],
      ['complaints', prisma.complaint],
      ['remarks', prisma.remark],
      ['libraryBooks', prisma.libraryBook],
      ['libraryTransactions', prisma.libraryTransaction],
      ['busRoutes', prisma.busRoute],
      ['transportVehicles', prisma.transportVehicle],
      ['transportAttendance', prisma.transportAttendance],
      ['canteenItems', prisma.canteenItem],
      ['canteenSales', prisma.canteenSale],
      ['hostelRoomTypes', prisma.hostelRoomType],
      ['hostelRooms', prisma.hostelRoom],
      ['hostelAllocations', prisma.hostelAllocation],
      ['hostelFeeStructures', prisma.hostelFeeStructure],
      ['notifications', prisma.notification],
    ];
    let totalVerified = 0;

    for (const [name, model] of verificationTargets) {
      try {
        const count = await model.count();
        totalVerified += count;
        if (count > 0) {
          console.log(`✅ ${name.padEnd(30)} ${count.toString().padStart(10)}`);
        }
      } catch (err) { }
    }

    console.log(`\n📊 TOTAL VERIFIED RECORDS: ${totalVerified}`);

  } catch (error) {
    console.error('❌ Error generating mock data:', error.message);
    console.error(error.stack);
  } finally {
    await sleep(2000);
    await prisma.$disconnect();
    console.log('\n👋 Database connection closed');
    process.exit(0);
  }
}

generateMockData().catch(console.error);
