const prisma = require('../config/prisma');

// Fix: Add mutex to prevent race conditions in nextSequence
const sequenceLocks = new Map();

async function nextSequence(name, year = new Date().getFullYear()) {
  const lockKey = `${name}_${year}`;

  // Acquire lock for this name/year combination
  while (sequenceLocks.has(lockKey)) {
    await new Promise(resolve => setTimeout(resolve, 50));
  }
  sequenceLocks.set(lockKey, true);

  try {
    // Fix: Use transaction with retry logic to prevent race conditions
    let retries = 3;
    while (retries > 0) {
      try {
        const counter = await prisma.counter.upsert({
          where: {
            name_year: { name, year },
          },
          update: {
            sequence: { increment: 1 },
          },
          create: {
            name,
            year,
            sequence: 1,
          },
        });
        return counter.sequence;
      } catch (error) {
        if (error.code === 'P2034' || error.code === 'P2002') {
          // Unique constraint violation or transaction conflict - retry
          retries--;
          await new Promise(resolve => setTimeout(resolve, 100 * (3 - retries)));
          continue;
        }
        throw error;
      }
    }
    throw new Error('Failed to generate sequence after retries');
  } finally {
    // Release lock
    sequenceLocks.delete(lockKey);
  }
}

function padSequence(value, length = 5) {
  return String(value).padStart(length, '0');
}

async function generateAdmissionNo(date = new Date()) {
  const year = date.getFullYear();
  const seq = await nextSequence('student_admission', year);
  return `ADM-${year}-${padSequence(seq)}`;
}

async function generateStudentId(date = new Date()) {
  const year = date.getFullYear();
  const seq = await nextSequence('studentId', year);
  return `STD/${year}/${padSequence(seq)}`;
}

async function generateStaffId(date = new Date()) {
  const year = date.getFullYear();
  const seq = await nextSequence('staffId', year);
  return `STAFF/${year}-${padSequence(seq)}`;
}

async function generateTeacherId(date = new Date()) {
  const year = date.getFullYear();
  const seq = await nextSequence('teacherId', year);
  return `TCH/${year}/${padSequence(seq)}`;
}

async function generateDriverId(date = new Date()) {
  const year = date.getFullYear();
  const seq = await nextSequence('driverId', year);
  return `DRV/${year}/${padSequence(seq)}`;
}

async function generateConductorId(date = new Date()) {
  const year = date.getFullYear();
  const seq = await nextSequence('conductorId', year);
  return `CND/${year}/${padSequence(seq)}`;
}

module.exports = {
  nextSequence,
  generateAdmissionNo,
  generateStudentId,
  generateEmployeeId: generateStaffId,
  generateStaffId,
  generateTeacherId,
  generateDriverId,
  generateConductorId,
};
