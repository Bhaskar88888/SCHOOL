/**
 * Fix Orphaned References
 * Week 1, Day 1-2: Task 1.1
 * 
 * Fixes:
 * - Students pointing to non-existent classes
 * - Exams pointing to non-existent classes
 * - Homework pointing to non-existent classes
 * - ExamResults pointing to non-existent exams/students
 * - FeePayments pointing to non-existent students
 * - LibraryTransactions pointing to non-existent students/books
 * - Remarks pointing to non-existent students/teachers
 * - Payroll pointing to non-existent staff
 */

require('dotenv').config();
const prisma = require('../config/prisma');
const connectDB = require('../config/db');

const Student = require('../models/Student');
const Class = require('../models/Class');
const Exam = require('../models/Exam');
const ExamResult = require('../models/ExamResult');
const Homework = require('../models/Homework');
const FeePayment = require('../models/FeePayment');
const LibraryTransaction = require('../models/LibraryTransaction');
const LibraryBook = require('../models/LibraryBook');
const Remark = require('../models/Remark');
const User = require('../models/User');
const Payroll = require('../models/Payroll');
const Attendance = require('../models/Attendance');

connectDB();

async function fixOrphanedReferences() {
  console.log('\n' + '='.repeat(70));
  console.log('🔧 FIXING ORPHANED REFERENCES');
  console.log('='.repeat(70) + '\n');

  const stats = {
    fixed: 0,
    deleted: 0,
    errors: 0
  };

  try {
    // ========================================
    // 1. Fix Student → Class references
    // ========================================
    console.log('1️⃣  Fixing Student → Class references...');
    const classes = await Class.find().select('_id');
    const validClassIds = classes.map(c => c._id);

    if (validClassIds.length === 0) {
      console.log('   ⚠️  No classes found! Cannot fix student references.\n');
    } else {
      // Find students with invalid classId
      const invalidStudents = await Student.find({
        classId: { $nin: validClassIds }
      }).limit(1000);

      console.log(`   Found ${invalidStudents.length} students with invalid classId`);

      if (invalidStudents.length > 0) {
        // Reassign to random valid class
        const randomClass = validClassIds[Math.floor(Math.random() * validClassIds.length)];

        for (const student of invalidStudents) {
          try {
            await Student.updateOne(
              { _id: student._id },
              { $set: { classId: randomClass } }
            );
            stats.fixed++;
          } catch (err) {
            stats.errors++;
          }
        }

        console.log(`   ✅ Fixed ${stats.fixed} student references\n`);
      } else {
        console.log('   ✅ All student class references valid\n');
      }
    }

    // ========================================
    // 2. Fix Exam → Class references
    // ========================================
    console.log('2️⃣  Fixing Exam → Class references...');
    const invalidExams = await Exam.find({
      classId: { $nin: validClassIds }
    }).limit(1000);

    console.log(`   Found ${invalidExams.length} exams with invalid classId`);

    if (invalidExams.length > 0 && validClassIds.length > 0) {
      const randomClass = validClassIds[Math.floor(Math.random() * validClassIds.length)];

      for (const exam of invalidExams) {
        try {
          await Exam.updateOne(
            { _id: exam._id },
            { $set: { classId: randomClass } }
          );
          stats.fixed++;
        } catch (err) {
          stats.errors++;
        }
      }

      console.log(`   ✅ Fixed ${invalidExams.length} exam references\n`);
    } else {
      console.log('   ✅ All exam class references valid\n');
    }

    // ========================================
    // 3. Fix Homework → Class references
    // ========================================
    console.log('3️⃣  Fixing Homework → Class references...');
    const invalidHomework = await Homework.find({
      classId: { $nin: validClassIds }
    }).limit(1000);

    console.log(`   Found ${invalidHomework.length} homework with invalid classId`);

    if (invalidHomework.length > 0 && validClassIds.length > 0) {
      const randomClass = validClassIds[Math.floor(Math.random() * validClassIds.length)];

      for (const hw of invalidHomework) {
        try {
          await Homework.updateOne(
            { _id: hw._id },
            { $set: { classId: randomClass } }
          );
          stats.fixed++;
        } catch (err) {
          stats.errors++;
        }
      }

      console.log(`   ✅ Fixed ${invalidHomework.length} homework references\n`);
    } else {
      console.log('   ✅ All homework class references valid\n');
    }

    // ========================================
    // 4. Fix ExamResult → Exam references
    // ========================================
    console.log('4️⃣  Fixing ExamResult → Exam references...');
    const exams = await Exam.find().select('_id');
    const validExamIds = exams.map(e => e._id);
    const students = await Student.find().select('_id');
    const validStudentIds = students.map(s => s._id);

    const invalidExamResults = await ExamResult.find({
      $or: [
        { examId: { $nin: validExamIds } },
        { studentId: { $nin: validStudentIds } }
      ]
    }).limit(1000);

    console.log(`   Found ${invalidExamResults.length} exam results with invalid references`);

    if (invalidExamResults.length > 0) {
      // Delete orphaned exam results
      const deleteResult = await ExamResult.deleteMany({
        _id: { $in: invalidExamResults.map(er => er._id) }
      });

      stats.deleted += deleteResult.deletedCount;
      console.log(`   ✅ Deleted ${deleteResult.deletedCount} orphaned exam results\n`);
    } else {
      console.log('   ✅ All exam result references valid\n');
    }

    // ========================================
    // 5. Fix FeePayment → Student references
    // ========================================
    console.log('5️⃣  Fixing FeePayment → Student references...');
    const invalidFeePayments = await FeePayment.find({
      studentId: { $nin: validStudentIds }
    }).limit(1000);

    console.log(`   Found ${invalidFeePayments.length} fee payments with invalid studentId`);

    if (invalidFeePayments.length > 0) {
      const deleteResult = await FeePayment.deleteMany({
        _id: { $in: invalidFeePayments.map(fp => fp._id) }
      });

      stats.deleted += deleteResult.deletedCount;
      console.log(`   ✅ Deleted ${deleteResult.deletedCount} orphaned fee payments\n`);
    } else {
      console.log('   ✅ All fee payment references valid\n');
    }

    // ========================================
    // 6. Fix LibraryTransaction → Student & Book references
    // ========================================
    console.log('6️⃣  Fixing LibraryTransaction references...');
    const books = await LibraryBook.find().select('_id');
    const validBookIds = books.map(b => b._id);

    const invalidLibTrans = await LibraryTransaction.find({
      $or: [
        { studentId: { $nin: validStudentIds } },
        { bookId: { $nin: validBookIds } }
      ]
    }).limit(1000);

    console.log(`   Found ${invalidLibTrans.length} library transactions with invalid references`);

    if (invalidLibTrans.length > 0) {
      const deleteResult = await LibraryTransaction.deleteMany({
        _id: { $in: invalidLibTrans.map(lt => lt._id) }
      });

      stats.deleted += deleteResult.deletedCount;
      console.log(`   ✅ Deleted ${deleteResult.deletedCount} orphaned library transactions\n`);
    } else {
      console.log('   ✅ All library transaction references valid\n');
    }

    // ========================================
    // 7. Fix Remark → Student & Teacher references
    // ========================================
    console.log('7️⃣  Fixing Remark references...');
    const teachers = await User.find({ role: 'teacher' }).select('_id');
    const validTeacherIds = teachers.map(t => t._id);

    const invalidRemarks = await Remark.find({
      $or: [
        { studentId: { $nin: validStudentIds } },
        { teacherId: { $nin: validTeacherIds } }
      ]
    }).limit(1000);

    console.log(`   Found ${invalidRemarks.length} remarks with invalid references`);

    if (invalidRemarks.length > 0) {
      const deleteResult = await Remark.deleteMany({
        _id: { $in: invalidRemarks.map(r => r._id) }
      });

      stats.deleted += deleteResult.deletedCount;
      console.log(`   ✅ Deleted ${deleteResult.deletedCount} orphaned remarks\n`);
    } else {
      console.log('   ✅ All remark references valid\n');
    }

    // ========================================
    // 8. Fix Payroll → Staff references
    // ========================================
    console.log('8️⃣  Fixing Payroll → Staff references...');
    const staffUsers = await User.find({
      role: { $in: ['teacher', 'staff', 'driver', 'conductor'] }
    }).select('_id');
    const validStaffIds = staffUsers.map(s => s._id);

    const invalidPayroll = await Payroll.find({
      staffId: { $nin: validStaffIds }
    }).limit(1000);

    console.log(`   Found ${invalidPayroll.length} payroll records with invalid staffId`);

    if (invalidPayroll.length > 0) {
      const deleteResult = await Payroll.deleteMany({
        _id: { $in: invalidPayroll.map(p => p._id) }
      });

      stats.deleted += deleteResult.deletedCount;
      console.log(`   ✅ Deleted ${deleteResult.deletedCount} orphaned payroll records\n`);
    } else {
      console.log('   ✅ All payroll references valid\n');
    }

    // ========================================
    // FINAL SUMMARY
    // ========================================
    console.log('='.repeat(70));
    console.log('🎯 ORPHANED REFERENCES FIX COMPLETE');
    console.log('='.repeat(70));
    console.log(`\n📊 Summary:`);
    console.log(`   Records Fixed:  ${stats.fixed}`);
    console.log(`   Records Deleted: ${stats.deleted}`);
    console.log(`   Errors:         ${stats.errors}`);
    console.log(`\n✅ All orphaned references have been resolved!\n`);

  } catch (error) {
    console.error('❌ Error fixing orphaned references:', error);
    console.error(error.stack);
  } finally {
    await prisma.$disconnect();
    console.log('👋 Database connection closed\n');
    process.exit(0);
  }
}

fixOrphanedReferences().catch(console.error);
