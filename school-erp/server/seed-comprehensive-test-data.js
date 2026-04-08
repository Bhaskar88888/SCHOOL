/**
 * Comprehensive Test Data Generator for School ERP
 * Generates 10,000+ records using Prisma ORM across ALL modules
 * Creates realistic fake staff, students, and test data
 */

const { createApp } = require('./server');
const prisma = require('./config/prisma');
const bcrypt = require('bcryptjs');

// =====================================================
// DATA GENERATORS
// =====================================================

const firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan', 'Ananya', 'Diya', 'Saanvi', 'Aadhya', 'Kavya', 'Anika', 'Meera', 'Riya', 'Pari', 'Tara', 'Rahul', 'Rohit', 'Pooja', 'Neha', 'Amit', 'Sunita', 'Rajesh', 'Kavita', 'Vikram', 'Priya', 'Deepak', 'Sneha', 'Anil', 'Manju', 'Sanjay', 'Rekha', 'Ravi', 'Suresh', 'Lakshmi', 'Dinesh'];
const lastNames = ['Sharma', 'Verma', 'Gupta', 'Singh', 'Kumar', 'Patel', 'Reddy', 'Nair', 'Pillai', 'Chatterjee', 'Mukherjee', 'Banerjee', 'Joshi', 'Rao', 'Menon', 'Iyer', 'Agarwal', 'Bhatia', 'Khanna', 'Mehta', 'Boruah', 'Goswami', 'Baruah', 'Bhattacharjee', 'Das', 'Dutta', 'Hazarika', 'Kalita', 'Phukan', 'Saikia'];
const cities = ['Mumbai', 'Delhi', 'Bangalore', 'Kolkata', 'Chennai', 'Hyderabad', 'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow', 'Guwahati', 'Dibrugarh', 'Jorhat', 'Tezpur', 'Nagaon', 'Tinsukia', 'Sivasagar', 'Dhemaji', 'Lakhimpur', 'Biswanath'];
const states = ['Maharashtra', 'Delhi', 'Karnataka', 'West Bengal', 'Tamil Nadu', 'Telangana', 'Gujarat', 'Rajasthan', 'Uttar Pradesh', 'Assam'];
const bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const subjects = ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'English', 'Hindi', 'Assamese', 'History', 'Geography', 'Computer Science', 'Economics', 'Political Science', 'Accountancy', 'Business Studies', 'Physical Education', 'Art', 'Music'];
const examTypes = ['Unit Test 1', 'Unit Test 2', 'Mid-Term', 'Unit Test 3', 'Unit Test 4', 'Final Term'];
const feeTypes = ['Tuition Fee', 'Transport Fee', 'Library Fee', 'Sports Fee', 'Exam Fee', 'Laboratory Fee', 'Computer Fee', 'Annual Day Fee', 'Development Fee', 'Admission Fee'];
const paymentModes = ['cash', 'online', 'cheque', 'card'];
const leaveTypes = ['sick', 'casual', 'earned'];
const noticePriorities = ['normal', 'important', 'urgent'];
const complaintTypes = ['teacher_to_parent', 'parent_to_teacher', 'student_to_admin', 'parent_to_admin', 'general'];
const complaintStatuses = ['open', 'in_progress', 'resolved', 'closed'];
const bookTitles = ['The Discovery of India', 'Train to Pakistan', 'The Guide', 'Malgudi Days', 'God of Small Things', 'The White Tiger', 'Indian Stories', 'Gitanjali', 'The Immortals of Meluha', 'Two States', 'Rich Like Us', 'The Inheritance of Loss', 'Midnight\'s Children', 'The Palace of Illusions', 'Animal Farm', 'To Kill a Mockingbird', 'The Great Gatsby', '1984', 'Pride and Prejudice', 'Harry Potter', 'The Alchemist', 'Wings of Fire', 'Five Point Someone', 'The 3 Mistakes of My Life'];
const bookAuthors = ['Jawaharlal Nehru', 'Khushwant Singh', 'R.K. Narayan', 'Arundhati Roy', 'Aravind Adiga', 'Rabindranath Tagore', 'Amish Tripathi', 'Chetan Bhagat', 'Kiran Desai', 'Salman Rushdie', 'Chitra Banerjee', 'George Orwell', 'Harper Lee', 'F. Scott Fitzgerald', 'Jane Austen', 'J.K. Rowling', 'Paulo Coelho', 'A.P.J. Abdul Kalam'];
const canteenNames = ['Samosa', 'Kachori', 'Puri', 'Paratha', 'Dosa', 'Idli', 'Vada', 'Pav Bhaji', 'Burger', 'Pizza', 'Sandwich', 'Noodles', 'Fried Rice', 'Chowmein', 'Momos', 'Spring Roll', 'Cutlet', 'Tikki', 'Jalebi', 'Gulab Jamun', 'Ice Cream', 'Cold Drink', 'Tea', 'Coffee', 'Lassi', 'Milkshake'];
const categories = ['General', 'OBC', 'SC', 'ST', 'EWS'];
const religions = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist'];
const genders = ['male', 'female', 'other'];
const occupations = ['Teacher', 'Engineer', 'Doctor', 'Business', 'Farmer', 'Government Service', 'Private Job', 'Self Employed', 'Housewife', 'Retired'];
const hostelRoomNames = ['Single Occupancy', 'Double Occupancy', 'Triple Occupancy', 'Dormitory'];
const vehicleTypes = ['AC Bus', 'Non-AC Bus', 'Van', 'Mini Bus'];
const routeNames = ['Route A - North', 'Route B - South', 'Route C - East', 'Route D - West', 'Route E - Central', 'Route F - Market', 'Route G - Station', 'Route H - Hospital'];

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

// =====================================================
// MAIN SEED FUNCTION
// =====================================================

