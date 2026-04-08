const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const { getTeacherClassIds, getStudentIdsForUser } = require('../utils/accessScope');

function getTodayRange() {
  const start = new Date();
  start.setHours(0, 0, 0, 0);
  const end = new Date(start);
  end.setDate(end.getDate() + 1);
  return { start, end };
}

function buildQuickActions(role) {
  const base = [
    { label: 'View Dashboard', path: '/dashboard', icon: 'D' },
    { label: 'Check Notices', path: '/notices', icon: 'N' },
  ];

  if (role === 'superadmin' || role === 'accounts') {
    base.push(
      { label: 'Collect Fee', path: '/fee', icon: 'F' },
      { label: 'Add Student', path: '/students', icon: 'S' }
    );
  } else if (role === 'teacher') {
    base.push(
      { label: 'Mark Attendance', path: '/attendance', icon: 'A' },
      { label: 'Assign Homework', path: '/homework', icon: 'H' }
    );
  } else if (role === 'student' || role === 'parent') {
    base.push(
      { label: 'View Homework', path: '/homework', icon: 'H' },
      { label: 'View Attendance', path: '/attendance', icon: 'A' }
    );
  } else {
    base.push(
      { label: 'Library', path: '/library', icon: 'L' },
      { label: 'Transport', path: '/transport', icon: 'T' }
    );
  }

  return base;
}

router.get('/stats', auth, async (req, res) => {
  try {
    const { start, end } = getTodayRange();
    const role = req.user.role;

    let studentIds = [];
    let classIds = [];

    if (role === 'teacher') {
      classIds = await getTeacherClassIds(req.user.id);
    } else if (role === 'student' || role === 'parent') {
      studentIds = await getStudentIdsForUser(req.user);
    }

    const studentFilter = studentIds.length ? { id: { in: studentIds } } : {};
    const attendanceFilter = studentIds.length
      ? { studentId: { in: studentIds } }
      : classIds.length
        ? { classId: { in: classIds } }
        : {};
    const paymentFilter = studentIds.length
      ? { studentId: { in: studentIds } }
      : {};
    const complaintFilter = studentIds.length
      ? { studentId: { in: studentIds } }
      : classIds.length
        ? { classId: { in: classIds } }
        : {};

    const [
      totalStudents,
      attendanceToday,
      feesCollectedAgg,
      openComplaints,
    ] = await Promise.all([
      prisma.student.count({ where: studentFilter }),
      prisma.attendance.count({
        where: {
          ...attendanceFilter,
          date: { gte: start, lt: end },
          status: 'present',
        },
      }),
      prisma.feePayment.aggregate({
        where: paymentFilter,
        _sum: { amountPaid: true },
      }),
      prisma.complaint.count({
        where: {
          ...complaintFilter,
          status: { in: ['open', 'pending', 'in_progress'] },
        },
      }),
    ]);

    // Build real 6-month revenue + attendance chart data
    const revenueChart = [];
    const attendanceChart = [];
    const now = new Date();

    for (let i = 5; i >= 0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const monthStart = new Date(d.getFullYear(), d.getMonth(), 1);
      const monthEnd = new Date(d.getFullYear(), d.getMonth() + 1, 0, 23, 59, 59);
      const label = d.toLocaleString('default', { month: 'short', year: '2-digit' });

      const [revAgg, presentCount, totalCount] = await Promise.all([
        prisma.feePayment.aggregate({
          where: { ...paymentFilter, paymentDate: { gte: monthStart, lte: monthEnd } },
          _sum: { amountPaid: true },
        }),
        prisma.attendance.count({
          where: { ...attendanceFilter, date: { gte: monthStart, lte: monthEnd }, status: 'present' },
        }),
        prisma.attendance.count({
          where: { ...attendanceFilter, date: { gte: monthStart, lte: monthEnd } },
        }),
      ]);

      revenueChart.push({ month: label, revenue: Number(revAgg._sum.amountPaid || 0) });
      attendanceChart.push({
        month: label,
        present: presentCount,
        total: totalCount,
        rate: totalCount > 0 ? Math.round((presentCount / totalCount) * 100) : 0,
      });
    }

    res.json({
      totalStudents,
      attendanceToday,
      feesCollected: Number(feesCollectedAgg._sum.amountPaid || 0),
      openComplaints,
      revenueChart,
      attendanceChart,
    });
  } catch (err) {
    console.error('Dashboard stats error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/quick-actions', auth, (req, res) => {
  res.json(buildQuickActions(req.user.role));
});

module.exports = router;
