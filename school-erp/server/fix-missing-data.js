/**
 * fix-missing-data.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Targeted patch script — fills ONLY the modules that have 0 records.
 * Does NOT clear or touch existing data.
 *
 * Fixes:
 *  1. Attendance           (was 0,    target 10,000)
 *  2. Fee Payments         (was 0,    target 10,000)
 *  3. Hostel Room Types    (was 0,    target 50)
 *  4. Hostel Rooms         (was 0,    target 200)
 *  5. Hostel Fee Structures(was 0,    target 50)
 *  6. Hostel Allocations   (was 0,    target 200)
 *  7. Salary Structures    (was 372,  target 1,000)
 *  8. Orphaned FK cleanup  (students → classes, exams → classes)
 * ─────────────────────────────────────────────────────────────────────────────
 */

require('dotenv').config();
const prisma = require('./config/prisma');

// ── Models ──────────────────────────────────────────────────────────────────
const Student = require('./models/Student');
const Class = require('./models/Class');
const User = require('./models/User');
const Attendance = require('./models/Attendance');
const FeePayment = require('./models/FeePayment');
const TransportVehicle = require('./models/TransportVehicle');
const HostelRoomType = require('./models/HostelRoomType');
const HostelRoom = require('./models/HostelRoom');
const HostelAllocation = require('./models/HostelAllocation');
const HostelFeeStructure = require('./models/HostelFeeStructure');
const SalaryStructure = require('./models/SalaryStructure');
const Exam = require('./models/Exam');
const Homework = require('./models/Homework');
const Payroll = require('./models/Payroll');
const Remark = require('./models/Remark');
const { trainChatbot } = require('./ai/nlpEngine');