async function seedTestData() {
  console.log('🚀 Starting comprehensive test data generation with Prisma...\n');

  const startTime = Date.now();
  const stats = {};

  try {
    // Clear existing data
    console.log('🗑️  Clearing existing test data...');
    await prisma.chatbotLog.deleteMany();
    await prisma.auditLog.deleteMany();
    await prisma.canteenSaleItem.deleteMany();
    await prisma.canteenSale.deleteMany();
    await prisma.canteenItem.deleteMany();
    await prisma.transportAttendance.deleteMany();
    await prisma.busStop.deleteMany();
    await prisma.busRoute.deleteMany();
    await prisma.transportVehicle.deleteMany();
    await prisma.hostelAllocation.deleteMany();
    await prisma.hostelFeeStructure.deleteMany();
    await prisma.hostelRoom.deleteMany();
    await prisma.hostelRoomType.deleteMany();
    await prisma.libraryTransaction.deleteMany();
    await prisma.libraryBook.deleteMany();
    await prisma.payroll.deleteMany();
    await prisma.salaryStructure.deleteMany();
    await prisma.staffAttendance.deleteMany();
    await prisma.notification.deleteMany();
    await prisma.notice.deleteMany();
    await prisma.routine.deleteMany();
    await prisma.homework.deleteMany();
    await prisma.complaint.deleteMany();
    await prisma.remark.deleteMany();
    await prisma.leave.deleteMany();
    await prisma.attendance.deleteMany();
    await prisma.examResult.deleteMany();
    await prisma.exam.deleteMany();
    await prisma.feePayment.deleteMany();
    await prisma.feeStructure.deleteMany();
    await prisma.student.deleteMany();
    await prisma.classSubject.deleteMany();
    await prisma.class.deleteMany();
    await prisma.user.deleteMany({ where: { role: { not: 'superadmin' } } });
    console.log('✅ Cleared existing data\n');

    // =====================================================
    // 1. GENERATE USERS (10,000+)
    // =====================================================
    console.log('👥 Generating 10,000+ Users across all roles...');

    const roleDistribution = {
      teacher: 2000,
      student: 3000,
      parent: 2000,
      staff: 1000,
      hr: 500,
      accounts: 500,
      canteen: 300,
      conductor: 300,
      driver: 400,
      principal: 50,
      admin: 50
    };

    const usersByRole = {};
    let userCounter = 0;

    for (const [role, count] of Object.entries(roleDistribution)) {
      console.log(`   Creating ${count} ${role} users...`);
      const users = [];

      for (let i = 0; i < count; i++) {
        const firstName = randomChoice(firstNames);
        const lastName = randomChoice(lastNames);
        const name = `${firstName} ${lastName}`;

        users.push({
          name,
          email: `test.${role}.${userCounter}@school.edu`,
          password: bcrypt.hashSync('test123', 10),
          role,
          phone: generatePhone(),
          employeeId: role !== 'parent' && role !== 'student' ? `EMP${role.toUpperCase().slice(0, 3)}${String(userCounter).padStart(5, '0')}` : null,
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
        });
        userCounter++;
      }

      // Insert in batches of 500
      for (let i = 0; i < users.length; i += 500) {
        const batch = users.slice(i, i + 500);
        await prisma.user.createMany({ data: batch });
      }

      usersByRole[role] = await prisma.user.findMany({
        where: { role },
        take: count,
        orderBy: { createdAt: 'asc' }
      });

      console.log(`   ✅ Created ${usersByRole[role].length} ${role} users`);
    }

    stats.users = await prisma.user.count();
    console.log(`✅ Total Users: ${stats.users}\n`);

    // =====================================================
    // 2. GENERATE CLASSES (200)
    // =====================================================
    console.log('🏫 Generating 200 Classes...');

    const classNames = ['Nursery', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
    const sections = 'A B C D E F G H I J K L M N O P Q R S T'.split(' ');
    const academicYears = ['2024-2025', '2025-2026'];
    const classes = [];

    for (let i = 0; i < 200; i++) {
      const className = classNames[i % classNames.length];
      const section = sections[i % sections.length];
      const stream = parseInt(className) >= 11 ? randomChoice(['Science', 'Commerce', 'Arts']) : null;
      const classTeacherId = usersByRole.teacher.length > 0 ? randomChoice(usersByRole.teacher).id : null;

      classes.push({
        name: stream ? `${className}-${stream}` : className,
        section,
        sections: JSON.stringify(sections.slice(0, 10)),
        capacity: randomInt(40, 60),
        academicYear: randomChoice(academicYears),
        classTeacherId
      });
    }

    await prisma.class.createMany({ data: classes });
    const insertedClasses = await prisma.class.findMany({ orderBy: { createdAt: 'asc' } });
    stats.classes = insertedClasses.length;
    console.log(`✅ Classes: ${stats.classes}\n`);

    // =====================================================
    // 3. GENERATE CLASS SUBJECTS
    // =====================================================
    console.log('📚 Generating Class Subjects...');

    const classSubjects = [];
    for (const classDoc of insertedClasses) {
      const numSubjects = randomInt(4, 8);
      const selectedSubjects = subjects.slice(0, numSubjects);

      for (const subject of selectedSubjects) {
        classSubjects.push({
          name: subject,
          subject,
          periodsPerWeek: randomInt(3, 6),
          classId: classDoc.id,
          teacherId: usersByRole.teacher.length > 0 ? randomChoice(usersByRole.teacher).id : null
        });
      }
    }

    await prisma.classSubject.createMany({ data: classSubjects });
    stats.classSubjects = await prisma.classSubject.count();
    console.log(`✅ Class Subjects: ${stats.classSubjects}\n`);

    // =====================================================
    // 4. GENERATE STUDENTS (5,000)
    // =====================================================
    console.log('🎓 Generating 5,000 Students...');

    const students = [];
    const studentUsers = usersByRole.student.slice(0, 5000);

    for (let i = 0; i < 5000 && i < studentUsers.length; i++) {
      const studentUser = studentUsers[i];
      const classDoc = randomChoice(insertedClasses);
      const parentUser = usersByRole.parent.length > 0 ? randomChoice(usersByRole.parent) : null;

      students.push({
        userId: studentUser.id,
        name: studentUser.name,
        admissionNo: `ADM${String(i + 1).padStart(6, '0')}`,
        classId: classDoc.id,
        parentPhone: parentUser?.phone || generatePhone(),
        parentEmail: parentUser?.email || `parent${i}@test.com`,
        parentUserId: parentUser?.id || null,
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
        structuredAddress: JSON.stringify(generateAddress()),
        transportRequired: Math.random() > 0.6,
        hostelRequired: Math.random() > 0.8,
        medicalConditions: Math.random() > 0.9 ? randomChoice(['Asthma', 'Diabetes', 'Epilepsy', 'None']) : '',
        allergies: Math.random() > 0.9 ? randomChoice(['Peanuts', 'Dust', 'Pollens', 'None']) : '',
        admissionNotes: '',
        canteenBalance: randomFloat(100, 5000),
        rfidTagHex: `RFID${randomInt(100000, 999999).toString(16).toUpperCase()}`
      });
    }

    await prisma.student.createMany({ data: students });
    const insertedStudents = await prisma.student.findMany({ take: 5000, orderBy: { createdAt: 'asc' } });
    stats.students = insertedStudents.length;
    console.log(`✅ Students: ${stats.students}\n`);

    // =====================================================
    // 5. GENERATE ATTENDANCE (10,000)
    // =====================================================
    console.log('📅 Generating 10,000 Attendance Records...');

    const attendanceRecords = [];
    const statuses = ['present', 'absent', 'late', 'half-day'];
    const usedKeys = new Set();

    while (attendanceRecords.length < 10000 && insertedStudents.length > 0) {
      const student = randomChoice(insertedStudents);
      const date = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));
      const dateStr = date.toISOString().split('T')[0];
      const subject = randomChoice(subjects);
      const key = `${student.id}-${student.classId}-${dateStr}-${subject}`;

      if (usedKeys.has(key)) continue;
      usedKeys.add(key);

      attendanceRecords.push({
        studentId: student.id,
        classId: student.classId,
        teacherId: randomChoice(usersByRole.teacher).id,
        date,
        status: Math.random() > 0.25 ? 'present' : randomChoice(statuses),
        smsSent: Math.random() > 0.5,
        subject,
        remarks: Math.random() > 0.9 ? randomChoice(['Late arrival', 'Early departure', 'Medical leave', '']) : null
      });
    }

    await prisma.attendance.createMany({ data: attendanceRecords });
    stats.attendance = await prisma.attendance.count();
    console.log(`✅ Attendance Records: ${stats.attendance}\n`);

    // =====================================================
    // 6. GENERATE STAFF ATTENDANCE (10,000)
    // =====================================================
    console.log('👨‍💼 Generating 10,000 Staff Attendance Records...');

    const staffAttendanceRecords = [];
    const allStaff = [...usersByRole.teacher, ...usersByRole.staff];

    for (let i = 0; i < 10000 && allStaff.length > 0; i++) {
      const staff = randomChoice(allStaff);
      staffAttendanceRecords.push({
        staffId: staff.id,
        date: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        status: Math.random() > 0.2 ? 'present' : randomChoice(statuses),
        remarks: Math.random() > 0.9 ? randomChoice(['On leave', 'Late arrival', 'Half day', '']) : null
      });
    }

    await prisma.staffAttendance.createMany({ data: staffAttendanceRecords });
    stats.staffAttendance = await prisma.staffAttendance.count();
    console.log(`✅ Staff Attendance: ${stats.staffAttendance}\n`);

    // =====================================================
    // 7. GENERATE FEE STRUCTURES (5,000)
    // =====================================================
    console.log('💰 Generating 5,000 Fee Structures...');

    const feeStructures = [];

    for (let i = 0; i < 5000; i++) {
      const classDoc = randomChoice(insertedClasses);
      feeStructures.push({
        classId: classDoc.id,
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

    await prisma.feeStructure.createMany({ data: feeStructures });
    stats.feeStructures = await prisma.feeStructure.count();
    console.log(`✅ Fee Structures: ${stats.feeStructures}\n`);

    // =====================================================
    // 8. GENERATE FEE PAYMENTS (10,000)
    // =====================================================
    console.log('💳 Generating 10,000 Fee Payments...');

    const feePayments = [];
    const feeStructuresList = await prisma.feeStructure.findMany({ select: { id: true, feeType: true } });
    const collectorId = usersByRole.accounts.length > 0 ? usersByRole.accounts[0].id : (usersByRole.admin.length > 0 ? usersByRole.admin[0].id : null);

    for (let i = 0; i < 10000 && insertedStudents.length > 0 && collectorId; i++) {
      const student = randomChoice(insertedStudents);
      const feeStruct = feeStructuresList.length > 0 && Math.random() > 0.3 ? randomChoice(feeStructuresList) : null;

      feePayments.push({
        studentId: student.id,
        feeStructureId: feeStruct?.id || null,
        amountPaid: randomInt(500, 25000),
        originalAmount: randomInt(500, 25000),
        discount: randomInt(0, 2000),
        paymentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        receiptNo: `REC${Date.now() + i}`,
        collectedById: collectorId,
        paymentMode: randomChoice(paymentModes),
        remarks: Math.random() > 0.8 ? 'Partial payment' : null,
        feeType: feeStruct?.feeType || randomChoice(feeTypes),
        academicYear: randomChoice(academicYears)
      });
    }

    await prisma.feePayment.createMany({ data: feePayments });
    stats.feePayments = await prisma.feePayment.count();
    console.log(`✅ Fee Payments: ${stats.feePayments}\n`);

    // =====================================================
    // 9. GENERATE EXAMS (1,000)
    // =====================================================
    console.log('📝 Generating 1,000 Exams...');

    const exams = [];

    for (let i = 0; i < 1000; i++) {
      const classDoc = randomChoice(insertedClasses);
      const exam = {
        name: `${randomChoice(examTypes)} - ${randomChoice(subjects)}`,
        examType: randomChoice(examTypes),
        classId: classDoc.id,
        subject: randomChoice(subjects),
        date: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        time: `${randomInt(8, 14)}:${randomChoice(['00', '15', '30', '45'])}`,
        startTime: `${randomInt(8, 12)}:00`,
        endTime: `${randomInt(11, 15)}:00`,
        roomNumber: `Room ${randomInt(1, 50)}`,
        instructions: 'Answer all questions',
        totalMarks: randomChoice([50, 80, 100]),
        passingMarks: randomChoice([20, 30, 35, 40])
      };
      exams.push(exam);
    }

    await prisma.exam.createMany({ data: exams });
    const insertedExams = await prisma.exam.findMany({ orderBy: { createdAt: 'asc' } });
    stats.exams = insertedExams.length;
    console.log(`✅ Exams: ${stats.exams}\n`);

    // =====================================================
    // 10. GENERATE EXAM RESULTS (10,000)
    // =====================================================
    console.log('📊 Generating 10,000 Exam Results...');

    const examResults = [];

    for (let i = 0; i < 10000 && insertedExams.length > 0 && insertedStudents.length > 0; i++) {
      const exam = randomChoice(insertedExams);
      const student = randomChoice(insertedStudents);
      const totalMarks = exam.totalMarks || 100;
      const marksObtained = randomInt(Math.floor(totalMarks * 0.2), totalMarks);
      const percentage = (marksObtained / totalMarks) * 100;

      let grade = 'F';
      if (percentage >= 90) grade = 'A+';
      else if (percentage >= 80) grade = 'A';
      else if (percentage >= 70) grade = 'B+';
      else if (percentage >= 60) grade = 'B';
      else if (percentage >= 50) grade = 'C';
      else if (percentage >= 40) grade = 'D';

      examResults.push({
        examId: exam.id,
        studentId: student.id,
        marksObtained,
        totalMarks,
        grade,
        remarks: percentage >= 40 ? 'Pass' : 'Fail'
      });
    }

    await prisma.examResult.createMany({ data: examResults });
    stats.examResults = await prisma.examResult.count();
    console.log(`✅ Exam Results: ${stats.examResults}\n`);

    // =====================================================
    // 11. GENERATE HOMEWORK (5,000)
    // =====================================================
    console.log('📖 Generating 5,000 Homework Assignments...');

    const homeworks = [];

    for (let i = 0; i < 5000; i++) {
      const classDoc = randomChoice(insertedClasses);
      homeworks.push({
        classId: classDoc.id,
        teacherId: randomChoice(usersByRole.teacher).id,
        subject: randomChoice(subjects),
        title: `Chapter ${randomInt(1, 20)}: ${randomChoice(['Exercises', 'Problems', 'Questions', 'Activities', 'Practice'])}`,
        description: `Complete all questions from chapter ${randomInt(1, 20)}. Submit by due date.`,
        dueDate: randomDate(new Date(2025, 3, 1), new Date(2025, 11, 31))
      });
    }

    await prisma.homework.createMany({ data: homeworks });
    stats.homeworks = await prisma.homework.count();
    console.log(`✅ Homeworks: ${stats.homeworks}\n`);

    // =====================================================
    // 12. GENERATE NOTICES (3,000)
    // =====================================================
    console.log('📢 Generating 3,000 Notices...');

    const notices = [];
    const creatorId = usersByRole.admin.length > 0 ? usersByRole.admin[0].id : null;

    for (let i = 0; i < 3000; i++) {
      notices.push({
        title: `${randomChoice(['Important', 'Notice', 'Announcement', 'Update', 'Circular'])}: ${randomChoice(['Exam Schedule', 'Holiday', 'Event', 'Meeting', 'Fee Due', 'PTM', 'Sports Day', 'Cultural Program'])}`,
        content: `This is an important notice for all ${randomChoice(['students', 'parents', 'staff', 'teachers'])}. Please read carefully and follow the instructions.`,
        audience: JSON.stringify([randomChoice(['all', 'students', 'parents', 'teachers', 'staff'])]),
        createdById: creatorId || (usersByRole.teacher[0]?.id || null),
        relatedClassId: Math.random() > 0.7 ? randomChoice(insertedClasses).id : null,
        priority: randomChoice(noticePriorities),
        published: Math.random() > 0.1
      });
    }

    await prisma.notice.createMany({ data: notices });
    stats.notices = await prisma.notice.count();
    console.log(`✅ Notices: ${stats.notices}\n`);

    // =====================================================
    // 13. GENERATE REMARKS (5,000)
    // =====================================================
    console.log('💬 Generating 5,000 Remarks...');

    const remarks = [];

    for (let i = 0; i < 5000; i++) {
      const student = randomChoice(insertedStudents);
      remarks.push({
        studentId: student.id,
        teacherId: randomChoice(usersByRole.teacher).id,
        remark: randomChoice([
          'Good performance in class',
          'Needs improvement in homework',
          'Excellent participation',
          'Regular and punctual',
          'Should focus on studies',
          'Very attentive in class',
          'Needs to improve handwriting',
          'Good in sports',
          'Active in extracurricular activities',
          'Should submit homework on time'
        ])
      });
    }

    await prisma.remark.createMany({ data: remarks });
    stats.remarks = await prisma.remark.count();
    console.log(`✅ Remarks: ${stats.remarks}\n`);

    // =====================================================
    // 14. GENERATE COMPLAINTS (3,000)
    // =====================================================
    console.log('⚠️  Generating 3,000 Complaints...');

    const complaints = [];

    for (let i = 0; i < 3000; i++) {
      const user = randomChoice([...usersByRole.teacher, ...usersByRole.parent, ...usersByRole.student]);
      complaints.push({
        userId: user.id,
        targetUserId: Math.random() > 0.5 ? randomChoice([...usersByRole.teacher, ...usersByRole.staff]).id : null,
        type: randomChoice(complaintTypes),
        studentId: Math.random() > 0.5 ? randomChoice(insertedStudents).id : null,
        classId: Math.random() > 0.7 ? randomChoice(insertedClasses).id : null,
        subject: randomChoice(['Teacher Behavior', 'Infrastructure', 'Fee Issue', 'Transport Issue', 'Canteen Issue', 'Academic Concern', 'Safety Issue', 'General']),
        description: `This is a complaint regarding ${randomChoice(['teacher behavior', 'infrastructure', 'fee collection', 'transport facility', 'canteen quality', 'academic standards', 'safety measures'])}. Please take necessary action.`,
        status: randomChoice(complaintStatuses),
        raisedByRole: user.role,
        assignedToRole: randomChoice(['admin', 'principal', 'hr']),
        resolutionNote: Math.random() > 0.5 ? 'Action taken. Issue resolved.' : null
      });
    }

    await prisma.complaint.createMany({ data: complaints });
    stats.complaints = await prisma.complaint.count();
    console.log(`✅ Complaints: ${stats.complaints}\n`);

    // =====================================================
    // 15. GENERATE LEAVES (5,000)
    // =====================================================
    console.log('🏖️  Generating 5,000 Leave Requests...');

    const leaves = [];

    for (let i = 0; i < 5000; i++) {
      const staff = randomChoice([...usersByRole.teacher, ...usersByRole.staff]);
      const fromDate = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));
      const toDate = new Date(fromDate.getTime() + randomInt(1, 10) * 24 * 60 * 60 * 1000);

      leaves.push({
        staffId: staff.id,
        type: randomChoice(leaveTypes),
        fromDate,
        toDate,
        reason: randomChoice(['Medical reason', 'Personal work', 'Family function', 'Health issue', 'Emergency work', 'Festival']),
        status: randomChoice(['pending', 'approved', 'rejected']),
        reviewedById: Math.random() > 0.5 ? randomChoice(usersByRole.hr || []).id : null,
        reviewNote: Math.random() > 0.5 ? 'Leave approved' : null
      });
    }

    await prisma.leave.createMany({ data: leaves });
    stats.leaves = await prisma.leave.count();
    console.log(`✅ Leave Requests: ${stats.leaves}\n`);

    // =====================================================
    // 16. GENERATE LIBRARY BOOKS (2,000)
    // =====================================================
    console.log('📚 Generating 2,000 Library Books...');

    const libraryBooks = [];

    for (let i = 0; i < 2000; i++) {
      const title = randomChoice(bookTitles);
      const author = randomChoice(bookAuthors);
      const totalCopies = randomInt(1, 10);

      libraryBooks.push({
        isbn: `ISBN${randomInt(1000000000, 9999999999)}`,
        title: `${title} - Vol ${i + 1}`,
        author,
        totalCopies,
        availableCopies: randomInt(0, totalCopies)
      });
    }

    await prisma.libraryBook.createMany({ data: libraryBooks });
    const insertedBooks = await prisma.libraryBook.findMany({ orderBy: { createdAt: 'asc' } });
    stats.libraryBooks = insertedBooks.length;
    console.log(`✅ Library Books: ${stats.libraryBooks}\n`);

    // =====================================================
    // 17. GENERATE LIBRARY TRANSACTIONS (5,000)
    // =====================================================
    console.log('📖 Generating 5,000 Library Transactions...');

    const libraryTransactions = [];

    for (let i = 0; i < 5000; i++) {
      const student = randomChoice(insertedStudents);
      const book = randomChoice(insertedBooks);
      const issueDate = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));
      const dueDate = new Date(issueDate.getTime() + 14 * 24 * 60 * 60 * 1000);
      const isReturned = Math.random() > 0.4;
      const returnDate = isReturned ? new Date(issueDate.getTime() + randomInt(1, 20) * 24 * 60 * 60 * 1000) : null;

      libraryTransactions.push({
        studentId: student.id,
        bookId: book.id,
        issueDate,
        dueDate,
        returnDate,
        status: isReturned ? 'RETURNED' : 'BORROWED',
        fineAmount: isReturned && returnDate > dueDate ? randomInt(10, 100) : 0,
        remarks: isReturned ? 'Returned on time' : 'Not yet returned'
      });
    }

    await prisma.libraryTransaction.createMany({ data: libraryTransactions });
    stats.libraryTransactions = await prisma.libraryTransaction.count();
    console.log(`✅ Library Transactions: ${stats.libraryTransactions}\n`);

    // =====================================================
    // 18. GENERATE TRANSPORT VEHICLES (200)
    // =====================================================
    console.log('🚌 Generating 200 Transport Vehicles...');

    const transportVehicles = [];

    for (let i = 0; i < 200; i++) {
      transportVehicles.push({
        busNumber: `BUS${String(i + 1).padStart(3, '0')}`,
        numberPlate: `AS${randomInt(1, 99)} ${randomChoice(['A', 'B', 'C'])} ${randomInt(1000, 9999)}`,
        driverId: usersByRole.driver.length > 0 ? randomChoice(usersByRole.driver).id : null,
        conductorId: usersByRole.conductor.length > 0 ? randomChoice(usersByRole.conductor).id : null,
        route: randomChoice(routeNames),
        capacity: randomInt(30, 60)
      });
    }

    await prisma.transportVehicle.createMany({ data: transportVehicles });
    const insertedVehicles = await prisma.transportVehicle.findMany({ orderBy: { createdAt: 'asc' } });
    stats.transportVehicles = insertedVehicles.length;
    console.log(`✅ Transport Vehicles: ${stats.transportVehicles}\n`);

    // =====================================================
    // 19. GENERATE BUS ROUTES (100)
    // =====================================================
    console.log('🛣️  Generating 100 Bus Routes...');

    const busRoutes = [];

    for (let i = 0; i < 100; i++) {
      busRoutes.push({
        routeName: randomChoice(routeNames),
        routeCode: `ROUTE${String(i + 1).padStart(3, '0')}`,
        routeNumber: `R${i + 1}`,
        vehicleId: insertedVehicles.length > 0 ? randomChoice(insertedVehicles).id : null,
        driverId: usersByRole.driver.length > 0 ? randomChoice(usersByRole.driver).id : null,
        conductorId: usersByRole.conductor.length > 0 ? randomChoice(usersByRole.conductor).id : null,
        totalDistance: randomFloat(10, 100),
        totalDuration: randomFloat(30, 180),
        departureTime: `${randomInt(6, 9)}:${randomChoice(['00', '15', '30', '45'])}`,
        returnTime: `${randomInt(14, 17)}:${randomChoice(['00', '15', '30', '45'])}`,
        vehicleType: randomChoice(vehicleTypes),
        capacity: randomInt(40, 60),
        activeStudents: randomInt(10, 50),
        feePerStudent: randomInt(500, 3000),
        totalCollection: randomInt(10000, 150000),
        isActive: Math.random() > 0.1,
        description: `Bus route ${i + 1}`,
        notes: ''
      });
    }

    await prisma.busRoute.createMany({ data: busRoutes });
    const insertedRoutes = await prisma.busRoute.findMany({ orderBy: { createdAt: 'asc' } });
    stats.busRoutes = insertedRoutes.length;
    console.log(`✅ Bus Routes: ${stats.busRoutes}\n`);

    // =====================================================
    // 20. GENERATE BUS STOPS (500)
    // =====================================================
    console.log('🚏 Generating 500 Bus Stops...');

    const busStops = [];

    for (let i = 0; i < 500; i++) {
      const route = randomChoice(insertedRoutes);
      busStops.push({
        routeId: route.id,
        stopName: `${randomChoice(['Main', 'Central', 'City', 'Market', 'Station'])} ${randomChoice(['Stop', 'Junction', 'Point', 'Corner'])} ${i + 1}`,
        stopCode: `STOP${String(i + 1).padStart(3, '0')}`,
        sequence: randomInt(1, 20),
        arrivalTime: `${randomInt(6, 16)}:${randomChoice(['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'])}`,
        departureTime: `${randomInt(6, 16)}:${randomChoice(['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'])}`,
        distance: randomFloat(1, 20),
        latitude: randomFloat(26.0, 26.5),
        longitude: randomFloat(91.5, 92.5),
        landmark: randomChoice(['Near Temple', 'Opposite Market', 'Besides Park', 'Near Station']),
        isPickup: Math.random() > 0.2,
        isDrop: Math.random() > 0.2
      });
    }

    await prisma.busStop.createMany({ data: busStops });
    stats.busStops = await prisma.busStop.count();
    console.log(`✅ Bus Stops: ${stats.busStops}\n`);

    // =====================================================
    // 21. GENERATE TRANSPORT ATTENDANCE (10,000)
    // =====================================================
    console.log('🚌 Generating 10,000 Transport Attendance Records...');

    const transportAttendance = [];

    for (let i = 0; i < 10000; i++) {
      const vehicle = randomChoice(insertedVehicles);
      const student = randomChoice(insertedStudents);
      const date = randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31));

      transportAttendance.push({
        busId: vehicle.id,
        studentId: student.id,
        date: date.toISOString().split('T')[0],
        status: Math.random() > 0.15 ? 'present' : 'absent',
        markedById: usersByRole.conductor.length > 0 ? randomChoice(usersByRole.conductor).id : null
      });
    }

    await prisma.transportAttendance.createMany({ data: transportAttendance });
    stats.transportAttendance = await prisma.transportAttendance.count();
    console.log(`✅ Transport Attendance: ${stats.transportAttendance}\n`);

    // =====================================================
    // 22. GENERATE CANTEEN ITEMS (200)
    // =====================================================
    console.log('🍔 Generating 200 Canteen Items...');

    const canteenItems = [];

    for (let i = 0; i < 200; i++) {
      canteenItems.push({
        name: `${randomChoice(canteenNames)} ${i + 1}`,
        price: randomFloat(10, 200),
        quantityAvailable: randomInt(0, 500),
        category: randomChoice(['Snacks', 'Meals', 'Beverages', 'Desserts', 'Fast Food']),
        isAvailable: Math.random() > 0.2
      });
    }

    await prisma.canteenItem.createMany({ data: canteenItems });
    const insertedCanteenItems = await prisma.canteenItem.findMany({ orderBy: { createdAt: 'asc' } });
    stats.canteenItems = insertedCanteenItems.length;
    console.log(`✅ Canteen Items: ${stats.canteenItems}\n`);

    // =====================================================
    // 23. GENERATE CANTEEN SALES (5,000)
    // =====================================================
    console.log('💵 Generating 5,000 Canteen Sales...');

    const canteenSales = [];
    const canteenSaleItems = [];
    const soldById = usersByRole.canteen.length > 0 ? usersByRole.canteen[0].id : null;

    for (let i = 0; i < 5000; i++) {
      const total = randomFloat(50, 500);
      canteenSales.push({
        total,
        paymentMode: randomChoice(['Cash', 'RFID', 'UPI']),
        soldTo: `Student ${i + 1}`,
        soldById,
        date: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31))
      });
    }

    await prisma.canteenSale.createMany({ data: canteenSales });
    const insertedCanteenSales = await prisma.canteenSale.findMany({ orderBy: { createdAt: 'asc' } });
    stats.canteenSales = insertedCanteenSales.length;
    console.log(`✅ Canteen Sales: ${stats.canteenSales}\n`);

    // Generate sale items
    for (const sale of insertedCanteenSales.slice(0, 5000)) {
      const numItems = randomInt(1, 5);
      for (let j = 0; j < numItems; j++) {
        const item = randomChoice(insertedCanteenItems);
        canteenSaleItems.push({
          saleId: sale.id,
          itemId: item.id,
          quantity: randomInt(1, 5),
          price: item.price
        });
      }
    }

    await prisma.canteenSaleItem.createMany({ data: canteenSaleItems.slice(0, 15000) });
    stats.canteenSaleItems = await prisma.canteenSaleItem.count();
    console.log(`✅ Canteen Sale Items: ${stats.canteenSaleItems}\n`);

    // =====================================================
    // 24. GENERATE HOSTEL ROOM TYPES (20)
    // =====================================================
    console.log('🏨 Generating 20 Hostel Room Types...');

    const hostelRoomTypes = [];

    for (let i = 0; i < 20; i++) {
      hostelRoomTypes.push({
        name: randomChoice(hostelRoomNames),
        code: `RT${String(i + 1).padStart(2, '0')}`,
        occupancy: randomInt(1, 6),
        genderPolicy: randomChoice(['male', 'female', 'mixed']),
        defaultFee: randomInt(10000, 50000),
        amenities: JSON.stringify(['WiFi', 'AC', 'Attached Bathroom', 'Study Table']),
        description: `Hostel room type ${i + 1}`
      });
    }

    await prisma.hostelRoomType.createMany({ data: hostelRoomTypes });
    const insertedRoomTypes = await prisma.hostelRoomType.findMany({ orderBy: { createdAt: 'asc' } });
    stats.hostelRoomTypes = insertedRoomTypes.length;
    console.log(`✅ Hostel Room Types: ${stats.hostelRoomTypes}\n`);

    // =====================================================
    // 25. GENERATE HOSTEL ROOMS (200)
    // =====================================================
    console.log('🚪 Generating 200 Hostel Rooms...');

    const hostelRooms = [];

    for (let i = 0; i < 200; i++) {
      const roomType = randomChoice(insertedRoomTypes);
      hostelRooms.push({
        roomTypeId: roomType.id,
        roomNumber: `H${randomInt(1, 10)}-${String(i + 1).padStart(3, '0')}`,
        block: randomChoice(['A', 'B', 'C', 'D']),
        floor: randomChoice(['Ground', 'First', 'Second', 'Third']),
        capacity: roomType.occupancy,
        occupiedBeds: randomInt(0, roomType.occupancy),
        status: randomChoice(['AVAILABLE', 'OCCUPIED', 'MAINTENANCE']),
        notes: ''
      });
    }

    await prisma.hostelRoom.createMany({ data: hostelRooms });
    const insertedHostelRooms = await prisma.hostelRoom.findMany({ orderBy: { createdAt: 'asc' } });
    stats.hostelRooms = insertedHostelRooms.length;
    console.log(`✅ Hostel Rooms: ${stats.hostelRooms}\n`);

    // =====================================================
    // 26. GENERATE HOSTEL FEE STRUCTURES (100)
    // =====================================================
    console.log('💰 Generating 100 Hostel Fee Structures...');

    const hostelFeeStructures = [];

    for (let i = 0; i < 100; i++) {
      const roomType = randomChoice(insertedRoomTypes);
      hostelFeeStructures.push({
        roomTypeId: roomType.id,
        academicYear: randomChoice(academicYears),
        term: randomChoice(['Annual', 'Quarterly', 'Monthly', 'Half-Yearly']),
        billingCycle: randomChoice(['monthly', 'quarterly', 'annual']),
        amount: randomInt(10000, 80000),
        cautionDeposit: randomInt(5000, 20000),
        messCharge: randomInt(3000, 8000),
        maintenanceCharge: randomInt(1000, 5000),
        notes: ''
      });
    }

    await prisma.hostelFeeStructure.createMany({ data: hostelFeeStructures });
    stats.hostelFeeStructures = await prisma.hostelFeeStructure.count();
    console.log(`✅ Hostel Fee Structures: ${stats.hostelFeeStructures}\n`);

    // =====================================================
    // 27. GENERATE HOSTEL ALLOCATIONS (1,000)
    // =====================================================
    console.log('🛏️  Generating 1,000 Hostel Allocations...');

    const hostelAllocations = [];

    for (let i = 0; i < 1000; i++) {
      const student = randomChoice(insertedStudents.filter(s => s.hostelRequired === true));
      if (!student) continue;

      const room = randomChoice(insertedHostelRooms);
      const roomType = insertedRoomTypes.find(rt => rt.id === room.roomTypeId);

      hostelAllocations.push({
        studentId: student.id,
        roomTypeId: room.roomTypeId,
        roomId: room.id,
        feeStructureId: Math.random() > 0.5 ? (await prisma.hostelFeeStructure.findFirst())?.id || null : null,
        academicYear: randomChoice(academicYears),
        bedLabel: `Bed ${randomInt(1, room.capacity)}`,
        allotmentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        status: randomChoice(['ACTIVE', 'VACATED', 'PENDING']),
        guardianContactName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
        guardianContactPhone: generatePhone(),
        notes: ''
      });
    }

    await prisma.hostelAllocation.createMany({ data: hostelAllocations });
    stats.hostelAllocations = await prisma.hostelAllocation.count();
    console.log(`✅ Hostel Allocations: ${stats.hostelAllocations}\n`);

    // =====================================================
    // 28. GENERATE SALARY STRUCTURES (3,000)
    // =====================================================
    console.log('💼 Generating 3,000 Salary Structures...');

    const salaryStructures = [];
    const allStaffForSalary = [...usersByRole.teacher, ...usersByRole.staff, ...usersByRole.driver, ...usersByRole.conductor];

    for (let i = 0; i < 3000; i++) {
      const staff = randomChoice(allStaffForSalary);
      salaryStructures.push({
        staffId: staff.id,
        basicSalary: randomInt(15000, 80000),
        hra: randomInt(5000, 20000),
        da: randomInt(2000, 10000),
        conveyance: randomInt(1000, 5000),
        medicalAllowance: randomInt(1000, 3000),
        specialAllowance: randomInt(1000, 5000),
        pfDeduction: randomInt(1000, 5000),
        taxDeduction: randomInt(500, 3000),
        otherDeductions: randomInt(0, 2000),
        effectiveFrom: randomDate(new Date(2024, 0, 1), new Date(2025, 11, 31))
      });
    }

    await prisma.salaryStructure.createMany({ data: salaryStructures });
    stats.salaryStructures = await prisma.salaryStructure.count();
    console.log(`✅ Salary Structures: ${stats.salaryStructures}\n`);

    // =====================================================
    // 29. GENERATE PAYROLL (5,000)
    // =====================================================
    console.log('💳 Generating 5,000 Payroll Records...');

    const payrolls = [];
    const allStaffForPayroll = [...usersByRole.teacher, ...usersByRole.staff, ...usersByRole.driver, ...usersByRole.conductor];

    for (let i = 0; i < 5000; i++) {
      const staff = randomChoice(allStaffForPayroll);
      const basicSalary = randomInt(15000, 80000);
      const hra = randomInt(5000, 20000);
      const da = randomInt(2000, 10000);
      const totalEarnings = basicSalary + hra + da + randomInt(1000, 5000);
      const pfDeduction = randomInt(1000, 5000);
      const taxDeduction = randomInt(500, 3000);
      const totalDeductions = pfDeduction + taxDeduction + randomInt(0, 2000);

      payrolls.push({
        staffId: staff.id,
        month: randomInt(1, 12),
        year: randomInt(2024, 2026),
        basicSalary,
        hra,
        da,
        conveyance: randomInt(1000, 5000),
        medicalAllowance: randomInt(1000, 3000),
        specialAllowance: randomInt(1000, 5000),
        totalEarnings,
        pfDeduction,
        taxDeduction,
        otherDeductions: randomInt(0, 2000),
        totalDeductions,
        netPay: totalEarnings - totalDeductions,
        generatedDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        isPaid: Math.random() > 0.3,
        paidOn: Math.random() > 0.3 ? randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)) : null
      });
    }

    await prisma.payroll.createMany({ data: payrolls });
    stats.payrolls = await prisma.payroll.count();
    console.log(`✅ Payroll Records: ${stats.payrolls}\n`);

    // =====================================================
    // 30. GENERATE ROUTINES (200)
    // =====================================================
    console.log('📅 Generating 200 Class Routines...');

    const routines = [];

    for (let i = 0; i < 200; i++) {
      const classDoc = randomChoice(insertedClasses);
      const timetable = {
        Monday: subjects.slice(0, 6).map(sub => ({ subject: sub, time: `${randomInt(8, 14)}:00` })),
        Tuesday: subjects.slice(0, 6).map(sub => ({ subject: sub, time: `${randomInt(8, 14)}:00` })),
        Wednesday: subjects.slice(0, 6).map(sub => ({ subject: sub, time: `${randomInt(8, 14)}:00` })),
        Thursday: subjects.slice(0, 6).map(sub => ({ subject: sub, time: `${randomInt(8, 14)}:00` })),
        Friday: subjects.slice(0, 6).map(sub => ({ subject: sub, time: `${randomInt(8, 14)}:00` })),
        Saturday: subjects.slice(0, 4).map(sub => ({ subject: sub, time: `${randomInt(8, 12)}:00` }))
      };

      routines.push({
        classId: classDoc.id,
        timetable: JSON.stringify(timetable)
      });
    }

    await prisma.routine.createMany({ data: routines });
    stats.routines = await prisma.routine.count();
    console.log(`✅ Routines: ${stats.routines}\n`);

    // =====================================================
    // FINAL STATISTICS
    // =====================================================
    const endTime = Date.now();
    const duration = ((endTime - startTime) / 1000).toFixed(2);

    stats.total = Object.values(stats).reduce((sum, val) => sum + val, 0);

    console.log('\n' + '='.repeat(70));
    console.log('✅ COMPREHENSIVE TEST DATA GENERATION COMPLETE!');
    console.log('='.repeat(70));
    console.log(`\n⏱️  Time taken: ${duration} seconds\n`);
    console.log('📊 STATISTICS:');
    console.log('-'.repeat(70));
    console.log(`👥 Users:                  ${stats.users?.toLocaleString() || 0}`);
    console.log(`🏫 Classes:                ${stats.classes?.toLocaleString() || 0}`);
    console.log(`📚 Class Subjects:         ${stats.classSubjects?.toLocaleString() || 0}`);
    console.log(`🎓 Students:               ${stats.students?.toLocaleString() || 0}`);
    console.log(`📅 Attendance:             ${stats.attendance?.toLocaleString() || 0}`);
    console.log(`👨‍💼 Staff Attendance:       ${stats.staffAttendance?.toLocaleString() || 0}`);
    console.log(`💰 Fee Structures:         ${stats.feeStructures?.toLocaleString() || 0}`);
    console.log(`💳 Fee Payments:           ${stats.feePayments?.toLocaleString() || 0}`);
    console.log(`📝 Exams:                  ${stats.exams?.toLocaleString() || 0}`);
    console.log(`📊 Exam Results:           ${stats.examResults?.toLocaleString() || 0}`);
    console.log(`📖 Homeworks:              ${stats.homeworks?.toLocaleString() || 0}`);
    console.log(`📢 Notices:                ${stats.notices?.toLocaleString() || 0}`);
    console.log(`💬 Remarks:                ${stats.remarks?.toLocaleString() || 0}`);
    console.log(`⚠️  Complaints:             ${stats.complaints?.toLocaleString() || 0}`);
    console.log(`🏖️  Leave Requests:         ${stats.leaves?.toLocaleString() || 0}`);
    console.log(`📚 Library Books:          ${stats.libraryBooks?.toLocaleString() || 0}`);
    console.log(`📖 Library Transactions:   ${stats.libraryTransactions?.toLocaleString() || 0}`);
    console.log(`🚌 Transport Vehicles:     ${stats.transportVehicles?.toLocaleString() || 0}`);
    console.log(`🛣️  Bus Routes:             ${stats.busRoutes?.toLocaleString() || 0}`);
    console.log(`🚏 Bus Stops:              ${stats.busStops?.toLocaleString() || 0}`);
    console.log(`🚌 Transport Attendance:   ${stats.transportAttendance?.toLocaleString() || 0}`);
    console.log(`🍔 Canteen Items:          ${stats.canteenItems?.toLocaleString() || 0}`);
    console.log(`💵 Canteen Sales:          ${stats.canteenSales?.toLocaleString() || 0}`);
    console.log(`🧾 Canteen Sale Items:     ${stats.canteenSaleItems?.toLocaleString() || 0}`);
    console.log(`🏨 Hostel Room Types:      ${stats.hostelRoomTypes?.toLocaleString() || 0}`);
    console.log(`🚪 Hostel Rooms:           ${stats.hostelRooms?.toLocaleString() || 0}`);
    console.log(`💰 Hostel Fee Structures:  ${stats.hostelFeeStructures?.toLocaleString() || 0}`);
    console.log(`🛏️  Hostel Allocations:     ${stats.hostelAllocations?.toLocaleString() || 0}`);
    console.log(`💼 Salary Structures:      ${stats.salaryStructures?.toLocaleString() || 0}`);
    console.log(`💳 Payroll Records:        ${stats.payrolls?.toLocaleString() || 0}`);
    console.log(`📅 Routines:               ${stats.routines?.toLocaleString() || 0}`);
    console.log('-'.repeat(70));
    console.log(`📦 TOTAL RECORDS:          ${stats.total.toLocaleString()}`);
    console.log('='.repeat(70));
    console.log('\n🎉 Test data generation completed successfully!');
    console.log('\n📝 Login Credentials:');
    console.log('   - Password for all users: test123');
    console.log('   - Superadmin: admin@school.com / admin123');
    console.log('\n🔐 Test accounts created for all roles:');
    Object.keys(usersByRole).forEach(role => {
      if (usersByRole[role].length > 0) {
        console.log(`   - ${role}: ${usersByRole[role][0].email}`);
      }
    });

    return stats;

  } catch (error) {
    console.error('\n❌ Error generating test data:', error);
    throw error;
  } finally {
    await prisma.$disconnect();
  }
}

// Run the seed function
seedTestData()
  .then(() => {
    console.log('\n✅ Database seeded successfully!');
    process.exit(0);
  })
  .catch((error) => {
    console.error('\n❌ Failed to seed database:', error);
    process.exit(1);
  });
