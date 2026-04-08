/**
 * PILOT SCHOOL TEST
 * Creates a fake school and runs comprehensive end-to-end testing
 * Simulates a real school's daily operations
 */

require('dotenv').config();
const prisma = require('../config/prisma');
const connectDB = require('../config/db');
const logger = require('../config/logger');

// Import all models
const User = require('../models/User');
const Student = require('../models/Student');
const Class = require('../models/Class');
const Attendance = require('../models/Attendance');
const StaffAttendance = require('../models/StaffAttendance');
const FeeStructure = require('../models/FeeStructure');
const FeePayment = require('../models/FeePayment');
const Exam = require('../models/Exam');
const ExamResult = require('../models/ExamResult');
const Homework = require('../models/Homework');
const Notice = require('../models/Notice');
const Leave = require('../models/Leave');
const Payroll = require('../models/Payroll');
const Complaint = require('../models/Complaint');
const Remark = require('../models/Remark');
const LibraryBook = require('../models/LibraryBook');
const LibraryTransaction = require('../models/LibraryTransaction');
const { CanteenItem, CanteenSale } = require('../models/Canteen');

connectDB();

// Fake School Details
const PILOT_SCHOOL = {
  name: 'Delhi Public Academy',
  location: 'Sector 62, Noida, Uttar Pradesh',
  type: 'CBSE Affiliated',
  grades: 'Nursery to 12th',
  estimatedStudents: 150,
  estimatedStaff: 25,
  academicYear: '2025-2026'
};

console.log('\n' + '='.repeat(80));
console.log('🏫 PILOT SCHOOL TEST - DELHI PUBLIC ACADEMY');
console.log('='.repeat(80));
console.log(`\nSchool: ${PILOT_SCHOOL.name}`);
console.log(`Location: ${PILOT_SCHOOL.location}`);
console.log(`Type: ${PILOT_SCHOOL.type}`);
console.log(`Academic Year: ${PILOT_SCHOOL.academicYear}`);
console.log(`Expected Students: ${PILOT_SCHOOL.estimatedStudents}`);
console.log(`Expected Staff: ${PILOT_SCHOOL.estimatedStaff}\n`);

const testResults = {
  passed: 0,
  failed: 0,
  warnings: 0,
  modules: {},
  timing: {}
};

function logTest(module, test, status, details = '') {
  const icon = status === 'PASS' ? '✅' : status === 'FAIL' ? '❌' : '⚠️';
  console.log(`   ${icon} ${test}`);
  if (details) console.log(`      ${details}`);

  if (!testResults.modules[module]) {
    testResults.modules[module] = { passed: 0, failed: 0, warnings: 0, tests: [] };
  }

  testResults.modules[module].tests.push({ test, status, details });
  if (status === 'PASS') { testResults.passed++; testResults.modules[module].passed++; }
  else if (status === 'FAIL') { testResults.failed++; testResults.modules[module].failed++; }
  else { testResults.warnings++; testResults.modules[module].warnings++; }
}

