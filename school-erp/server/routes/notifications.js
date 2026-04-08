const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');
const { toLegacyNotification } = require('../utils/prismaCompat');

router.get('/unread-count', auth, async (req, res) => {
  try {
    const count = await prisma.notification.count({
      where: {
        recipientId: req.user.id,
        read: false,
      },
    });

    res.json({ count });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.put('/read-all', auth, async (req, res) => {
  try {
    await prisma.notification.updateMany({
      where: {
        recipientId: req.user.id,
        read: false,
      },
      data: {
        read: true,
      },
    });

    res.json({ msg: 'All marked as read' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/', auth, async (req, res) => {
  try {
    const { page, limit } = parsePaginationParams(req.query);

    const [total, notifications] = await Promise.all([
      prisma.notification.count({
        where: { recipientId: req.user.id },
      }),
      prisma.notification.findMany({
        where: { recipientId: req.user.id },
        include: {
          sender: {
            select: {
              id: true,
              name: true,
              role: true,
              email: true,
              phone: true,
              employeeId: true,
              department: true,
              designation: true,
              isActive: true,
              createdAt: true,
              updatedAt: true,
            },
          },
        },
        orderBy: { createdAt: 'desc' },
        skip: (page - 1) * limit,
        take: limit || 50,
      }),
    ]);

    res.json({
      data: notifications.map(toLegacyNotification),
      pagination: getPaginationData(page, limit || 50, total),
      meta: {
        query: {
          page,
          limit: limit || 50,
          sort: '-createdAt',
        },
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.put('/:id/read', auth, async (req, res) => {
  try {
    const notification = await prisma.notification.findFirst({
      where: {
        id: req.params.id,
        recipientId: req.user.id,
      },
    });

    if (!notification) {
      return res.status(404).json({ msg: 'Notification not found' });
    }

    const updated = await prisma.notification.update({
      where: { id: req.params.id },
      include: {
        sender: {
          select: {
            id: true,
            name: true,
            role: true,
            email: true,
            phone: true,
            employeeId: true,
            department: true,
            designation: true,
            isActive: true,
            createdAt: true,
            updatedAt: true,
          },
        },
      },
      data: { read: true },
    });

    res.json(toLegacyNotification(updated));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
