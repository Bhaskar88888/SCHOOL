const cron = require('node-cron');
const prisma = require('./config/prisma');

// Helper to send a notification
async function sendNotification(recipientId, senderId, title, message, type, relatedEntityId = null) {
  try {
    await prisma.notification.create({
      data: { recipientId, senderId, title, message, type, relatedEntityId, read: false },
    });
  } catch (err) {
    console.error('Error sending notification:', err);
  }
}

// ── Job 1: Absent Notification Reminder @ 10:00 AM Mon-Sat ──
cron.schedule('0 10 * * 1-6', async () => {
  console.log('Running Job: Absent Notification Reminder');
  try {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    const students = await prisma.student.findMany({
      where: { parentUserId: { not: null } },
      select: { id: true, name: true, parentUserId: true },
    });
    
    for (const student of students) {
      const record = await prisma.attendance.findFirst({
        where: {
          studentId: student.id,
          date: { gte: today, lt: tomorrow },
        },
        select: { id: true },
      });
      if (!record) {
        await sendNotification(
          student.parentUserId,
          null, // System generated
          'Attendance Not Marked',
          `Attendance for ${student.name} has not been recorded today. Please contact the school if this is unexpected.`,
          'attendance_alert'
        );
      }
    }
  } catch (err) {
    console.error('Job 1 Error:', err);
  }
});

// ── Job 2: Homework Due Tomorrow @ 7:00 PM Mon-Sat ──
cron.schedule('0 19 * * 1-6', async () => {
  console.log('Running Job: Homework Due Tomorrow Reminder');
  try {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 0, 0);
    const dayAfter = new Date(tomorrow);
    dayAfter.setDate(dayAfter.getDate() + 1);

    const homeworks = await prisma.homework.findMany({
      where: { dueDate: { gte: tomorrow, lt: dayAfter } },
      select: { id: true, subject: true, title: true, classId: true, teacherId: true },
    });

    for (const hw of homeworks) {
      const students = await prisma.student.findMany({
        where: { classId: hw.classId, parentUserId: { not: null } },
        select: { parentUserId: true },
      });
      const parentIds = [...new Set(students.map(s => s.parentUserId).filter(Boolean))];
      
      for (const parentId of parentIds) {
        await sendNotification(
          parentId,
          hw.teacherId,
          'Homework Due Tomorrow',
          `Reminder: ${hw.subject} homework '${hw.title}' is due tomorrow.`,
          'homework'
        );
      }
    }
  } catch (err) {
    console.error('Job 2 Error:', err);
  }
});

// ── Job 3: Fee Overdue Alert @ 9:00 AM on 1st of month ──
cron.schedule('0 9 1 * *', async () => {
  console.log('Running Job: Fee Overdue Alert');
  try {
    const today = new Date();
    const students = await prisma.student.findMany({
      where: { parentUserId: { not: null } },
      select: { id: true, name: true, parentUserId: true },
    });
    
    for (const student of students) {
      // Find fee payment for current month (simplified check based on creation date for this example)
      const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
      const payment = await prisma.feePayment.findFirst({ 
        where: { 
          studentId: student.id,
          createdAt: { gte: startOfMonth },
        },
        select: { id: true },
      });
      
      if (!payment) {
        await sendNotification(
          student.parentUserId,
          null,
          'Fee Due This Month',
          `Fee payment for ${student.name} has not been recorded for this month. Please contact the accounts office.`,
          'fee_alert'
        );
      }
    }
  } catch (err) {
    console.error('Job 3 Error:', err);
  }
});

// ── Job 4: Daily Attendance Summary @ 5:00 PM Mon-Sat ──
cron.schedule('0 17 * * 1-6', async () => {
  console.log('Running Job: Daily Attendance Summary');
  try {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    const records = await prisma.attendance.findMany({
      where: { date: { gte: today, lt: tomorrow } },
      select: { status: true },
    });
    
    const presentCount = records.filter(r => r.status === 'present').length;
    const absentCount = records.filter(r => r.status === 'absent').length;
    const lateCount = records.filter(r => r.status === 'late').length;

    const admins = await prisma.user.findMany({
      where: { role: 'superadmin' },
      select: { id: true },
    });
    
    for (const admin of admins) {
      await sendNotification(
        admin.id,
        null,
        `Daily Attendance Report — ${today.toISOString().split('T')[0]}`,
        `Present: ${presentCount} | Absent: ${absentCount} | Late: ${lateCount}`,
        'general'
      );
    }
  } catch (err) {
    console.error('Job 4 Error:', err);
  }
});

// ── Job 5: Bus Not Departed Alert @ 4:00 PM Mon-Sat ──
cron.schedule('0 16 * * 1-6', async () => {
  console.log('Running Job: Bus Not Departed Alert');
  try {
    const today = new Date().toISOString().split('T')[0];
    const buses = await prisma.transportVehicle.findMany({
      where: { conductorId: { not: null } },
      select: { id: true, busNumber: true, conductorId: true },
    });

    for (const bus of buses) {
      const attendance = await prisma.transportAttendance.findFirst({
        where: { busId: bus.id, date: today },
        select: { id: true },
      });
      if (!attendance) {
        await sendNotification(
          bus.conductorId,
          null,
          'Action Required: Boarding Not Started',
          `You haven't marked any students as boarded today for Bus ${bus.busNumber}. Please update attendance.`,
          'transport'
        );
      }
    }
  } catch (err) {
    console.error('Job 5 Error:', err);
  }
});

// ── Job 6: Nightly Cleanup @ 12:00 AM every day ──
cron.schedule('0 0 * * *', async () => {
  console.log('Running Job: Nightly Notification Cleanup');
  try {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    await prisma.notification.deleteMany({
      where: {
        read: true,
        createdAt: { lt: thirtyDaysAgo },
      },
    });

    const ninetyDaysAgo = new Date();
    ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
    await prisma.chatbotLog.deleteMany({
      where: { timestamp: { lt: ninetyDaysAgo } },
    });

    const oneYearAgo = new Date();
    oneYearAgo.setDate(oneYearAgo.getDate() - 365);
    await prisma.auditLog.deleteMany({
      where: { timestamp: { lt: oneYearAgo } },
    });
  } catch (err) {
    console.error('Job 6 Error:', err);
  }
});

console.log('✅ Automatic Scheduler initilized: 6 background jobs loaded.');