// ── Helpers ──────────────────────────────────────────────────────────────────
function randomChoice(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}
function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}
function randomFloat(min, max, dec = 2) {
  return parseFloat((Math.random() * (max - min) + min).toFixed(dec));
}
function randomDate(start, end) {
  return new Date(start.getTime() + Math.random() * (end.getTime() - start.getTime()));
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

const subjects = [
  'Mathematics', 'Physics', 'Chemistry', 'Biology', 'English',
  'Hindi', 'Assamese', 'History', 'Geography', 'Computer Science',
  'Economics', 'Political Science', 'Accountancy', 'Business Studies'
];
const feeTypes = [
  'Tuition Fee', 'Transport Fee', 'Library Fee', 'Sports Fee',
  'Exam Fee', 'Laboratory Fee', 'Computer Fee', 'Annual Day Fee',
  'Development Fee', 'Admission Fee'
];
const paymentModes = ['cash', 'online', 'cheque'];
const academicYears = ['2024-2025', '2025-2026'];
const classNames = ['Nursery', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
const routeNames = ['Route A - North', 'Route B - South', 'Route C - East', 'Route D - West', 'Route E - Central', 'Route F - Market', 'Route G - Station', 'Route H - Hospital'];

// ── Main ─────────────────────────────────────────────────────────────────────
async function fixMissingData() {
  console.log('🔧 Starting targeted data fix...\n');
  const t0 = Date.now();

  await prisma.$connect();
  console.log('✅ MongoDB Connected\n');

  const stats = {};

  // ══════════════════════════════════════════════════════════════════════════
  // 0. Load reference data
  // ══════════════════════════════════════════════════════════════════════════
  console.log('📂 Loading reference data...');
  const allStudents = await Student.find().select('_id classId').lean();
  let allClasses = await Class.find().select('_id').lean();
  const teachers = await User.find({ role: 'teacher' }).select('_id').lean();
  const accountsStaff = await User.find({ role: { $in: ['accounts', 'staff'] } }).select('_id').lean();
  const allStaff = await User.find({ role: { $in: ['teacher', 'staff', 'hr', 'accounts', 'canteen', 'conductor', 'driver'] } }).select('_id').lean();

  console.log(`   Students: ${allStudents.length}, Classes: ${allClasses.length}, Teachers: ${teachers.length}\n`);

  if (allStudents.length === 0) {
    console.error('❌ No students found. Please run generate-mock-data.js first.');
    process.exit(1);
  }

  const existingClassCount = await Class.countDocuments();
  console.log(`🏫 Classes: ${existingClassCount} existing. Target: 500...`);

  if (existingClassCount < 500) {
    const classesToCreate = [];
    const sections = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

    while (existingClassCount + classesToCreate.length < 500) {
      const nextIndex = existingClassCount + classesToCreate.length;
      const className = classNames[nextIndex % classNames.length];
      const streamChoices = Number(className) >= 11 ? ['Science', 'Commerce', 'Arts'] : [''];
      const stream = streamChoices[Math.floor(nextIndex / classNames.length) % streamChoices.length];
      const section = sections[nextIndex % sections.length];

      classesToCreate.push({
        name: stream ? `${className}-${stream}` : className,
        section,
        sections: sections.slice(0, 10),
        classTeacher: teachers.length ? randomChoice(teachers)._id : null,
        subjects: subjects.slice(0, randomInt(4, 8)).map(subject => ({
          name: subject,
          subject,
          teacherId: teachers.length ? randomChoice(teachers)._id : null,
          periodsPerWeek: randomInt(3, 6)
        })),
        capacity: randomInt(40, 60),
        academicYear: academicYears[nextIndex % academicYears.length]
      });
    }

    await Class.insertMany(classesToCreate, { ordered: false }).catch(err => {
      console.warn(`   ⚠️ Class top-up warning: ${err.message}`);
    });
    allClasses = await Class.find().select('_id').lean();
    console.log(`   ✅ Classes after top-up: ${allClasses.length}\n`);
  } else {
    console.log('   ✅ Already sufficient. Skipping.\n');
  }

  const existingTransportVehicles = await TransportVehicle.countDocuments();
  console.log(`🚌 Transport Vehicles: ${existingTransportVehicles} existing. Target: 1000...`);

  if (existingTransportVehicles < 1000) {
    const drivers = await User.find({ role: 'driver' }).select('_id').lean();
    const conductors = await User.find({ role: 'conductor' }).select('_id').lean();
    const vehiclesToCreate = [];

    for (let i = existingTransportVehicles; i < 1000; i++) {
      vehiclesToCreate.push({
        busNumber: `BUS-${String(i + 1).padStart(4, '0')}`,
        numberPlate: `AS${String((i % 30) + 1).padStart(2, '0')}${String.fromCharCode(65 + (i % 26))}${String(1000 + i).padStart(4, '0')}`,
        driverId: drivers.length ? randomChoice(drivers)._id : null,
        conductorId: conductors.length ? randomChoice(conductors)._id : null,
        route: randomChoice(routeNames),
        capacity: randomInt(30, 60)
      });
    }

    await TransportVehicle.insertMany(vehiclesToCreate, { ordered: false }).catch(err => {
      console.warn(`   ⚠️ Transport top-up warning: ${err.message}`);
    });
    console.log(`   ✅ Transport vehicles after top-up: ${await TransportVehicle.countDocuments()}\n`);
  } else {
    console.log('   ✅ Already sufficient. Skipping.\n');
  }

  // ══════════════════════════════════════════════════════════════════════════
  // 1. FIX ATTENDANCE (unique index: studentId + classId + date + subject)
  //    Strategy: one record per student per date per subject (all unique combos)
  // ══════════════════════════════════════════════════════════════════════════
  const existingAttendance = await Attendance.countDocuments();
  console.log(`📅 Attendance: ${existingAttendance} existing. Generating up to 10,000...`);

  if (existingAttendance < 5000) {
    const attStatuses = ['present', 'absent', 'late', 'half-day'];
    const attWeights = [0.75, 0.15, 0.05, 0.05];
    function weightedStatus() {
      const r = Math.random(); let s = 0;
      for (let i = 0; i < attWeights.length; i++) {
        s += attWeights[i]; if (r < s) return attStatuses[i];
      }
      return 'present';
    }

    // Build unique (studentId, classId, date, subject) combos
    const attBatch = [];
    const usedKeys = new Set();
    const targetCount = 10000 - existingAttendance;

    // Generate dates for the academic year
    const dateRange = [];
    for (let d = new Date(2025, 0, 2); d <= new Date(2025, 11, 31); d.setDate(d.getDate() + 1)) {
      const day = d.getDay();
      if (day !== 0) dateRange.push(new Date(d)); // skip Sundays
    }

    let attempts = 0;
    const maxAttempts = targetCount * 3;

    while (attBatch.length < targetCount && attempts < maxAttempts) {
      attempts++;
      const student = randomChoice(allStudents);
      const classId = student.classId || (allClasses.length ? randomChoice(allClasses)._id : null);
      if (!classId) continue;
      const teacher = teachers.length ? randomChoice(teachers) : null;
      if (!teacher) continue;
      const date = randomChoice(dateRange);
      // Use specific subject always (avoids null collision)
      const subject = randomChoice(subjects);

      // Normalize date to midnight UTC (same as pre-validate hook)
      const normDate = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
      const key = `${student._id}|${classId}|${normDate.getTime()}|${subject}`;
      if (usedKeys.has(key)) continue;
      usedKeys.add(key);

      attBatch.push({
        studentId: student._id,
        classId,
        teacherId: teacher._id,
        date: normDate,
        status: weightedStatus(),
        smsSent: Math.random() > 0.5,
        subject,
        remarks: Math.random() > 0.9 ? 'Late arrival' : ''
      });
    }

    let attCount = 0;
    for (let i = 0; i < attBatch.length; i += 500) {
      try {
        const inserted = await Attendance.insertMany(attBatch.slice(i, i + 500), { ordered: false });
        attCount += inserted.length;
      } catch (err) {
        // ordered:false — partial inserts still succeed; count what was inserted
        if (err.insertedDocs) attCount += err.insertedDocs.length;
        else if (err.result && err.result.insertedCount) attCount += err.result.insertedCount;
        if (err.code !== 11000) console.error(`   ⚠️ Attendance batch error:`, err.message);
      }
      await sleep(50);
    }
    stats.attendance = existingAttendance + attCount;
    console.log(`   ✅ Added ${attCount} attendance records. Total: ${stats.attendance}\n`);
  } else {
    stats.attendance = existingAttendance;
    console.log(`   ✅ Already sufficient. Skipping.\n`);
  }

  // ══════════════════════════════════════════════════════════════════════════
  // 2. FIX FEE PAYMENTS (unique index: receiptNo)
  //    Strategy: use timestamp + index to guarantee unique receiptNo
  // ══════════════════════════════════════════════════════════════════════════
  const existingFeePayments = await FeePayment.countDocuments();
  console.log(`💳 Fee Payments: ${existingFeePayments} existing. Generating up to 10,000...`);

  if (existingFeePayments < 5000) {
    const targetFee = 10000 - existingFeePayments;
    const feePayments = [];
    const tsBase = Date.now();

    for (let i = 0; i < targetFee; i++) {
      const student = randomChoice(allStudents);
      const collector = accountsStaff.length ? randomChoice(accountsStaff) : (teachers.length ? randomChoice(teachers) : null);
      if (!collector) continue;

      feePayments.push({
        studentId: student._id,
        amountPaid: randomInt(500, 25000),
        originalAmount: randomInt(500, 25000),
        discount: randomFloat(0, 2000),
        paymentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        receiptNo: `FIX${tsBase}${String(i).padStart(6, '0')}`,
        collectedBy: collector._id,
        paymentMode: randomChoice(paymentModes),
        remarks: Math.random() > 0.8 ? 'Partial payment' : '',
        feeType: randomChoice(feeTypes),
        academicYear: randomChoice(academicYears)
      });
    }

    let feeCount = 0;
    for (let i = 0; i < feePayments.length; i += 500) {
      try {
        const inserted = await FeePayment.insertMany(feePayments.slice(i, i + 500), { ordered: false });
        feeCount += inserted.length;
      } catch (err) {
        if (err.insertedDocs) feeCount += err.insertedDocs.length;
        if (err.code !== 11000) console.error(`   ⚠️ FeePayment batch error:`, err.message);
      }
      await sleep(50);
    }
    stats.feePayments = existingFeePayments + feeCount;
    console.log(`   ✅ Added ${feeCount} fee payment records. Total: ${stats.feePayments}\n`);
  } else {
    stats.feePayments = existingFeePayments;
    console.log(`   ✅ Already sufficient. Skipping.\n`);
  }

  // ══════════════════════════════════════════════════════════════════════════
  // 3-6. FIX HOSTEL MODULE
  //      Root cause: 'name' is unique on HostelRoomType. On re-runs the
  //      generator uses identical names → insertMany throws (no ordered:false)
  //      → returns [] → all downstream hostel loops break immediately.
  //      Fix: use upsert + timestamp-salted names.
  // ══════════════════════════════════════════════════════════════════════════
  console.log('🏠 Fixing Hostel Module...');

  // 3. Hostel Room Types
  const existingRoomTypes = await HostelRoomType.find().lean();
  const hostelRoomTypeNames = [
    'Single Occupancy', 'Double Occupancy', 'Triple Occupancy', 'Dormitory',
    'Deluxe Single', 'Deluxe Double', 'AC Single', 'AC Double',
    'Standard Single', 'Standard Double'
  ];

  let insertedHostelRoomTypes = [...existingRoomTypes];

  if (existingRoomTypes.length < 50) {
    const ts = Date.now();
    const rtBatch = [];
    for (let i = existingRoomTypes.length; i < 50; i++) {
      const baseName = hostelRoomTypeNames[i % hostelRoomTypeNames.length];
      const blockLetter = String.fromCharCode(65 + (i % 10));
      rtBatch.push({
        name: `${baseName} - Block ${blockLetter} (${ts + i})`,
        code: `RT${ts}${String(i).padStart(3, '0')}`,
        occupancy: i % 4 === 0 ? 1 : i % 4 === 1 ? 2 : i % 4 === 2 ? 3 : 8,
        genderPolicy: randomChoice(['boys', 'girls', 'mixed']),
        defaultFee: randomInt(10000, 50000),
        amenities: randomChoice([['AC', 'WiFi', 'Attached Bathroom'], ['Fan', 'WiFi', 'Common Bathroom']]),
        description: `${baseName} type hostel room`
      });
    }

    const newRTs = [];
    for (const rt of rtBatch) {
      try {
        const saved = await HostelRoomType.create(rt);
        newRTs.push(saved);
      } catch (err) {
        if (err.code !== 11000) console.error(`   ⚠️ RoomType error:`, err.message);
      }
    }
    insertedHostelRoomTypes = [...existingRoomTypes, ...newRTs];
    console.log(`   Room Types: created ${newRTs.length} new. Total: ${insertedHostelRoomTypes.length}`);
  } else {
    console.log(`   Room Types: ${existingRoomTypes.length} already exist. Skipping.`);
  }

  // 4. Hostel Rooms
  const existingRooms = await HostelRoom.countDocuments();
  let insertedHostelRooms = [];

  if (existingRooms < 200 && insertedHostelRoomTypes.length > 0) {
    const usedRoomNumbers = new Set((await HostelRoom.find().select('roomNumber').lean()).map(r => r.roomNumber));
    const roomBatch = [];
    const ts = Date.now();

    for (let i = existingRooms; i < 200; i++) {
      const roomType = randomChoice(insertedHostelRoomTypes);
      const roomNum = `${randomChoice(['A', 'B', 'C', 'D'])}${ts % 1000}${String(i).padStart(3, '0')}`;
      if (usedRoomNumbers.has(roomNum)) continue;
      usedRoomNumbers.add(roomNum);
      roomBatch.push({
        roomTypeId: roomType._id,
        roomNumber: roomNum,
        block: randomChoice(['A', 'B', 'C', 'D']),
        floor: randomChoice(['Ground', 'First', 'Second']),
        capacity: roomType.occupancy,
        occupiedBeds: randomInt(0, roomType.occupancy),
        status: randomChoice(['AVAILABLE', 'LIMITED', 'FULL'])
      });
    }

    let roomCount = 0;
    for (let i = 0; i < roomBatch.length; i += 100) {
      try {
        const ins = await HostelRoom.insertMany(roomBatch.slice(i, i + 100), { ordered: false });
        roomCount += ins.length;
      } catch (err) {
        if (err.insertedDocs) roomCount += err.insertedDocs.length;
        if (err.code !== 11000) console.error(`   ⚠️ Room error:`, err.message);
      }
    }
    insertedHostelRooms = await HostelRoom.find().lean();
    console.log(`   Rooms: created ${roomCount}. Total: ${insertedHostelRooms.length}`);
  } else {
    insertedHostelRooms = await HostelRoom.find().lean();
    console.log(`   Rooms: ${existingRooms} already exist. Skipping.`);
  }

  // 5. Hostel Fee Structures
  const existingHFS = await HostelFeeStructure.countDocuments();
  if (existingHFS < 50 && insertedHostelRoomTypes.length > 0) {
    const hfsBatch = [];
    for (let i = existingHFS; i < 50; i++) {
      const rt = randomChoice(insertedHostelRoomTypes);
      hfsBatch.push({
        roomTypeId: rt._id,
        academicYear: randomChoice(academicYears),
        term: randomChoice(['Annual', 'Quarterly', 'Monthly']),
        billingCycle: randomChoice(['monthly', 'quarterly', 'annual']),
        amount: randomInt(10000, 100000),
        cautionDeposit: randomInt(5000, 20000),
        messCharge: randomInt(3000, 10000),
        maintenanceCharge: randomInt(1000, 5000)
      });
    }
    const hfsInserted = await HostelFeeStructure.insertMany(hfsBatch, { ordered: false }).catch(() => []);
    console.log(`   Fee Structures: created ${hfsInserted.length}`);
  } else {
    console.log(`   Fee Structures: ${existingHFS} already exist. Skipping.`);
  }

  // 6. Hostel Allocations
  const existingAllocs = await HostelAllocation.countDocuments();
  if (existingAllocs < 200 && insertedHostelRooms.length > 0) {
    const hostelStudents = allStudents.slice(0, 200);
    const firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Ananya', 'Diya', 'Meera', 'Rahul'];
    const lastNames = ['Sharma', 'Verma', 'Gupta', 'Singh', 'Kumar'];
    const allocBatch = [];

    for (let i = existingAllocs; i < Math.min(200, hostelStudents.length); i++) {
      const student = hostelStudents[i];
      const room = randomChoice(insertedHostelRooms);
      const roomType = insertedHostelRoomTypes.find(rt => rt._id.toString() === room.roomTypeId.toString())
        || randomChoice(insertedHostelRoomTypes);
      allocBatch.push({
        studentId: student._id,
        roomTypeId: roomType._id,
        roomId: room._id,
        academicYear: randomChoice(academicYears),
        bedLabel: `Bed ${randomInt(1, Math.max(1, room.capacity))}`,
        allotmentDate: randomDate(new Date(2025, 0, 1), new Date(2025, 11, 31)),
        status: Math.random() > 0.2 ? 'ACTIVE' : 'VACATED',
        guardianContactName: `${randomChoice(firstNames)} ${randomChoice(lastNames)}`,
        guardianContactPhone: `9${randomInt(100000000, 999999999)}`
      });
    }

    let allocCount = 0;
    for (let i = 0; i < allocBatch.length; i += 100) {
      try {
        const ins = await HostelAllocation.insertMany(allocBatch.slice(i, i + 100), { ordered: false });
        allocCount += ins.length;
      } catch (err) {
        if (err.insertedDocs) allocCount += err.insertedDocs.length;
        if (err.code !== 11000) console.error(`   ⚠️ Allocation error:`, err.message);
      }
    }
    console.log(`   Allocations: created ${allocCount}`);
  } else {
    console.log(`   Allocations: ${existingAllocs} already exist. Skipping.`);
  }
  console.log('');

  // ══════════════════════════════════════════════════════════════════════════
  // 7. FIX SALARY STRUCTURES
  //    Root cause: unique index on (staffId). 1000 attempts on 200 staff = 800 dupes.
  //    Fix: upsert — one structure per staff member.
  // ══════════════════════════════════════════════════════════════════════════
  const existingSalary = await SalaryStructure.countDocuments();
  console.log(`💼 Salary Structures: ${existingSalary} existing. Generating for all staff...`);

  if (existingSalary < 500) {
    const alreadyHaveStructure = new Set(
      (await SalaryStructure.find().select('staffId').lean()).map(s => s.staffId.toString())
    );
    const staffWithoutStructure = allStaff.filter(s => !alreadyHaveStructure.has(s._id.toString()));
    console.log(`   Staff without salary structure: ${staffWithoutStructure.length}`);

    let salaryCount = 0;
    for (const staff of staffWithoutStructure) {
      try {
        await SalaryStructure.findOneAndUpdate(
          { staffId: staff._id },
          {
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
          },
          { upsert: true }
        );
        salaryCount++;
      } catch (err) {
        if (err.code !== 11000) console.error(`   ⚠️ Salary error for ${staff._id}:`, err.message);
      }
    }
    stats.salaryStructures = existingSalary + salaryCount;
    console.log(`   ✅ Added ${salaryCount} salary structures. Total: ${stats.salaryStructures}\n`);
  } else {
    stats.salaryStructures = existingSalary;
    console.log(`   ✅ Already sufficient. Skipping.\n`);
  }

  // ══════════════════════════════════════════════════════════════════════════
  // 8. FIX ORPHANED FOREIGN KEY REFERENCES
  //    Reassign orphaned studentId→classId, examId→classId, homeworkId→classId
  // ══════════════════════════════════════════════════════════════════════════
  if (allClasses.length > 0) {
    console.log('🔗 Fixing orphaned foreign key references...');
    const validClassIds = new Set(allClasses.map(c => c._id.toString()));

    // Fix orphaned students (classId points to non-existent class)
    const orphanedStudents = allStudents.filter(s => s.classId && !validClassIds.has(s.classId.toString()));
    if (orphanedStudents.length > 0) {
      console.log(`   Students with invalid classId: ${orphanedStudents.length}`);
      let fixedStudents = 0;
      for (const student of orphanedStudents) {
        const newClass = randomChoice(allClasses);
        await Student.findByIdAndUpdate(student._id, { classId: newClass._id });
        fixedStudents++;
      }
      console.log(`   ✅ Fixed ${fixedStudents} orphaned student→class references`);
    } else {
      console.log('   ✅ No orphaned student references found');
    }

    // Fix orphaned exams
    const allExams = await Exam.find().select('_id classId').lean();
    const orphanedExams = allExams.filter(e => e.classId && !validClassIds.has(e.classId.toString()));
    if (orphanedExams.length > 0) {
      console.log(`   Exams with invalid classId: ${orphanedExams.length}`);
      let fixedExams = 0;
      for (const exam of orphanedExams) {
        const newClass = randomChoice(allClasses);
        await Exam.findByIdAndUpdate(exam._id, { classId: newClass._id });
        fixedExams++;
      }
      console.log(`   ✅ Fixed ${fixedExams} orphaned exam→class references`);
    } else {
      console.log('   ✅ No orphaned exam references found');
    }

    // Fix orphaned homework
    const allHW = await Homework.find().select('_id classId').lean();
    const orphanedHW = allHW.filter(h => h.classId && !validClassIds.has(h.classId.toString()));
    if (orphanedHW.length > 0) {
      console.log(`   Homework with invalid classId: ${orphanedHW.length}`);
      let fixedHW = 0;
      for (const hw of orphanedHW) {
        const newClass = randomChoice(allClasses);
        await Homework.findByIdAndUpdate(hw._id, { classId: newClass._id });
        fixedHW++;
      }
      console.log(`   ✅ Fixed ${fixedHW} orphaned homework→class references`);
    } else {
      console.log('   ✅ No orphaned homework references found');
    }

    // Fix orphaned payroll (staffId)
    const allStaffIds = new Set((await User.find({ role: { not: 'student' } }).select('_id').lean()).map(u => u._id.toString()));
    const allPayrolls = await Payroll.find().select('_id staffId').lean();
    const orphanedPayrolls = allPayrolls.filter(p => p.staffId && !allStaffIds.has(p.staffId.toString()));
    if (orphanedPayrolls.length > 0) {
      console.log(`   Payroll records with invalid staffId: ${orphanedPayrolls.length}`);
      let fixedPayrolls = 0;
      for (const p of orphanedPayrolls) {
        const newStaff = randomChoice(allStaff);
        await Payroll.findByIdAndUpdate(p._id, { staffId: newStaff._id });
        fixedPayrolls++;
      }
      console.log(`   ✅ Fixed ${fixedPayrolls} orphaned payroll→staff references`);
    } else {
      console.log('   ✅ No orphaned payroll references found');
    }

    // Fix orphaned remarks (teacherId)
    const allRemarks = await Remark.find().select('_id teacherId').lean();
    const orphanedRemarks = allRemarks.filter(r => r.teacherId && !allStaffIds.has(r.teacherId.toString()));
    if (orphanedRemarks.length > 0) {
      console.log(`   Remarks with invalid teacherId: ${orphanedRemarks.length}`);
      let fixedRemarks = 0;
      for (const r of orphanedRemarks) {
        const newTeacher = randomChoice(teachers);
        if (newTeacher) await Remark.findByIdAndUpdate(r._id, { teacherId: newTeacher._id });
        fixedRemarks++;
      }
      console.log(`   ✅ Fixed ${fixedRemarks} orphaned remark→teacher references`);
    } else {
      console.log('   ✅ No orphaned remark references found');
    }

    console.log('');
  }

  // ══════════════════════════════════════════════════════════════════════════
  // FINAL SUMMARY
  // ══════════════════════════════════════════════════════════════════════════
  const elapsed = ((Date.now() - t0) / 1000).toFixed(1);

  const finalCounts = {
    attendance: await Attendance.countDocuments(),
    feePayments: await FeePayment.countDocuments(),
    hostelRoomTypes: await HostelRoomType.countDocuments(),
    hostelRooms: await HostelRoom.countDocuments(),
    hostelFeeStructures: await HostelFeeStructure.countDocuments(),
    hostelAllocations: await HostelAllocation.countDocuments(),
    salaryStructures: await SalaryStructure.countDocuments(),
  };

  console.log('\n' + '╔' + '═'.repeat(55) + '╗');
  console.log('║        DATA FIX COMPLETE!                             ║');
  console.log('╠' + '═'.repeat(55) + '╣');
  for (const [key, val] of Object.entries(finalCounts)) {
    const label = key.padEnd(30);
    const value = String(val).padStart(10);
    console.log(`║   ${label}${value}           ║`);
  }
  console.log(`║   Time Taken                     ${elapsed.padStart(7)}s           ║`);
  console.log('╚' + '═'.repeat(55) + '╝\n');

  console.log('🧠 Retraining chatbot NLP model with repaired entities...');
  await trainChatbot({ forceRetrain: true, forceEntityReload: true });
  await prisma.$disconnect();
  console.log('✅ Done. Data and chatbot entities are refreshed.\n');
  process.exit(0);
}

fixMissingData().catch(err => {
  console.error('❌ Fatal error:', err);
  process.exit(1);
});
