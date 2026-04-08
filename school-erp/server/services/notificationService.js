/**
 * Enhanced Notification Service
 * Handles in-app notifications, SMS, and push notifications
 */

const prisma = require('../config/prisma');
const { toLegacyNotification } = require('../utils/prismaCompat');

const userSelect = {
  id: true,
  name: true,
  email: true,
  phone: true,
  role: true,
  employeeId: true,
  department: true,
  designation: true,
  isActive: true,
  createdAt: true,
  updatedAt: true,
};

const studentInclude = {
  parentUser: {
    select: userSelect,
  },
};

const sendSMS = async (phone, message) => {
  try {
    if (!process.env.TWILIO_ACCOUNT_SID || !process.env.TWILIO_AUTH_TOKEN || !process.env.TWILIO_PHONE_NUMBER) {
      console.log('SMS not sent - Twilio not configured. Message:', message);
      return { success: false, reason: 'SMS not configured' };
    }

    const twilio = require('twilio');
    const client = twilio(process.env.TWILIO_ACCOUNT_SID, process.env.TWILIO_AUTH_TOKEN);

    await client.messages.create({
      body: message,
      from: process.env.TWILIO_PHONE_NUMBER,
      to: phone,
    });

    return { success: true };
  } catch (error) {
    console.error('SMS send error:', error.message);
    return { success: false, error: error.message };
  }
};

const createNotification = async ({ recipientId, senderId, title, message, type, relatedEntityId }) => {
  try {
    const notification = await prisma.notification.create({
      data: {
        recipientId,
        senderId,
        title,
        message,
        type,
        relatedEntityId,
      },
      include: {
        sender: {
          select: userSelect,
        },
        recipient: {
          select: userSelect,
        },
      },
    });

    return toLegacyNotification(notification);
  } catch (error) {
    console.error('Error creating notification:', error);
    throw error;
  }
};

const notifyParentTransport = async ({ studentId, status, busNumber, driverName, markedBy }) => {
  try {
    const student = await prisma.student.findUnique({
      where: { id: studentId },
      include: studentInclude,
    });

    if (!student || !student.parentUser) {
      console.log('No parent found for student:', studentId);
      return;
    }

    const statusMessages = {
      boarded: {
        title: 'Student Boarded Bus',
        message: `${student.name} has boarded ${busNumber}. Driver: ${driverName}. Safe travels!`,
        sms: `${student.name} boarded ${busNumber} at ${new Date().toLocaleTimeString()}. Driver: ${driverName}.`,
      },
      dropped_off: {
        title: 'Student Dropped Safely',
        message: `${student.name} has been safely dropped off from ${busNumber}.`,
        sms: `${student.name} dropped off from ${busNumber} at ${new Date().toLocaleTimeString()}.`,
      },
      absent: {
        title: 'Student Marked Absent',
        message: `${student.name} was marked absent for ${busNumber} today.`,
        sms: `${student.name} marked absent for ${busNumber} today.`,
      },
    };

    const notificationData = statusMessages[status] || statusMessages.boarded;

    await createNotification({
      recipientId: student.parentUser.id,
      senderId: markedBy,
      title: notificationData.title,
      message: notificationData.message,
      type: 'transport',
      relatedEntityId: studentId,
    });

    const parentPhone = student.parentPhone || student.parentUser.phone;
    if (parentPhone) {
      await sendSMS(parentPhone, notificationData.sms);
    }

    return { success: true };
  } catch (error) {
    console.error('Error notifying parent about transport:', error);
    throw error;
  }
};

const notifyParentAttendance = async ({ studentId, status, className, markedBy }) => {
  try {
    const student = await prisma.student.findUnique({
      where: { id: studentId },
      include: studentInclude,
    });

    if (!student || !student.parentUser) {
      console.log('No parent found for student:', studentId);
      return;
    }

    const statusMessages = {
      present: {
        title: 'Student Present',
        message: `${student.name} is present in ${className} today.`,
        sms: `${student.name} is present in ${className} today.`,
      },
      absent: {
        title: 'Student Absent',
        message: `${student.name} is absent from ${className} today.`,
        sms: `${student.name} is absent from ${className} today. Please inform the school.`,
      },
      late: {
        title: 'Student Late',
        message: `${student.name} arrived late to ${className} today.`,
        sms: `${student.name} arrived late to ${className} today.`,
      },
      'half-day': {
        title: 'Student Half-Day',
        message: `${student.name} is on half-day leave from ${className}.`,
        sms: `${student.name} is on half-day leave from ${className} today.`,
      },
    };

    const notificationData = statusMessages[status] || statusMessages.present;

    await createNotification({
      recipientId: student.parentUser.id,
      senderId: markedBy,
      title: notificationData.title,
      message: notificationData.message,
      type: 'attendance_alert',
      relatedEntityId: studentId,
    });

    const parentPhone = student.parentPhone || student.parentUser.phone;
    if (parentPhone) {
      await sendSMS(parentPhone, notificationData.sms);
    }

    return { success: true };
  } catch (error) {
    console.error('Error notifying parent about attendance:', error);
    throw error;
  }
};

