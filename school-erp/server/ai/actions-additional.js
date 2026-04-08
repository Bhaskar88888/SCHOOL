// Additional Action Handlers for Chatbot
// These actions were missing from the original actions.js file
// Added: April 8, 2026 - Comprehensive fixes

const prisma = require('../config/prisma');

const additionalActions = {
  // ==================== Attendance Percentage ====================
  'attendance.percentage': async (entities, context) => {
    try {
      const userId = context.user?.id || context.user?._id;
      if (!userId) return { message: 'Please log in to check attendance.' };

      const studentRecords = await prisma.student.findMany({
        where: { OR: [{ userId }, { parentUserId: userId }] },
        select: { id: true, name: true },
      });

      if (studentRecords.length === 0) {
        return {
          message: '❌ No student records found for your account.\n\n💡 Please contact the school office to link your student account.',
          data: null
        };
      }

      const studentIds = studentRecords.map(s => s.id);
      const [total, present] = await Promise.all([
        prisma.attendance.count({ where: { studentId: { in: studentIds } } }),
        prisma.attendance.count({ where: { studentId: { in: studentIds }, status: 'present' } }),
      ]);

      const percentage = total > 0 ? Math.round((present / total) * 100) : 0;
      const status = percentage >= 75 ? '✅ Good' : '⚠️ Below required 75%';
      const emoji = percentage >= 90 ? '🌟' : percentage >= 75 ? '👍' : '⚠️';

      return {
        message: `${emoji} Attendance Summary:\n\n📊 ${studentRecords.map(s => s.name).join(', ')}\n✅ Present: ${present}/${total} days\n📈 Percentage: ${percentage}%\n${status}${percentage < 75 ? '\n\n⚠️ Warning: Minimum 75% attendance required for promotion!' : ''}`,
        data: { total, present, percentage }
      };
    } catch (err) {
      console.error('[Action Error] attendance.percentage:', err);
      return {
        message: '❌ Failed to fetch attendance data.\n\n💡 This might be because:\n- No attendance records exist yet\n- Database connection issue\n\nPlease try again later or contact support.'
      };
    }
  },

  // ==================== Canteen Recharge Wallet ====================
  'canteen.recharge': async (entities, context) => {
    try {
      const userId = context.user?.id || context.user?._id;
      if (!userId) return { message: 'Please log in to recharge wallet.' };

      const student = await prisma.student.findFirst({
        where: { OR: [{ userId }, { parentUserId: userId }] },
        select: { id: true, name: true, canteenBalance: true },
      });

      if (!student) {
        return {
          message: '❌ No student account found for wallet recharge.\n\n💡 Please contact the accounts office to set up your canteen account.',
          data: null
        };
      }

      const balance = student.canteenBalance || 0;
      const status = balance > 500 ? '✅ Sufficient' : balance > 100 ? '⚠️ Low' : '❌ Very Low';

      return {
        message: `💰 Wallet Balance\n\n👤 Student: ${student.name}\n💵 Balance: ₹${balance.toFixed(2)}\n${status}\n\n📝 To recharge:\n1. Visit school accounts office\n2. Pay via cash/UPI\n3. Balance updates instantly`,
        data: { balance, studentId: student.id }
      };
    } catch (err) {
      console.error('[Action Error] canteen.recharge:', err);
      return { message: '❌ Failed to fetch wallet balance. Please try again or contact support.' };
    }
  },

  // ==================== Complaint Form Step Handler ====================
  'complaint.new.step': async (message, context, user) => {
    const step = context.formData.step || 1;

    if (step === 1) {
      context.formData.subject = message;
      context.formData.step = 2;
      return {
        message: '📝 Please describe your complaint in detail:',
        data: { step: 2 }
      };
    }

    if (step === 2) {
      context.formData.description = message;
      context.formData.step = 3;
      return {
        message: '🏷️ Select type:\n1. teacher_to_parent\n2. parent_to_teacher\n3. student_to_admin\n4. general\n\nReply with number:',
        data: { step: 3 }
      };
    }

    if (step === 3) {
      const typeMap = { '1': 'teacher_to_parent', '2': 'parent_to_teacher', '3': 'student_to_admin', '4': 'general' };
      const complaintType = typeMap[message.trim()] || 'general';

      try {
        await prisma.complaint.create({
          data: {
            userId: user.id,
            type: complaintType,
            subject: context.formData.subject,
            description: context.formData.description,
            status: 'open',
            raisedByRole: user.role || 'student',
          }
        });

        context.activeForm = null;
        context.formData = {};

        return {
          message: '✅ Complaint submitted successfully!\n\n📋 You can check status anytime by asking "complaint status".\n⏱️ Usually resolved within 2-3 working days.',
          data: { success: true }
        };
      } catch (err) {
        console.error('[Action Error] complaint.create:', err);
        return { message: '❌ Failed to submit complaint. Please try again.' };
      }
    }

    return { message: 'I did not understand. Please follow the steps above.' };
  },
};

module.exports = additionalActions;