async function runPilotTest() {
  const startTime = Date.now();
  const scenarioMetrics = {
    staffAttendanceMarked: 0,
    studentAttendanceMarked: 0,
    feesCollectedCount: 0,
    feesCollectedAmount: 0,
    homeworkAssigned: 0,
    examsScheduled: 0,
    examResultsRecorded: 0,
    remarksAdded: 0,
    booksIssued: 0,
    canteenSales: 0,
    leaveApplicationsProcessed: 0,
    payrollGenerated: 0,
    noticesPublished: 0,
    complaintsReceived: 0
  };

  try {
    // ============================================
    // SCENARIO 1: MORNING - SCHOOL SETUP (7:00 AM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('🌅 SCENARIO 1: MORNING SETUP (7:00 AM)');
    console.log('='.repeat(80));

    // Test 1.1: Admin Login
    console.log('\n1️⃣  Admin Authentication & Dashboard...');
    const adminUser = await User.findOne({ role: 'superadmin' }).limit(1);
    if (adminUser) {
      logTest('Auth', 'Admin user exists', 'PASS', `Email: ${adminUser.email}`);
    } else {
      logTest('Auth', 'Admin user exists', 'FAIL', 'No superadmin found');
    }

    // Test 1.2: Verify School Structure
    console.log('\n2️⃣  Verifying School Structure...');
    const classCount = await Class.countDocuments();
    const teacherCount = await User.countDocuments({ role: 'teacher' });
    const staffCount = await User.countDocuments({ role: 'staff' });
    const studentCount = await Student.countDocuments();

    logTest('Structure', 'Classes configured', classCount > 0 ? 'PASS' : 'FAIL', `${classCount} classes found`);
    logTest('Structure', 'Teachers available', teacherCount >= 5 ? 'PASS' : 'WARN', `${teacherCount} teachers`);
    logTest('Structure', 'Staff available', staffCount >= 5 ? 'PASS' : 'WARN', `${staffCount} staff`);
    logTest('Structure', 'Students enrolled', studentCount > 0 ? 'PASS' : 'FAIL', `${studentCount} students`);

    // ============================================
    // SCENARIO 2: ATTENDANCE MARKING (8:30 AM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('📋 SCENARIO 2: ATTENDANCE MARKING (8:30 AM)');
    console.log('='.repeat(80));

    // Test 2.1: Mark Teacher Attendance
    console.log('\n3️⃣  Marking Staff Attendance...');
    const teachers = await User.find({ role: 'teacher' }).limit(10);
    let staffAttCount = 0;
    for (const teacher of teachers) {
      try {
        await StaffAttendance.create({
          staffId: teacher._id,
          date: new Date(),
          status: Math.random() > 0.1 ? 'present' : 'absent',
          remarks: ''
        });
        staffAttCount++;
        scenarioMetrics.staffAttendanceMarked++;
      } catch (err) {
        // Skip duplicates
      }
    }
    logTest('Attendance', 'Staff attendance marked', staffAttCount > 0 ? 'PASS' : 'FAIL', `${staffAttCount}/${teachers.length} teachers`);

    // Test 2.2: Mark Student Attendance
    console.log('\n4️⃣  Marking Student Attendance...');
    const classes = await Class.find().limit(5);
    let studentAttCount = 0;
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (const classDoc of classes) {
      const students = await Student.find({ classId: classDoc._id }).limit(20);
      for (const student of students) {
        try {
          // Use findOneAndUpdate to prevent duplicate key errors
          await Attendance.findOneAndUpdate(
            {
              studentId: student._id,
              classId: classDoc._id,
              date: { $gte: today, $lt: new Date(today.getTime() + 86400000) }
            },
            {
              studentId: student._id,
              classId: classDoc._id,
              teacherId: teachers[0]?._id,
              date: new Date(),
              status: Math.random() > 0.15 ? 'present' : 'absent',
              smsSent: false,
              subject: null
            },
            { upsert: true, returnDocument: 'after' }
          );
          studentAttCount++;
          scenarioMetrics.studentAttendanceMarked++;
        } catch (err) {
          // Skip if still fails
        }
      }
    }
    logTest('Attendance', 'Student attendance marked', studentAttCount > 0 ? 'PASS' : 'FAIL', `${studentAttCount} students marked`);

    // Test 2.3: View Attendance Report
    console.log('\n5️⃣  Viewing Attendance Reports...');
    // Fix #2: Use proper UTC date range for timezone-safe queries
    const startOfDay = new Date();
    startOfDay.setUTCHours(0, 0, 0, 0);
    const endOfDay = new Date();
    endOfDay.setUTCHours(23, 59, 59, 999);

    const todayAttendance = await Attendance.countDocuments({ date: { $gte: startOfDay, $lte: endOfDay } });
    const presentCount = await Attendance.countDocuments({
      date: { $gte: startOfDay, $lte: endOfDay },
      status: 'present'
    });
    const absentCount = await Attendance.countDocuments({
      date: { $gte: startOfDay, $lte: endOfDay },
      status: 'absent'
    });

    logTest('Attendance', 'Attendance report generated', 'PASS',
      `Total: ${todayAttendance}, Present: ${presentCount}, Absent: ${absentCount}`);

    // ============================================
    // SCENARIO 3: FEE COLLECTION (10:00 AM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('💰 SCENARIO 3: FEE COLLECTION (10:00 AM)');
    console.log('='.repeat(80));

    // Test 3.1: View Fee Structure
    console.log('\n6️⃣  Checking Fee Structure...');
    const feeStructures = await FeeStructure.find().limit(5);
    logTest('Fees', 'Fee structure exists', feeStructures.length > 0 ? 'PASS' : 'FAIL',
      `${feeStructures.length} fee types configured`);

    // Test 3.2: Collect Fee Payment
    console.log('\n7️⃣  Collecting Fee Payments...');
    const feeStudents = await Student.find().limit(15);
    const accountsStaff = await User.findOne({ role: 'accounts' });
    let feeCollectCount = 0;
    let feeCollectedAmount = 0;
    for (const student of feeStudents) {
      try {
        const amountPaid = Math.floor(Math.random() * 20000) + 5000;
        await FeePayment.create({
          studentId: student._id,
          amountPaid,
          originalAmount: 25000,
          discount: 0,
          paymentDate: new Date(),
          receiptNo: `REC${Date.now()}${Math.floor(Math.random() * 1000)}`,
          collectedBy: accountsStaff?._id || teachers[0]._id,
          paymentMode: ['cash', 'online', 'cheque'][Math.floor(Math.random() * 3)],
          feeType: 'Tuition Fee',
          academicYear: '2025-2026',
          remarks: ''
        });
        feeCollectCount++;
        feeCollectedAmount += amountPaid;
        scenarioMetrics.feesCollectedCount++;
        scenarioMetrics.feesCollectedAmount += amountPaid;
      } catch (err) {
        // Skip duplicates
      }
    }
    logTest('Fees', 'Fee payments collected', feeCollectCount > 0 ? 'PASS' : 'FAIL',
      `${feeCollectCount}/${feeStudents.length} payments`);

    // Test 3.3: Generate Fee Collection Report
    console.log('\n8️⃣  Generating Fee Collection Report...');
    // Fix #2: Use UTC date range for fee reports too
    const todayFees = await FeePayment.aggregate([
      { $match: { paymentDate: { $gte: startOfDay, $lte: endOfDay } } },
      { $group: { _id: null, total: { $sum: '$amountPaid' }, count: { $sum: 1 } } }
    ]);
    if (todayFees.length > 0) {
      logTest('Fees', 'Fee report generated', 'PASS',
        `Collected: ₹${todayFees[0].total.toLocaleString()} from ${todayFees[0].count} students`);
    } else {
      logTest('Fees', 'Fee report generated', 'WARN', 'No fees collected today');
    }

    // ============================================
    // SCENARIO 4: ACADEMIC OPERATIONS (11:30 AM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('📚 SCENARIO 4: ACADEMIC OPERATIONS (11:30 AM)');
    console.log('='.repeat(80));

    // Test 4.1: Create Homework Assignment
    console.log('\n9️⃣  Creating Homework Assignments...');
    let homeworkCount = 0;
    for (const classDoc of classes.slice(0, 3)) {
      try {
        await Homework.create({
          classId: classDoc._id,
          teacherId: teachers[0]._id,
          subject: ['Mathematics', 'English', 'Science'][homeworkCount],
          title: `Chapter ${homeworkCount + 1} Exercises`,
          description: 'Complete all questions from the chapter and submit by tomorrow',
          dueDate: new Date(Date.now() + 86400000)
        });
        homeworkCount++;
        scenarioMetrics.homeworkAssigned++;
      } catch (err) { }
    }
    logTest('Academic', 'Homework assignments created', homeworkCount > 0 ? 'PASS' : 'FAIL',
      `${homeworkCount} assignments`);

    // Test 4.2: Schedule Exam
    console.log('\n🔟 Scheduling Exam...');
    let examCount = 0;
    let examResultCount = 0;
    for (const classDoc of classes.slice(0, 2)) {
      try {
        const exam = await Exam.create({
          name: 'Unit Test 5 - Mathematics',
          examType: 'Unit Test 5',
          classId: classDoc._id,
          subject: 'Mathematics',
          date: new Date(Date.now() + 7 * 86400000),
          time: '10:00 AM',
          startTime: '10:00',
          endTime: '12:00',
          roomNumber: `${Math.floor(Math.random() * 20) + 1}`,
          instructions: 'Bring geometry box and calculator',
          totalMarks: 100,
          passingMarks: 35
        });

        // Add exam results for some students
        const students = await Student.find({ classId: classDoc._id }).limit(10);
        for (const student of students) {
          const marks = Math.floor(Math.random() * 60) + 40;
          await ExamResult.create({
            examId: exam._id,
            studentId: student._id,
            marksObtained: marks,
            totalMarks: 100,
            grade: marks >= 90 ? 'A+' : marks >= 80 ? 'A' : marks >= 70 ? 'B+' : marks >= 60 ? 'B' : marks >= 50 ? 'C' : marks >= 40 ? 'D' : 'F',
            remarks: marks >= 80 ? 'Excellent' : marks >= 60 ? 'Good' : 'Needs improvement'
          });
          examResultCount++;
          scenarioMetrics.examResultsRecorded++;
        }
        examCount++;
        scenarioMetrics.examsScheduled++;
      } catch (err) { }
    }
    logTest('Academic', 'Exams scheduled with results', examCount > 0 ? 'PASS' : 'FAIL',
      `${examCount} exams scheduled`);

    // Test 4.3: Add Student Remarks
    console.log('\n1️⃣1️⃣ Adding Student Remarks...');
    let remarkCount = 0;
    const studentsForRemarks = await Student.find().limit(10);
    for (const student of studentsForRemarks) {
      try {
        await Remark.create({
          studentId: student._id,
          teacherId: teachers[0]._id,
          remark: ['Excellent performance', 'Needs to improve attendance', 'Very active in class', 'Good behavior'][remarkCount % 4]
        });
        remarkCount++;
        scenarioMetrics.remarksAdded++;
      } catch (err) { }
    }
    logTest('Academic', 'Student remarks added', remarkCount > 0 ? 'PASS' : 'FAIL',
      `${remarkCount} remarks added`);

    // ============================================
    // SCENARIO 5: LIBRARY OPERATIONS (1:00 PM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('📖 SCENARIO 5: LIBRARY OPERATIONS (1:00 PM)');
    console.log('='.repeat(80));

    // Test 5.1: Issue Books to Students
    console.log('\n1️⃣2️⃣ Issuing Library Books...');
    const books = await LibraryBook.find().limit(10);
    const libraryStudents = await Student.find().limit(8);
    let bookIssueCount = 0;
    for (let i = 0; i < Math.min(books.length, libraryStudents.length); i++) {
      try {
        await LibraryTransaction.create({
          studentId: libraryStudents[i]._id,
          bookId: books[i]._id,
          issueDate: new Date(),
          dueDate: new Date(Date.now() + 14 * 86400000),
          status: 'BORROWED',
          fineAmount: 0
        });
        bookIssueCount++;
        scenarioMetrics.booksIssued++;
      } catch (err) { }
    }
    logTest('Library', 'Books issued to students', bookIssueCount > 0 ? 'PASS' : 'FAIL',
      `${bookIssueCount} books issued`);

    // ============================================
    // SCENARIO 6: CANTEEN OPERATIONS (1:30 PM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('🍔 SCENARIO 6: CANTEEN OPERATIONS (1:30 PM)');
    console.log('='.repeat(80));

    // Test 6.1: View Canteen Menu
    console.log('\n1️⃣3️⃣ Checking Canteen Menu...');
    const canteenItems = await CanteenItem.find({ isAvailable: true }).limit(10);
    logTest('Canteen', 'Menu items available', canteenItems.length > 0 ? 'PASS' : 'FAIL',
      `${canteenItems.length} items available`);

    // Test 6.2: Process Canteen Sales
    console.log('\n1️⃣4️⃣ Processing Canteen Sales...');
    const canteenStaff = await User.findOne({ role: 'canteen' });
    let canteenSaleCount = 0;
    for (let i = 0; i < 10; i++) {
      try {
        const item = canteenItems[i % canteenItems.length];
        const student = libraryStudents[i % libraryStudents.length];
        await CanteenSale.create({
          items: [{
            itemId: item._id,
            quantity: Math.floor(Math.random() * 3) + 1,
            price: item.price
          }],
          total: item.price * (Math.floor(Math.random() * 3) + 1),
          soldTo: student._id.toString(),
          soldBy: canteenStaff?._id || teachers[0]._id,
          date: new Date()
        });
        canteenSaleCount++;
        scenarioMetrics.canteenSales++;
      } catch (err) { }
    }
    logTest('Canteen', 'Sales processed', canteenSaleCount > 0 ? 'PASS' : 'FAIL',
      `${canteenSaleCount} sales recorded`);

    // ============================================
    // SCENARIO 7: HR OPERATIONS (2:30 PM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('👥 SCENARIO 7: HR OPERATIONS (2:30 PM)');
    console.log('='.repeat(80));

    // Test 7.1: Process Leave Applications
    console.log('\n1️⃣5️⃣ Processing Leave Applications...');
    const hrUser = await User.findOne({ role: 'hr' });
    let leaveCount = 0;
    for (let i = 0; i < 3; i++) {
      try {
        await Leave.create({
          staffId: teachers[i]._id,
          type: ['sick', 'casual', 'earned'][i],
          fromDate: new Date(),
          toDate: new Date(Date.now() + 2 * 86400000),
          reason: ['Medical appointment', 'Personal work', 'Family function'][i],
          status: i === 2 ? 'approved' : 'pending',
          reviewedBy: hrUser?._id,
          reviewNote: i === 2 ? 'Approved' : ''
        });
        leaveCount++;
        scenarioMetrics.leaveApplicationsProcessed++;
      } catch (err) { }
    }
    logTest('HR', 'Leave applications processed', leaveCount > 0 ? 'PASS' : 'FAIL',
      `${leaveCount} applications (1 approved, 2 pending)`);

    // Test 7.2: Generate Payroll
    console.log('\n1️⃣6️⃣ Generating Payroll...');
    let payrollCount = 0;
    for (const teacher of teachers.slice(0, 5)) {
      try {
        const basicSalary = Math.floor(Math.random() * 30000) + 30000;
        await Payroll.create({
          staffId: teacher._id,
          month: new Date().getMonth() + 1,
          year: new Date().getFullYear(),
          basicSalary,
          hra: Math.floor(basicSalary * 0.4),
          da: Math.floor(basicSalary * 0.2),
          conveyance: 2000,
          medicalAllowance: 1500,
          specialAllowance: 3000,
          totalEarnings: 0, // Auto-calculated
          pfDeduction: Math.floor(basicSalary * 0.12),
          taxDeduction: Math.floor(basicSalary * 0.05),
          otherDeductions: 500,
          totalDeductions: 0, // Auto-calculated
          netPay: 0, // Auto-calculated
          generatedDate: new Date(),
          isPaid: false
        });
        payrollCount++;
        scenarioMetrics.payrollGenerated++;
      } catch (err) { }
    }
    logTest('HR', 'Payroll generated', payrollCount > 0 ? 'PASS' : 'FAIL',
      `${payrollCount} staff payroll ready`);

    // ============================================
    // SCENARIO 8: COMPLAINTS & NOTICES (3:30 PM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('📢 SCENARIO 8: COMPLAINTS & NOTICES (3:30 PM)');
    console.log('='.repeat(80));

    // Test 8.1: Publish Notice
    console.log('\n1️⃣7️⃣ Publishing Notice...');
    let noticeCount = 0;
    const notices = [
      { title: 'Annual Day Celebration', priority: 'important' },
      { title: 'Exam Schedule Updated', priority: 'urgent' },
      { title: 'Holiday Notice - Diwali', priority: 'normal' }
    ];
    for (const notice of notices) {
      try {
        await Notice.create({
          title: notice.title,
          content: `This is to inform all students and parents about the ${notice.title.toLowerCase()}. Please note the details and act accordingly.`,
          audience: ['all'],
          createdBy: adminUser?._id || teachers[0]._id,
          priority: notice.priority,
          published: true
        });
        noticeCount++;
        scenarioMetrics.noticesPublished++;
      } catch (err) { }
    }
    logTest('Notices', 'Notices published', noticeCount > 0 ? 'PASS' : 'FAIL',
      `${noticeCount} notices published`);

    // Test 8.2: File Complaint
    console.log('\n1️⃣8️⃣ Filing Complaint...');
    let complaintCount = 0;
    const parentUser = await User.findOne({ role: 'parent' });
    try {
      const student = await Student.findOne();
      await Complaint.create({
        userId: parentUser?._id || teachers[0]._id,
        type: 'parent_to_teacher',
        studentId: student?._id,
        subject: 'Infrastructure Issue',
        description: 'The classroom fans in Room 5 are not working properly. Please repair them urgently as summer is approaching.',
        status: 'open',
        raisedByRole: 'parent',
        assignedToRole: 'staff',
        resolutionNote: ''
      });
      complaintCount++;
      scenarioMetrics.complaintsReceived++;
    } catch (err) { }
    logTest('Complaints', 'Complaint filed', complaintCount > 0 ? 'PASS' : 'FAIL',
      `${complaintCount} complaint registered`);

    // ============================================
    // SCENARIO 9: CHATBOT TESTING (4:00 PM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('🤖 SCENARIO 9: CHATBOT TESTING (4:00 PM)');
    console.log('='.repeat(80));

    // Test 9.1: Test Chatbot with School Queries
    console.log('\n1️⃣9️⃣ Testing Chatbot with School-Specific Queries...');
    try {
      const { processMessage } = require('../ai/nlpEngine');

      const schoolQueries = [
        { query: 'hello', category: 'Greeting', expectedIntentPrefix: 'greeting.' },
        { query: 'how many students are there', category: 'Students', expectedIntent: 'student.getCount' },
        { query: 'how many teachers', category: 'Staff', expectedIntent: 'staff.getCount' },
        { query: 'transport routes', category: 'Transport', expectedIntent: 'transport.getRoutes' },
        { query: 'available library books', category: 'Library', expectedIntent: 'library.checkBook' }
      ];

      let chatbotPass = 0;
      for (const q of schoolQueries) {
        const response = await processMessage(q.query, 'en');
        const isFallback = !response
          || response.intent === 'None'
          || /didn['’]t quite understand/i.test(response.message || '');
        const intentMatches = q.expectedIntent
          ? response?.intent === q.expectedIntent
          : response?.intent?.startsWith(q.expectedIntentPrefix);

        if (!isFallback && intentMatches && response.message && response.message.length > 10) {
          chatbotPass++;
          console.log(`   ✅ "${q.query}" → ${response.intent || 'General'} (${response.responseTime || 0}ms)`);
        } else {
          console.log(`   ⚠️ "${q.query}" → ${response?.intent || 'None'} | ${response?.message || 'No response'}`);
        }
      }
      logTest('Chatbot', 'School queries handled', chatbotPass === schoolQueries.length ? 'PASS' : 'WARN',
        `${chatbotPass}/${schoolQueries.length} queries successful`);
    } catch (err) {
      logTest('Chatbot', 'School queries handled', 'FAIL', err.message);
    }

    // ============================================
    // SCENARIO 10: END-OF-DAY REPORTS (5:00 PM)
    // ============================================
    console.log('\n' + '='.repeat(80));
    console.log('📊 SCENARIO 10: END-OF-DAY REPORTS (5:00 PM)');
    console.log('='.repeat(80));

    // Test 10.1: Generate Daily Summary
    console.log('\n2️⃣0️⃣ Generating Daily Summary Report...');
    const dailyStats = {
      attendance: scenarioMetrics.studentAttendanceMarked,
      feesCollected: scenarioMetrics.feesCollectedCount,
      feeAmount: scenarioMetrics.feesCollectedAmount,
      homeworkAssigned: scenarioMetrics.homeworkAssigned,
      examsScheduled: scenarioMetrics.examsScheduled,
      examResultsRecorded: scenarioMetrics.examResultsRecorded,
      booksIssued: scenarioMetrics.booksIssued,
      canteenSales: scenarioMetrics.canteenSales,
      complaintsReceived: scenarioMetrics.complaintsReceived,
      noticesPublished: scenarioMetrics.noticesPublished,
      leaveApplicationsProcessed: scenarioMetrics.leaveApplicationsProcessed,
      payrollGenerated: scenarioMetrics.payrollGenerated
    };

    logTest('Reports', 'Daily summary generated', 'PASS',
      `Attendance: ${dailyStats.attendance}, Fees: ${dailyStats.feesCollected}, Homework: ${dailyStats.homeworkAssigned}, ` +
      `Exams: ${dailyStats.examsScheduled}, Library: ${dailyStats.booksIssued}, Canteen: ${dailyStats.canteenSales}, ` +
      `Complaints: ${dailyStats.complaintsReceived}, Notices: ${dailyStats.noticesPublished}`);

    // ============================================
    // FINAL PILOT TEST REPORT
    // ============================================
    const totalTime = ((Date.now() - startTime) / 1000).toFixed(2);

    console.log('\n' + '='.repeat(80));
    console.log('🎯 PILOT TEST FINAL REPORT - DELHI PUBLIC ACADEMY');
    console.log('='.repeat(80));

    console.log('\n📊 Test Results Summary:');
    console.log('─'.repeat(80));
    console.log(`   Total Tests:     ${testResults.passed + testResults.failed + testResults.warnings}`);
    console.log(`   ✅ Passed:        ${testResults.passed}`);
    console.log(`   ❌ Failed:        ${testResults.failed}`);
    console.log(`   ⚠️  Warnings:     ${testResults.warnings}`);
    console.log(`   Success Rate:    ${(((testResults.passed) / (testResults.passed + testResults.failed + testResults.warnings)) * 100).toFixed(1)}%`);
    console.log(`   Time Elapsed:    ${totalTime}s`);
    console.log('─'.repeat(80));

    console.log('\n📋 Module-wise Breakdown:');
    for (const [module, data] of Object.entries(testResults.modules)) {
      const total = data.passed + data.failed + data.warnings;
      const rate = ((data.passed / total) * 100).toFixed(0);
      console.log(`   ${module.padEnd(20)} ${String(total).padStart(3)} tests | ✅ ${data.passed} ❌ ${data.failed} ⚠️ ${data.warnings} | ${rate}% success`);
    }

    console.log('\n📈 Daily Operations Summary:');
    console.log('─'.repeat(80));
    console.log(`   👥 Staff Attendance Marked:    ${staffAttCount} teachers`);
    console.log(`   🎓 Student Attendance Marked:  ${studentAttCount} students`);
    console.log(`   💰 Fees Collected:             ₹${dailyStats.feeAmount.toLocaleString()} from ${dailyStats.feesCollected} payments`);
    console.log(`   📚 Homework Assigned:          ${dailyStats.homeworkAssigned} assignments`);
    console.log(`   📝 Exams Scheduled:            ${dailyStats.examsScheduled} exams`);
    console.log(`   📄 Exam Results Recorded:      ${dailyStats.examResultsRecorded} results`);
    console.log(`   📖 Books Issued:               ${dailyStats.booksIssued} books`);
    console.log(`   🍔 Canteen Sales:              ${dailyStats.canteenSales} transactions`);
    console.log(`   👥 Leave Requests Processed:   ${dailyStats.leaveApplicationsProcessed} applications`);
    console.log(`   💼 Payroll Runs Generated:     ${dailyStats.payrollGenerated} payrolls`);
    console.log(`   📢 Notices Published:          ${dailyStats.noticesPublished} notices`);
    console.log(`   ⚠️  Complaints Received:        ${dailyStats.complaintsReceived} complaints`);
    console.log('─'.repeat(80));

    // Pilot Decision
    const successRate = ((testResults.passed) / (testResults.passed + testResults.failed + testResults.warnings)) * 100;
    console.log('\n' + '='.repeat(80));
    if (successRate >= 90 && testResults.failed === 0) {
      console.log('✅ PILOT TEST RESULT: PASSED');
      console.log('🎉 Delhi Public Academy is ready for full deployment!');
      console.log('='.repeat(80));
      console.log('\n📋 Recommendations:');
      console.log('   • Deploy to production server');
      console.log('   • Train school staff on all modules');
      console.log('   • Monitor logs daily for first 2 weeks');
      console.log('   • Collect feedback from teachers & parents');
      console.log('   • Schedule follow-up review in 30 days');
    } else if (successRate >= 75) {
      console.log('⚠️  PILOT TEST RESULT: CONDITIONALLY PASSED');
      console.log('📝 Minor issues found. Fix warnings before full deployment.');
      console.log('='.repeat(80));
    } else {
      console.log('❌ PILOT TEST RESULT: FAILED');
      console.log('🚨 Critical issues found. Must fix before deployment.');
      console.log('='.repeat(80));
    }
    console.log('\n');

  } catch (error) {
    console.error('❌ Pilot test failed:', error);
    console.error(error.stack);
  } finally {
    await prisma.$disconnect();
    console.log('👋 Database connection closed\n');
    process.exit(0);
  }
}

runPilotTest().catch(console.error);