const notifyParentFee = async ({ studentId, amount, receiptNo, collectedBy }) => {
  try {
    const student = await prisma.student.findUnique({
      where: { id: studentId },
      include: studentInclude,
    });

    if (!student || !student.parentUser) {
      console.log('No parent found for student:', studentId);
      return;
    }

    const title = 'Fee Payment Received';
    const message = `Fee of Rs. ${amount} received for ${student.name}. Receipt No: ${receiptNo}. Thank you!`;

    await createNotification({
      recipientId: student.parentUser.id,
      senderId: collectedBy,
      title,
      message,
      type: 'fee_alert',
      relatedEntityId: studentId,
    });

    const parentPhone = student.parentPhone || student.parentUser.phone;
    if (parentPhone) {
      await sendSMS(parentPhone, `Fee Rs.${amount} received for ${student.name}. Receipt: ${receiptNo}. - ${process.env.SCHOOL_NAME || 'School'}`);
    }

    return { success: true };
  } catch (error) {
    console.error('Error notifying parent about fee:', error);
    throw error;
  }
};

const getUnreadNotifications = async (userId) => {
  try {
    const notifications = await prisma.notification.findMany({
      where: {
        recipientId: userId,
        read: false,
      },
      include: {
        sender: {
          select: userSelect,
        },
      },
      orderBy: { createdAt: 'desc' },
      take: 50,
    });

    return notifications.map(toLegacyNotification);
  } catch (error) {
    console.error('Error getting unread notifications:', error);
    throw error;
  }
};

const getUserNotifications = async (userId, page = 1, limit = 20) => {
  try {
    const [notifications, total] = await Promise.all([
      prisma.notification.findMany({
        where: { recipientId: userId },
        include: {
          sender: {
            select: userSelect,
          },
        },
        orderBy: { createdAt: 'desc' },
        skip: (page - 1) * limit,
        take: limit,
      }),
      prisma.notification.count({
        where: { recipientId: userId },
      }),
    ]);

    return {
      notifications: notifications.map(toLegacyNotification),
      total,
      page,
      pages: Math.ceil(total / limit),
    };
  } catch (error) {
    console.error('Error getting notifications:', error);
    throw error;
  }
};

const markAsRead = async (notificationId) => {
  try {
    const notification = await prisma.notification.update({
      where: { id: notificationId },
      include: {
        sender: {
          select: userSelect,
        },
      },
      data: { read: true },
    });

    return toLegacyNotification(notification);
  } catch (error) {
    console.error('Error marking notification as read:', error);
    throw error;
  }
};

const markAllAsRead = async (userId) => {
  try {
    return prisma.notification.updateMany({
      where: {
        recipientId: userId,
        read: false,
      },
      data: { read: true },
    });
  } catch (error) {
    console.error('Error marking all as read:', error);
    throw error;
  }
};

const getNotificationStats = async (userId) => {
  try {
    const [unreadCount, totalCount] = await Promise.all([
      prisma.notification.count({
        where: {
          recipientId: userId,
          read: false,
        },
      }),
      prisma.notification.count({
        where: { recipientId: userId },
      }),
    ]);

    return {
      unread: unreadCount,
      total: totalCount,
      read: totalCount - unreadCount,
    };
  } catch (error) {
    console.error('Error getting notification stats:', error);
    throw error;
  }
};

module.exports = {
  createNotification,
  notifyParentTransport,
  notifyParentAttendance,
  notifyParentFee,
  getUnreadNotifications,
  getUserNotifications,
  markAsRead,
  markAllAsRead,
  getNotificationStats,
  sendSMS,
};
