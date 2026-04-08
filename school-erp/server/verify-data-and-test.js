/**
 * Comprehensive Data Verification & Chatbot Testing Script
 * 1. Verify all data was generated correctly
 * 2. Check data relationships and integrity
 * 3. Test chatbot with new data
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

connectDB();

function buildReferenceStats(ids) {
  const normalizedIds = ids.map(id => (id ? String(id) : null));
  const presentIds = normalizedIds.filter(Boolean);

  return {
    sampleCount: ids.length,
    presentCount: presentIds.length,
    uniqueIds: [...new Set(presentIds)]
  };
}

async function validateReferences(Model, ids) {
  const stats = buildReferenceStats(ids);
  const validCount = stats.uniqueIds.length
    ? await Model.countDocuments({ _id: { $in: stats.uniqueIds } })
    : 0;

  return {
    ...stats,
    validCount,
    missingCount: stats.sampleCount - stats.presentCount,
    invalidCount: stats.uniqueIds.length - validCount,
    isValid:
      stats.sampleCount > 0 &&
      stats.sampleCount === stats.presentCount &&
      stats.uniqueIds.length === validCount
  };
}

function formatReferenceStats(summary) {
  return `sampled ${summary.sampleCount}, present ${summary.presentCount}, unique ${summary.uniqueIds.length}, valid unique ${summary.validCount}`;
}

function logRelationshipResult(results, test, passed, successMessage, warningMessage) {
  if (passed) {
    console.log(`   PASS ${successMessage}`);
    results.relationships.passed++;
  } else {
    console.log(`   WARN ${warningMessage}`);
    results.relationships.warnings++;
  }

  results.relationships.details.push({
    test,
    status: passed ? 'PASS' : 'WARN'
  });
}

async function verifyAllData() {
  console.log(`\n${'='.repeat(70)}`);
  console.log('COMPREHENSIVE DATA VERIFICATION & CHATBOT TESTING');
  console.log(`${'='.repeat(70)}\n`);

  const results = {
    counts: {},
    relationships: { passed: 0, failed: 0, warnings: 0, details: [] },
    chatbot: { tested: 0, passed: 0, failed: 0, details: [] }
  };

  // ============================================
  // PART 1: VERIFY DATA COUNTS
  // ============================================
  console.log('PART 1: VERIFYING DATA COUNTS\n');
  console.log('-'.repeat(70));

  const models = [
    { name: 'User', model: User, expected: '10000+' },
    { name: 'Student', model: Student, expected: '5000+' },
    { name: 'Class', model: Class, expected: '500' },
    { name: 'Attendance', model: Attendance, expected: '10000+' },
    { name: 'StaffAttendance', model: StaffAttendance, expected: '10000' },
    { name: 'FeeStructure', model: FeeStructure, expected: '5000' },
    { name: 'FeePayment', model: FeePayment, expected: '10000+' },
    { name: 'Exam', model: Exam, expected: '5000' },
    { name: 'ExamResult', model: ExamResult, expected: '7000+' },
    { name: 'Homework', model: Homework, expected: '5000' },
    { name: 'Notice', model: Notice, expected: '3000' },
    { name: 'Notification', model: Notification, expected: '3000' },
    { name: 'LibraryBook', model: LibraryBook, expected: '2000' },
    { name: 'LibraryTransaction', model: LibraryTransaction, expected: '5000' },
    { name: 'CanteenItem', model: CanteenItem, expected: '1000' },
    { name: 'CanteenSale', model: CanteenSale, expected: '5000' },
    { name: 'BusRoute', model: BusRoute, expected: '500' },
    { name: 'TransportVehicle', model: TransportVehicle, expected: '1000' },
    { name: 'TransportAttendance', model: TransportAttendance, expected: '5000' },
    { name: 'HostelRoomType', model: HostelRoomType, expected: '50+' },
    { name: 'HostelRoom', model: HostelRoom, expected: '200+' },
    { name: 'HostelAllocation', model: HostelAllocation, expected: '200+' },
    { name: 'HostelFeeStructure', model: HostelFeeStructure, expected: '50+' },
    { name: 'Complaint', model: Complaint, expected: '2000' },
    { name: 'Remark', model: Remark, expected: '3000' },
    { name: 'Leave', model: Leave, expected: '1000' },
    { name: 'SalaryStructure', model: SalaryStructure, expected: '500+' },
    { name: 'Payroll', model: Payroll, expected: '2000' },
    { name: 'Routine', model: Routine, expected: '100' }
  ];

  let totalRecords = 0;
  for (const { name, model, expected } of models) {
    try {
      const count = await model.countDocuments();
      results.counts[name] = count;
      totalRecords += count;
      const status = count > 0 ? 'PASS' : 'WARN';
      console.log(`${status.padEnd(4)} ${name.padEnd(25)} ${String(count).padStart(8)} records (expected ${expected || 'N/A'})`);
    } catch (err) {
      console.log(`FAIL ${name.padEnd(25)} ERROR: ${err.message}`);
      results.counts[name] = 0;
    }
  }

  console.log('-'.repeat(70));
  console.log(`TOTAL RECORDS IN DATABASE: ${totalRecords.toLocaleString()}\n`);

  // ============================================
  // PART 2: CHECK DATA RELATIONSHIPS & INTEGRITY
  // ============================================
  console.log('\nPART 2: CHECKING DATA RELATIONSHIPS & INTEGRITY\n');
  console.log('-'.repeat(70));

  console.log('Test 1: Student -> Class references...');
  const students = await Student.find().limit(100).select('classId userId name');
  const studentClassSummary = await validateReferences(Class, students.map(student => student.classId));
  logRelationshipResult(
    results,
    'Student->Class',
    studentClassSummary.isValid,
    `Student class references valid (${formatReferenceStats(studentClassSummary)})`,
    `Student class references invalid (${formatReferenceStats(studentClassSummary)})`
  );

  console.log('Test 2: Student -> User references...');
  const studentUserSummary = await validateReferences(User, students.map(student => student.userId));
  logRelationshipResult(
    results,
    'Student->User',
    studentUserSummary.isValid,
    `Student user references valid (${formatReferenceStats(studentUserSummary)})`,
    `Student user references invalid (${formatReferenceStats(studentUserSummary)})`
  );

  console.log('Test 3: Exam -> Class references...');
  const exams = await Exam.find().limit(100).select('classId name');
  const examClassSummary = await validateReferences(Class, exams.map(exam => exam.classId));
  logRelationshipResult(
    results,
    'Exam->Class',
    examClassSummary.isValid,
    `Exam class references valid (${formatReferenceStats(examClassSummary)})`,
    `Exam class references invalid (${formatReferenceStats(examClassSummary)})`
  );

  console.log('Test 4: ExamResult -> Exam & Student references...');
  const examResults = await ExamResult.find().limit(100).select('examId studentId');
  const examResultExamSummary = await validateReferences(Exam, examResults.map(result => result.examId));
  const examResultStudentSummary = await validateReferences(Student, examResults.map(result => result.studentId));
  logRelationshipResult(
    results,
    'ExamResult->Exam/Student',
    examResultExamSummary.isValid && examResultStudentSummary.isValid,
    `Exam result references valid (exams: ${formatReferenceStats(examResultExamSummary)}; students: ${formatReferenceStats(examResultStudentSummary)})`,
    `Exam result references invalid (exams: ${formatReferenceStats(examResultExamSummary)}; students: ${formatReferenceStats(examResultStudentSummary)})`
  );

  console.log('Test 5: FeePayment -> Student references...');
  const feePayments = await FeePayment.find().limit(100).select('studentId');
  if (feePayments.length > 0) {
    const feeStudentSummary = await validateReferences(Student, feePayments.map(payment => payment.studentId));
    logRelationshipResult(
      results,
      'FeePayment->Student',
      feeStudentSummary.isValid,
      `Fee payment student references valid (${formatReferenceStats(feeStudentSummary)})`,
      `Fee payment student references invalid (${formatReferenceStats(feeStudentSummary)})`
    );
  } else {
    console.log('   WARN No fee payments found to test');
    results.relationships.warnings++;
  }

  console.log('Test 6: LibraryTransaction -> Student & Book references...');
  const libTrans = await LibraryTransaction.find().limit(100).select('studentId bookId');
  if (libTrans.length > 0) {
    const libStudentSummary = await validateReferences(Student, libTrans.map(transaction => transaction.studentId));
    const libBookSummary = await validateReferences(LibraryBook, libTrans.map(transaction => transaction.bookId));
    logRelationshipResult(
      results,
      'LibraryTransaction->Student/Book',
      libStudentSummary.isValid && libBookSummary.isValid,
      `Library transaction references valid (students: ${formatReferenceStats(libStudentSummary)}; books: ${formatReferenceStats(libBookSummary)})`,
      `Library transaction references invalid (students: ${formatReferenceStats(libStudentSummary)}; books: ${formatReferenceStats(libBookSummary)})`
    );
  }

  console.log('Test 7: Remark -> Student & Teacher references...');
  const remarks = await Remark.find().limit(100).select('studentId teacherId');
  if (remarks.length > 0) {
    const remarkStudentSummary = await validateReferences(Student, remarks.map(remark => remark.studentId));
    const remarkTeacherSummary = await validateReferences(User, remarks.map(remark => remark.teacherId));
    logRelationshipResult(
      results,
      'Remark->Student/Teacher',
      remarkStudentSummary.isValid && remarkTeacherSummary.isValid,
      `Remark references valid (students: ${formatReferenceStats(remarkStudentSummary)}; teachers: ${formatReferenceStats(remarkTeacherSummary)})`,
      `Remark references invalid (students: ${formatReferenceStats(remarkStudentSummary)}; teachers: ${formatReferenceStats(remarkTeacherSummary)})`
    );
  }

  console.log('Test 8: Attendance -> Student, Class & Teacher references...');
  const attendance = await Attendance.find().limit(100).select('studentId classId teacherId');
  if (attendance.length > 0) {
    const attStudentSummary = await validateReferences(Student, attendance.map(record => record.studentId));
    const attClassSummary = await validateReferences(Class, attendance.map(record => record.classId));
    const attTeacherSummary = await validateReferences(User, attendance.map(record => record.teacherId));
    logRelationshipResult(
      results,
      'Attendance->Student/Class/Teacher',
      attStudentSummary.isValid && attClassSummary.isValid && attTeacherSummary.isValid,
      `Attendance references valid (students: ${formatReferenceStats(attStudentSummary)}; classes: ${formatReferenceStats(attClassSummary)}; teachers: ${formatReferenceStats(attTeacherSummary)})`,
      `Attendance references invalid (students: ${formatReferenceStats(attStudentSummary)}; classes: ${formatReferenceStats(attClassSummary)}; teachers: ${formatReferenceStats(attTeacherSummary)})`
    );
  } else {
    console.log('   WARN No attendance records found');
    results.relationships.warnings++;
  }

  console.log('Test 9: Homework -> Class & Teacher references...');
  const homeworks = await Homework.find().limit(100).select('classId teacherId');
  if (homeworks.length > 0) {
    const hwClassSummary = await validateReferences(Class, homeworks.map(homework => homework.classId));
    const hwTeacherSummary = await validateReferences(User, homeworks.map(homework => homework.teacherId));
    logRelationshipResult(
      results,
      'Homework->Class/Teacher',
      hwClassSummary.isValid && hwTeacherSummary.isValid,
      `Homework references valid (classes: ${formatReferenceStats(hwClassSummary)}; teachers: ${formatReferenceStats(hwTeacherSummary)})`,
      `Homework references invalid (classes: ${formatReferenceStats(hwClassSummary)}; teachers: ${formatReferenceStats(hwTeacherSummary)})`
    );
  }

  console.log('Test 10: Payroll -> Staff references...');
  const payrolls = await Payroll.find().limit(100).select('staffId');
  if (payrolls.length > 0) {
    const payrollStaffSummary = await validateReferences(User, payrolls.map(payroll => payroll.staffId));
    logRelationshipResult(
      results,
      'Payroll->Staff',
      payrollStaffSummary.isValid,
      `Payroll staff references valid (${formatReferenceStats(payrollStaffSummary)})`,
      `Payroll staff references invalid (${formatReferenceStats(payrollStaffSummary)})`
    );
  }

  console.log('-'.repeat(70));
  console.log(`Relationship Tests: ${results.relationships.passed} passed, ${results.relationships.warnings} warnings, ${results.relationships.failed} failed\n`);

  // ============================================
  // PART 3: TEST CHATBOT WITH NEW DATA
  // ============================================
  console.log('\nPART 3: TESTING CHATBOT WITH MOCK DATA\n');
  console.log('-'.repeat(70));

  const { processMessage } = require('./ai/nlpEngine');
  const fallbackPrefix = "I didn't quite understand";

  const testCases = [
    { query: 'hello', expected: 'greeting', desc: 'Greeting test' },
    { query: 'how many students are there', expected: 'student', desc: 'Student count query' },
    { query: 'show me exam schedule', expected: 'exam', desc: 'Exam schedule query' },
    { query: 'library books available', expected: 'library', desc: 'Library query' },
    { query: 'fee payment status', expected: 'fee', desc: 'Fee status query' },
    { query: 'transport routes', expected: 'transport', desc: 'Transport query' },
    { query: 'canteen menu', expected: 'canteen', desc: 'Canteen query' },
    { query: 'attendance report', expected: 'attendance', desc: 'Attendance query' },
    { query: 'how many teachers', expected: 'staff', desc: 'Teacher count query' },
    { query: 'payroll information', expected: 'payroll', desc: 'Payroll query' }
  ];

  for (const testCase of testCases) {
    try {
      console.log(`\nTest: ${testCase.desc}`);
      console.log(`Query: "${testCase.query}"`);

      const result = await processMessage(testCase.query, 'en');
      const message = result?.message || '';
      const intent = result?.intent || 'N/A';
      const isFallback = intent === 'None' || message.startsWith(fallbackPrefix);
      const matchesExpected = intent.toLowerCase().includes(testCase.expected);

      if (result && message && !isFallback && matchesExpected) {
        console.log(`Response: ${message.substring(0, 100)}...`);
        console.log(`Intent: ${intent}`);
        results.chatbot.tested++;
        results.chatbot.passed++;
        results.chatbot.details.push({
          query: testCase.query,
          intent,
          status: 'PASS'
        });
      } else if (result && message) {
        console.log(`Response: ${message.substring(0, 100)}...`);
        console.log(`Intent: ${intent}`);
        console.log(`FAIL Expected domain: ${testCase.expected}`);
        results.chatbot.tested++;
        results.chatbot.failed++;
        results.chatbot.details.push({
          query: testCase.query,
          intent,
          status: `FAIL - Expected ${testCase.expected}`
        });
      } else {
        console.log('FAIL No response received');
        results.chatbot.tested++;
        results.chatbot.failed++;
        results.chatbot.details.push({
          query: testCase.query,
          intent: 'N/A',
          status: 'FAIL - No response'
        });
      }
    } catch (err) {
      console.log(`FAIL Error: ${err.message}`);
      results.chatbot.tested++;
      results.chatbot.failed++;
      results.chatbot.details.push({
        query: testCase.query,
        error: err.message,
        status: 'FAIL'
      });
    }
  }

  console.log(`\n${'-'.repeat(70)}`);
  console.log(`Chatbot Tests: ${results.chatbot.passed}/${results.chatbot.tested} passed, ${results.chatbot.failed} failed\n`);

  // ============================================
  // PART 4: DATA DISTRIBUTION ANALYSIS
  // ============================================
  console.log('\nPART 4: DATA DISTRIBUTION ANALYSIS\n');
  console.log('-'.repeat(70));

  console.log('User Role Distribution:');
  const userRoles = await User.aggregate([
    { $group: { _id: '$role', count: { $sum: 1 } } },
    { $sort: { count: -1 } }
  ]);
  for (const role of userRoles) {
    console.log(`   ${role._id.padEnd(20)} ${role.count}`);
  }

  console.log('\nStudent Gender Distribution:');
  const studentGenders = await Student.aggregate([
    { $group: { _id: '$gender', count: { $sum: 1 } } }
  ]);
  for (const gender of studentGenders) {
    console.log(`   ${gender._id.padEnd(20)} ${gender.count}`);
  }

  console.log('\nStudent Category Distribution:');
  const studentCategories = await Student.aggregate([
    { $group: { _id: '$category', count: { $sum: 1 } } }
  ]);
  for (const category of studentCategories) {
    console.log(`   ${(category._id || 'N/A').padEnd(20)} ${category.count}`);
  }

  console.log('\nExam Type Distribution:');
  const examTypes = await Exam.aggregate([
    { $group: { _id: '$examType', count: { $sum: 1 } } },
    { $sort: { count: -1 } }
  ]);
  for (const examType of examTypes) {
    console.log(`   ${(examType._id || 'N/A').padEnd(20)} ${examType.count}`);
  }

  console.log('\nComplaint Status Distribution:');
  const complaintStatuses = await Complaint.aggregate([
    { $group: { _id: '$status', count: { $sum: 1 } } }
  ]);
  for (const status of complaintStatuses) {
    console.log(`   ${(status._id || 'N/A').padEnd(20)} ${status.count}`);
  }

  console.log('\nAttendance Status Distribution:');
  const attendanceStatuses = await StaffAttendance.aggregate([
    { $group: { _id: '$status', count: { $sum: 1 } } }
  ]);
  for (const status of attendanceStatuses) {
    console.log(`   ${(status._id || 'N/A').padEnd(20)} ${status.count}`);
  }

  console.log('\nLibrary Transaction Status:');
  const libStatuses = await LibraryTransaction.aggregate([
    { $group: { _id: '$status', count: { $sum: 1 } } }
  ]);
  for (const status of libStatuses) {
    console.log(`   ${(status._id || 'N/A').padEnd(20)} ${status.count}`);
  }

  console.log('\nLeave Application Status:');
  const leaveStatuses = await Leave.aggregate([
    { $group: { _id: '$status', count: { $sum: 1 } } }
  ]);
  for (const status of leaveStatuses) {
    console.log(`   ${(status._id || 'N/A').padEnd(20)} ${status.count}`);
  }

  console.log('-'.repeat(70));

  // ============================================
  // PART 5: FINAL SUMMARY
  // ============================================
  console.log(`\n${'='.repeat(70)}`);
  console.log('FINAL VERIFICATION SUMMARY');
  console.log('='.repeat(70));

  console.log('\nData Counts:');
  console.log(`   Total Records: ${totalRecords.toLocaleString()}`);
  console.log(`   Collections with Data: ${Object.values(results.counts).filter(count => count > 0).length}/${models.length}`);

  console.log('\nData Integrity:');
  console.log(`   Tests Passed: ${results.relationships.passed}`);
  console.log(`   Warnings: ${results.relationships.warnings}`);
  console.log(`   Failed: ${results.relationships.failed}`);

  console.log('\nChatbot Testing:');
  console.log(`   Tests Run: ${results.chatbot.tested}`);
  console.log(`   Passed: ${results.chatbot.passed}`);
  console.log(`   Failed: ${results.chatbot.failed}`);

  console.log(`\n${'='.repeat(70)}`);

  if (results.relationships.failed === 0 && results.relationships.warnings === 0 && results.chatbot.failed === 0) {
    console.log('PASS All tests passed. Database is healthy and chatbot is working.');
  } else if (results.relationships.failed > 0 || results.chatbot.failed > 0) {
    console.log('WARN Some tests failed. Review the details above.');
  } else {
    console.log('WARN All tests completed with warnings.');
  }

  console.log(`${'='.repeat(70)}\n`);

  await prisma.$disconnect();
  console.log('Database connection closed\n');
  process.exit(0);
}

verifyAllData().catch(err => {
  console.error('FAIL Fatal error:', err);
  process.exit(1);
});
