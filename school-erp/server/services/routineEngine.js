const prisma = require('../config/prisma');
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const periods = ['8:00', '8:45', '9:30', '10:15', '11:00', '11:45', '12:30', '1:15'];

// Concurrency lock to prevent race conditions
let isGenerating = false;

function makeSlot(day, period) {
  return `${day}-${period}`;
}

function normalizeTeacherId(id) {
  if (id == null) return null;
  return String(id);
}

function normalizeRoutineEntry(subject) {
  return {
    name: subject.name || subject.subject || 'Subject',
    subject: subject.subject || subject.name || 'Subject',
    teacherId: normalizeTeacherId(subject.teacherId) || normalizeTeacherId(subject.teacherId?.id) || null,
    teacherName: subject.teacher?.name || '',
  };
}

async function buildRoutine() {
  if (isGenerating) {
    throw new Error('Routine generation is already in progress. Please wait for the current generation to complete.');
  }

  isGenerating = true;

  try {
    const classes = await prisma.class.findMany({
      include: {
        subjects: { include: { teacher: true } },
      },
    });

    const existingRoutines = await prisma.routine.findMany();
    const teacherBusy = {};
    const routines = [];

    // Build teacher busy map with normalized IDs
    for (const r of existingRoutines) {
      if (!r.timetable) continue;
      for (const [slot, entry] of Object.entries(r.timetable)) {
        const tid = normalizeTeacherId(entry.teacherId);
        if (tid) {
          if (!teacherBusy[tid]) teacherBusy[tid] = new Set();
          teacherBusy[tid].add(slot);
        }
      }
    }

    for (const cls of classes) {
      const classGrid = {};
      const subjectDayUsage = {};
      const existing = existingRoutines.find(r => String(r.classId) === String(cls.id));

      // Preserve existing valid entries
      if (existing && existing.timetable) {
        for (const [slot, entry] of Object.entries(existing.timetable)) {
          classGrid[slot] = entry;
          const entryName = entry.subject || entry.name || 'Subject';
          if (!subjectDayUsage[entryName]) subjectDayUsage[entryName] = new Set();
          const day = slot.split('-')[0];
          subjectDayUsage[entryName].add(day);
        }
      }

      for (const subject of cls.subjects) {
        const subjectKey = subject.subject || subject.name || 'Subject';

        let assigned = 0;
        if (existing && existing.timetable) {
          assigned = Object.values(existing.timetable).filter(
            e => (e.subject || e.name || 'Subject') === subjectKey
          ).length;
        }

        const needed = subject.periodsPerWeek || 4;

        if (!subjectDayUsage[subjectKey]) {
          subjectDayUsage[subjectKey] = new Set();
        }

        // Distribute periods across days evenly
        const daysPerPeriod = Math.floor(days.length / needed) || 1;
        let dayAssignments = 0;

        for (const day of days) {
          if (assigned >= needed) break;

          // Skip if this subject already has enough periods on this day (max 2 per day)
          const periodsOnDay = Object.entries(classGrid)
            .filter(([slot, entry]) => {
              const slotDay = slot.split('-')[0];
              return slotDay === day && (entry.subject || entry.name || 'Subject') === subjectKey;
            }).length;

          if (periodsOnDay >= 2) continue;

          for (const period of periods) {
            if (assigned >= needed) break;

            const slot = makeSlot(day, period);
            const tid = normalizeTeacherId(subject.teacherId) || normalizeTeacherId(subject.teacher?.id);

            // Check if slot is free and teacher is not busy
            if (!classGrid[slot] && (!tid || !teacherBusy[tid]?.has(slot))) {
              classGrid[slot] = normalizeRoutineEntry(subject);
              subjectDayUsage[subjectKey].add(day);
              if (tid) {
                if (!teacherBusy[tid]) teacherBusy[tid] = new Set();
                teacherBusy[tid].add(slot);
              }
              assigned++;
            }
          }
        }
      }

      // Validate timetable completeness before saving
      const totalPeriods = Object.keys(classGrid).length;
      if (totalPeriods === 0) {
        console.warn(`Warning: Generated empty timetable for class ${cls.id}`);
        continue;
      }

      await prisma.routine.upsert({
        where: { classId: cls.id },
        update: { timetable: classGrid },
        create: { classId: cls.id, timetable: classGrid },
      });
      routines.push({ classId: cls.id, timetable: classGrid });
    }

    return routines;
  } catch (error) {
    console.error('Error in routine generation:', error);
    throw error;
  } finally {
    isGenerating = false;
  }
}

module.exports = buildRoutine;
module.exports.days = days;
module.exports.periods = periods;
module.exports.makeSlot = makeSlot;
