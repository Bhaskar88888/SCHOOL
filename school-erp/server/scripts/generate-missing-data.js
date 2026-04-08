/**
 * Generate Missing Core Data
 * Week 1, Day 1-2: Task 1.2
 * 
 * Generates:
 * - 10,000 Student Attendance records
 * - 10,000 Fee Payment records
 * - 200 Hostel Rooms
 * - 200 Hostel Allocations
 * - 50 Hostel Fee Structures
 */

require('dotenv').config();
const prisma = require('../config/prisma');
const connectDB = require('../config/db');

const Student = require('../models/Student');
const Class = require('../models/Class');
const User = require('../models/User');
const Attendance = require('../models/Attendance');
const FeeStructure = require('../models/FeeStructure');
const FeePayment = require('../models/FeePayment');
const HostelRoom = require('../models/HostelRoom');
const HostelRoomType = require('../models/HostelRoomType');
const HostelAllocation = require('../models/HostelAllocation');
const HostelFeeStructure = require('../models/HostelFeeStructure');

connectDB();

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

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function generateMissingData() {
  console.log('\n' + '='.repeat(70));
  console.log('📝 GENERATING MISSING CORE DATA');
  console.log('='.repeat(70) + '\n');

  const stats = {};

  try {
    // Load existing data
    console.log('📦 Loading existing data...');
    const students = await Student.find().limit(5000);
    const classes = await Class.find().limit(100);
    const teachers = await User.find({ role: 'teacher' }).limit(1000);
    const accountsStaff = await User.find({ role: { $in: ['accounts', 'staff'] } }).limit(500);
    const hostelRoomTypes = await HostelRoomType.find().limit(50);

    console.log(`   ✅ Students: ${students.length}`);
    console.log(`   ✅ Classes: ${classes.length}`);
    console.log(`   ✅ Teachers: ${teachers.length}`);
    console.log(`   ✅ Accounts Staff: ${accountsStaff.length}`);
    console.log(`   ✅ Hostel Room Types: ${hostelRoomTypes.length}\n`);

    if (students.length === 0) {
      console.log('❌ No students found! Cannot generate dependent data.\n');
      process.exit(1);
    }

    // ========================================
    // 1. GENERATE STUDENT ATTENDANCE (10,000)
    // ========================================
    console.log('1️⃣  Generating 10,000 Student Attendance Records...');
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

    const attendanceRecords = [];
    for (let i = 0; i < 10000; i++) {
      const student = randomChoice(students);
      attendanceRecords.push({
        studentId: student._id,
        classId: student.classId,
        teacherId: randomChoice(teachers)._id,
        date: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        status: weightedRandom(statuses, attendanceWeights),
        smsSent: Math.random() > 0.5,
        subject: Math.random() > 0.5 ? randomChoice(['Mathematics', 'Physics', 'English', 'Hindi']) : null,
        remarks: ''
      });
    }

    let attendanceCount = 0;
    for (let i = 0; i < attendanceRecords.length; i += 1000) {
      try {
        const inserted = await Attendance.insertMany(
          attendanceRecords.slice(i, i + 1000),
          { ordered: false }
        );
        attendanceCount += inserted.length;
        console.log(`   Progress: ${attendanceCount}/10000`);
      } catch (err) {
        console.log(`   ⚠️  Batch insert failed at ${i}, retrying...`);
        const chunk = attendanceRecords.slice(i, i + 1000);
        for (const record of chunk) {
          try {
            await Attendance.create(record);
            attendanceCount++;
          } catch (e) {
            // Skip duplicates
          }
        }
      }
      await sleep(100);
    }

    stats.attendance = attendanceCount;
    console.log(`   ✅ Attendance Records Generated: ${stats.attendance}\n`);

    // ========================================
    // 2. GENERATE FEE STRUCTURES (1,000)
    // ========================================
    console.log('2️⃣  Generating 1,000 Fee Structures...');
    const feeTypes = ['Tuition Fee', 'Transport Fee', 'Library Fee', 'Sports Fee', 'Exam Fee', 'Laboratory Fee'];
    const academicYears = ['2024-2025', '2025-2026'];

    const feeStructures = [];
    for (let i = 0; i < 1000; i++) {
      const classDoc = randomChoice(classes);
      feeStructures.push({
        classId: classDoc._id,
        feeType: randomChoice(feeTypes),
        amount: randomInt(500, 25000),
        dueDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        term: randomChoice(['Annual', 'Quarterly', 'Monthly']),
        description: `Fee for ${randomChoice(feeTypes)}`,
        lateFee: randomInt(0, 500),
        type: randomChoice(['monthly', 'annual', 'exam']),
        academicYear: randomChoice(academicYears)
      });
    }

    let feeStructCount = 0;
    for (let i = 0; i < feeStructures.length; i += 500) {
      try {
        const inserted = await FeeStructure.insertMany(
          feeStructures.slice(i, i + 500),
          { ordered: false }
        );
        feeStructCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }

    stats.feeStructures = feeStructCount;
    console.log(`   ✅ Fee Structures Generated: ${stats.feeStructures}\n`);

    // ========================================
    // 3. GENERATE FEE PAYMENTS (10,000)
    // ========================================
    console.log('3️⃣  Generating 10,000 Fee Payment Records...');
    const paymentModes = ['cash', 'online', 'cheque'];
    const allFeeStructures = await FeeStructure.find().limit(500);

    const feePayments = [];
    for (let i = 0; i < 10000; i++) {
      const student = randomChoice(students);
      const feeStruct = allFeeStructures.length > 0 ? randomChoice(allFeeStructures) : null;

      feePayments.push({
        studentId: student._id,
        feeStructureId: feeStruct?._id || null,
        amountPaid: randomInt(500, 25000),
        originalAmount: randomInt(500, 25000),
        discount: randomFloat(0, 5000),
        paymentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        receiptNo: `REC${String(i + 1).padStart(8, '0')}`,
        collectedBy: randomChoice(accountsStaff)._id,
        paymentMode: randomChoice(paymentModes),
        remarks: Math.random() > 0.8 ? randomChoice(['Partial payment', 'Late payment', 'Discount applied', '']) : '',
        feeType: randomChoice(feeTypes),
        academicYear: randomChoice(academicYears)
      });
    }

    let feePayCount = 0;
    for (let i = 0; i < feePayments.length; i += 1000) {
      try {
        const inserted = await FeePayment.insertMany(
          feePayments.slice(i, i + 1000),
          { ordered: false }
        );
        feePayCount += inserted.length;
        console.log(`   Progress: ${feePayCount}/10000`);
      } catch (err) {
        // Handle duplicate receiptNo
        const chunk = feePayments.slice(i, i + 1000);
        for (const payment of chunk) {
          try {
            payment.receiptNo = `REC${String(randomInt(10000000, 99999999))}`;
            await FeePayment.create(payment);
            feePayCount++;
          } catch (e) {
            // Skip
          }
        }
      }
      await sleep(100);
    }

    stats.feePayments = feePayCount;
    console.log(`   ✅ Fee Payments Generated: ${stats.feePayments}\n`);

    // ========================================
    // 4. GENERATE HOSTEL ROOMS (200)
    // ========================================
    console.log('4️⃣  Generating 200 Hostel Rooms...');

    if (hostelRoomTypes.length === 0) {
      console.log('   ⚠️  No hostel room types found. Creating default types...\n');

      // Create default room types
      const defaultTypes = [
        { name: 'Single Occupancy', code: 'SINGLE', occupancy: 1, genderPolicy: 'mixed', defaultFee: 30000 },
        { name: 'Double Occupancy', code: 'DOUBLE', occupancy: 2, genderPolicy: 'mixed', defaultFee: 20000 },
        { name: 'Triple Occupancy', code: 'TRIPLE', occupancy: 3, genderPolicy: 'mixed', defaultFee: 15000 },
        { name: 'Dormitory', code: 'DORM', occupancy: 8, genderPolicy: 'mixed', defaultFee: 8000 }
      ];

      const insertedTypes = await HostelRoomType.insertMany(defaultTypes);
      console.log(`   ✅ Created ${insertedTypes.length} hostel room types\n`);

      hostelRoomTypes.push(...insertedTypes);
    }

    const hostelRooms = [];
    for (let i = 0; i < 200; i++) {
      const roomType = randomChoice(hostelRoomTypes);
      const capacity = roomType.occupancy || randomInt(1, 4);
      const occupied = randomInt(0, capacity);

      hostelRooms.push({
        roomTypeId: roomType._id,
        roomNumber: `${randomChoice(['A', 'B', 'C', 'D'])}${randomInt(100, 999)}`,
        block: randomChoice(['A', 'B', 'C', 'D']),
        floor: randomChoice(['Ground', 'First', 'Second', 'Third']),
        capacity,
        occupiedBeds: occupied,
        status: occupied === capacity ? 'FULL' : occupied === 0 ? 'AVAILABLE' : 'LIMITED',
        notes: ''
      });
    }

    let hostelRoomCount = 0;
    for (let i = 0; i < hostelRooms.length; i += 100) {
      try {
        const inserted = await HostelRoom.insertMany(
          hostelRooms.slice(i, i + 100),
          { ordered: false }
        );
        hostelRoomCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }

    stats.hostelRooms = hostelRoomCount;
    console.log(`   ✅ Hostel Rooms Generated: ${stats.hostelRooms}\n`);

    // ========================================
    // 5. GENERATE HOSTEL FEE STRUCTURES (50)
    // ========================================
    console.log('5️⃣  Generating 50 Hostel Fee Structures...');
    const hostelFeeStructures = [];
    const allHostelRoomTypes = await HostelRoomType.find();

    for (let i = 0; i < 50 && allHostelRoomTypes.length > 0; i++) {
      const roomType = randomChoice(allHostelRoomTypes);
      hostelFeeStructures.push({
        roomTypeId: roomType._id,
        academicYear: randomChoice(academicYears),
        term: randomChoice(['Annual', 'Quarterly', 'Monthly']),
        billingCycle: randomChoice(['monthly', 'quarterly', 'annual']),
        amount: randomInt(10000, 50000),
        cautionDeposit: randomInt(5000, 20000),
        messCharge: randomInt(3000, 10000),
        maintenanceCharge: randomInt(1000, 5000),
        notes: `Hostel fee for ${roomType.name}`
      });
    }

    let hostelFeeCount = 0;
    if (hostelFeeStructures.length > 0) {
      const inserted = await HostelFeeStructure.insertMany(hostelFeeStructures).catch(() => []);
      hostelFeeCount = inserted.length;
    }

    stats.hostelFeeStructures = hostelFeeCount;
    console.log(`   ✅ Hostel Fee Structures Generated: ${stats.hostelFeeStructures}\n`);

    // ========================================
    // 6. GENERATE HOSTEL ALLOCATIONS (200)
    // ========================================
    console.log('6️⃣  Generating 200 Hostel Allocations...');
    const hostelStudents = students.filter(s => s.hostelRequired).slice(0, 200);
    const allHostelRooms = await HostelRoom.find().limit(100);
    const allHostelFeeStructures = await HostelFeeStructure.find().limit(50);

    const hostelAllocations = [];
    for (let i = 0; i < Math.min(200, hostelStudents.length); i++) {
      if (allHostelRooms.length === 0 || allHostelRoomTypes.length === 0) break;

      const student = hostelStudents[i];
      const room = randomChoice(allHostelRooms);
      const roomType = allHostelRoomTypes.find(rt => rt._id.toString() === room.roomTypeId?.toString()) || randomChoice(allHostelRoomTypes);

      hostelAllocations.push({
        studentId: student._id,
        roomTypeId: roomType._id,
        roomId: room._id,
        feeStructureId: allHostelFeeStructures.length > 0 ? randomChoice(allHostelFeeStructures)._id : null,
        academicYear: randomChoice(academicYears),
        bedLabel: `Bed ${randomInt(1, room.capacity || 2)}`,
        allotmentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        status: Math.random() > 0.2 ? 'ACTIVE' : 'VACATED',
        guardianContactName: `${randomChoice(['Ramesh', 'Suresh', 'Priya', 'Kavita'])} ${randomChoice(['Sharma', 'Verma', 'Gupta', 'Singh'])}`,
        guardianContactPhone: `9${randomInt(100000000, 999999999)}`,
        notes: ''
      });
    }

    let hostelAllocCount = 0;
    for (let i = 0; i < hostelAllocations.length; i += 100) {
      try {
        const inserted = await HostelAllocation.insertMany(
          hostelAllocations.slice(i, i + 100),
          { ordered: false }
        );
        hostelAllocCount += inserted.length;
      } catch (err) { }
      await sleep(50);
    }

    stats.hostelAllocations = hostelAllocCount;
    console.log(`   ✅ Hostel Allocations Generated: ${stats.hostelAllocations}\n`);

    // ========================================
    // FINAL SUMMARY
    // ========================================
    console.log('='.repeat(70));
    console.log('🎯 MISSING DATA GENERATION COMPLETE');
    console.log('='.repeat(70));
    console.log('\n📊 Generated Records:');
    console.log(`   Student Attendance:      ${stats.attendance?.toLocaleString() || 0}`);
    console.log(`   Fee Structures:          ${stats.feeStructures?.toLocaleString() || 0}`);
    console.log(`   Fee Payments:            ${stats.feePayments?.toLocaleString() || 0}`);
    console.log(`   Hostel Rooms:            ${stats.hostelRooms?.toLocaleString() || 0}`);
    console.log(`   Hostel Fee Structures:   ${stats.hostelFeeStructures?.toLocaleString() || 0}`);
    console.log(`   Hostel Allocations:      ${stats.hostelAllocations?.toLocaleString() || 0}`);

    const total = Object.values(stats).reduce((sum, val) => sum + (val || 0), 0);
    console.log(`\n   TOTAL:                   ${total.toLocaleString()} records\n`);

  } catch (error) {
    console.error('❌ Error generating missing data:', error);
    console.error(error.stack);
  } finally {
    await prisma.$disconnect();
    console.log('👋 Database connection closed\n');
    process.exit(0);
  }
}

generateMissingData().catch(console.error);
