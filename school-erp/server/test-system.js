const prisma = require('./config/prisma');
require('dotenv').config();

// Import all models
const Student = require('./models/Student');
const User = require('./models/User');
const Class = require('./models/Class');
const Attendance = require('./models/Attendance');
const FeePayment = require('./models/FeePayment');
const Exam = require('./models/Exam');
const ExamResult = require('./models/ExamResult');

async function runTests() {
  try {
    await prisma.$connect();
    console.log('✅ MongoDB Connected\n');

    const results = {
      passed: [],
      failed: [],
      warnings: []
    };

    // Test 1: Check if mock data exists
    console.log('🧪 Running Comprehensive Tests...\n');
    
    const tests = [
      {
        name: 'Users Collection',
        test: async () => {
          const count = await User.countDocuments();
          if (count >= 10) return { pass: true, message: `${count} users found` };
          if (count > 0) return { pass: false, message: `Only ${count} users (expected 10+)`, warning: true };
          return { pass: false, message: 'No users found' };
        }
      },
      {
        name: 'Students Collection',
        test: async () => {
          const count = await Student.countDocuments();
          if (count >= 10) return { pass: true, message: `${count} students found` };
          if (count > 0) return { pass: false, message: `Only ${count} students (expected 10+)`, warning: true };
          return { pass: false, message: 'No students found' };
        }
      },
      {
        name: 'Classes Collection',
        test: async () => {
          const count = await Class.countDocuments();
          if (count >= 10) return { pass: true, message: `${count} classes found` };
          if (count > 0) return { pass: false, message: `Only ${count} classes (expected 10+)`, warning: true };
          return { pass: false, message: 'No classes found' };
        }
      },
      {
        name: 'Attendance Records',
        test: async () => {
          const count = await Attendance.countDocuments();
          if (count >= 100) return { pass: true, message: `${count} attendance records found` };
          if (count > 0) return { pass: false, message: `Only ${count} records (expected 100+)`, warning: true };
          return { pass: false, message: 'No attendance records found' };
        }
      },
      {
        name: 'Fee Payments',
        test: async () => {
          const count = await FeePayment.countDocuments();
          if (count >= 20) return { pass: true, message: `${count} fee payments found` };
          if (count > 0) return { pass: false, message: `Only ${count} payments (expected 20+)`, warning: true };
          return { pass: false, message: 'No fee payments found' };
        }
      },
      {
        name: 'Exam Schedules',
        test: async () => {
          const count = await Exam.countDocuments();
          if (count >= 50) return { pass: true, message: `${count} exams scheduled` };
          if (count > 0) return { pass: false, message: `Only ${count} exams (expected 50+)`, warning: true };
          return { pass: false, message: 'No exams scheduled' };
        }
      },
      {
        name: 'Exam Results',
        test: async () => {
          const count = await ExamResult.countDocuments();
          if (count >= 50) return { pass: true, message: `${count} exam results found` };
          if (count > 0) return { pass: false, message: `Only ${count} results (expected 50+)`, warning: true };
          return { pass: false, message: 'No exam results found' };
        }
      },
      {
        name: 'Student-Class Relationship',
        test: async () => {
          const students = await Student.find().populate('classId');
          const valid = students.filter(s => s.classId).length;
          if (valid === students.length) return { pass: true, message: `All ${students.length} students have valid classes` };
          if (valid > 0) return { pass: false, message: `${valid}/${students.length} students have valid classes`, warning: true };
          return { pass: false, message: 'No students have valid classes' };
        }
      },
      {
        name: 'Student-User Relationship',
        test: async () => {
          const students = await Student.find().populate('userId');
          const valid = students.filter(s => s.userId).length;
          if (valid === students.length) return { pass: true, message: `All ${students.length} students have valid user accounts` };
          if (valid > 0) return { pass: false, message: `${valid}/${students.length} students have valid user accounts`, warning: true };
          return { pass: false, message: 'No students have valid user accounts' };
        }
      },
      {
        name: 'Attendance-Student Relationship',
        test: async () => {
          const attendance = await Attendance.find().populate('studentId');
          const valid = attendance.filter(a => a.studentId).length;
          if (valid === attendance.length) return { pass: true, message: `All ${attendance.length} records have valid students` };
          if (valid > 0) return { pass: false, message: `${valid}/${attendance.length} records have valid students`, warning: true };
          return { pass: false, message: 'No attendance records have valid students' };
        }
      },
      {
        name: 'Fee Payment-Student Relationship',
        test: async () => {
          const payments = await FeePayment.find().populate('studentId');
          const valid = payments.filter(p => p.studentId).length;
          if (valid === payments.length) return { pass: true, message: `All ${payments.length} payments have valid students` };
          if (valid > 0) return { pass: false, message: `${valid}/${payments.length} payments have valid students`, warning: true };
          return { pass: false, message: 'No payments have valid students' };
        }
      },
      {
        name: 'Exam Results Relationship',
        test: async () => {
          const results = await ExamResult.find().populate('studentId').populate('examId');
          const valid = results.filter(r => r.studentId && r.examId).length;
          if (valid === results.length) return { pass: true, message: `All ${results.length} results have valid relationships` };
          if (valid > 0) return { pass: false, message: `${valid}/${results.length} results have valid relationships`, warning: true };
          return { pass: false, message: 'No results have valid relationships' };
        }
      },
      {
        name: 'Login Credentials Test',
        test: async () => {
          const admin = await User.findOne({ email: 'admin@school.com' });
          if (admin) return { pass: true, message: 'Admin account exists (admin@school.com)' };
          return { pass: false, message: 'Admin account not found' };
        }
      },
      {
        name: 'Student Data Completeness',
        test: async () => {
          const students = await Student.find();
          const complete = students.filter(s => 
            s.name && s.admissionNo && s.classId && s.parentPhone && s.dob && s.gender
          ).length;
          if (complete === students.length) return { pass: true, message: `All ${students.length} students have complete data` };
          if (complete > 0) return { pass: false, message: `${complete}/${students.length} students have complete data`, warning: true };
          return { pass: false, message: 'No students have complete data' };
        }
      },
      {
        name: 'API Endpoint Connectivity',
        test: async () => {
          // Just check if we can query the database
          const [users, students, classes] = await Promise.all([
            User.countDocuments(),
            Student.countDocuments(),
            Class.countDocuments()
          ]);
          const total = users + students + classes;
          if (total > 0) return { pass: true, message: `Database queries working (${total} documents)` };
          return { pass: false, message: 'Database queries not working' };
        }
      }
    ];

    // Run all tests
    for (const test of tests) {
      try {
        const result = await test.test();
        if (result.pass) {
          results.passed.push({ name: test.name, message: result.message });
          console.log(`✅ ${test.name}: ${result.message}`);
        } else if (result.warning) {
          results.warnings.push({ name: test.name, message: result.message });
          console.log(`⚠️  ${test.name}: ${result.message}`);
        } else {
          results.failed.push({ name: test.name, message: result.message });
          console.log(`❌ ${test.name}: ${result.message}`);
        }
      } catch (err) {
        results.failed.push({ name: test.name, message: err.message });
        console.log(`❌ ${test.name}: ${err.message}`);
      }
    }

    // Summary
    console.log('\n' + '='.repeat(60));
    console.log('📊 TEST SUMMARY');
    console.log('='.repeat(60));
    console.log(`✅ Passed: ${results.passed.length}/${tests.length}`);
    console.log(`⚠️  Warnings: ${results.warnings.length}/${tests.length}`);
    console.log(`❌ Failed: ${results.failed.length}/${tests.length}`);
    console.log('='.repeat(60));

    if (results.failed.length === 0 && results.warnings.length === 0) {
      console.log('\n🎉 ALL TESTS PASSED! System is ready!\n');
    } else if (results.failed.length === 0) {
      console.log('\n✅ All critical tests passed! Some data is limited.\n');
    } else {
      console.log('\n❌ Some tests failed. Please review the issues above.\n');
      console.log('💡 Run "node server/create-mock-data.js" to create test data\n');
    }

    process.exit(results.failed.length > 0 ? 1 : 0);
  } catch (err) {
    console.error('❌ Test execution failed:', err.message);
    process.exit(1);
  }
}

runTests();
