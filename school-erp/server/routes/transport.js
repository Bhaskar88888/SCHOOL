const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const notificationService = require('../services/notificationService');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { withLegacyId, toLegacyStudent } = require('../utils/prismaCompat');
const { canUserAccessStudent, getStudentIdsForUser } = require('../utils/accessScope');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyTransportVehicle(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    driverId: record.driver ? withLegacyId(record.driver) : record.driverId,
    conductorId: record.conductor ? withLegacyId(record.conductor) : record.conductorId,
    students: Array.isArray(record.students)
      ? record.students.map((student) => toLegacyStudent(student))
      : [],
  });
}

function toLegacyTransportAttendance(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    busId: record.bus ? toLegacyTransportVehicle(record.bus) : record.busId,
    markedBy: record.markedBy ? withLegacyId(record.markedBy) : record.markedById,
  });
}

async function getTransportVehicleForOperator(user, busId) {
  if (user.role === 'driver') {
    return prisma.transportVehicle.findFirst({ where: { id: busId, driverId: user.id } });
  }

  if (user.role === 'conductor') {
    return prisma.transportVehicle.findFirst({ where: { id: busId, conductorId: user.id } });
  }

  return null;
}

async function canUserAccessBus(user, busId) {
  if (['superadmin', 'accounts', 'teacher', 'hr'].includes(user.role)) {
    return true;
  }

  if (['driver', 'conductor'].includes(user.role)) {
    return Boolean(await getTransportVehicleForOperator(user, busId));
  }

  if (['student', 'parent'].includes(user.role)) {
    const studentIds = await getStudentIdsForUser(user);
    if (!studentIds.length) {
      return false;
    }

    const vehicle = await prisma.transportVehicle.findFirst({
      where: {
        id: busId,
        students: { some: { id: { in: studentIds } } },
      },
      select: { id: true },
    });

    return Boolean(vehicle);
  }

  return false;
}

async function canTransportOperatorAccessStudent(user, studentId) {
  if (!['driver', 'conductor'].includes(user.role)) {
    return false;
  }

  const filter = user.role === 'driver'
    ? { driverId: user.id, students: { some: { id: studentId } } }
    : { conductorId: user.id, students: { some: { id: studentId } } };

  const vehicle = await prisma.transportVehicle.findFirst({
    where: filter,
    select: { id: true },
  });

  return Boolean(vehicle);
}

async function getHistoryBusFilter(user) {
  if (!['driver', 'conductor'].includes(user.role)) {
    return {};
  }

  const filter = user.role === 'driver'
    ? { driverId: user.id }
    : { conductorId: user.id };

  const vehicles = await prisma.transportVehicle.findMany({
    where: filter,
    select: { id: true },
  });

  return { busId: { in: vehicles.map(vehicle => vehicle.id) } };
}

function buildTransportVehicleListResponse(data, page, limit, total) {
  return {
    data,
    vehicles: data,
    pagination: getPaginationData(page, limit, total),
    meta: { query: { page, limit, sort: '-createdAt' } },
  };
}

function buildTransportAttendanceListResponse(data, page, limit, total, extraQuery = {}) {
  return {
    data,
    attendance: data,
    pagination: getPaginationData(page, limit, total),
    meta: {
      query: {
        page,
        limit,
        ...extraQuery,
      },
    },
  };
}

// POST /api/transport (Create Bus — Admin only)
router.post('/', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const vehicle = await prisma.transportVehicle.create({
      data: {
        busNumber: req.body.busNumber,
        numberPlate: req.body.numberPlate,
        driverId: req.body.driverId || null,
        conductorId: req.body.conductorId || null,
        route: req.body.route || '',
        capacity: req.body.capacity || 40,
      },
      include: {
        driver: { select: { id: true, name: true, phone: true } },
        conductor: { select: { id: true, name: true, phone: true } },
        students: {
          select: {
            id: true,
            name: true,
            admissionNo: true,
            parentPhone: true,
            classId: true,
            userId: true,
            parentUserId: true,
          },
        },
      },
    });

    res.status(201).json(toLegacyTransportVehicle(vehicle));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', err: err.message });
  }
});

router.post('/vehicles', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const vehicle = await prisma.transportVehicle.create({
      data: {
        busNumber: req.body.busNumber,
        numberPlate: req.body.numberPlate,
        driverId: req.body.driverId || null,
        conductorId: req.body.conductorId || null,
        route: req.body.route || '',
        capacity: req.body.capacity || 40,
      },
      include: {
        driver: { select: { id: true, name: true, phone: true } },
        conductor: { select: { id: true, name: true, phone: true } },
        students: {
          select: {
            id: true,
            name: true,
            admissionNo: true,
            parentPhone: true,
            classId: true,
            userId: true,
            parentUserId: true,
          },
        },
      },
    });

    res.status(201).json(toLegacyTransportVehicle(vehicle));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error', err: err.message });
  }
});

// PUT /api/transport/:id (Update Bus — Admin only)
router.put('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const vehicle = await prisma.transportVehicle.update({
      where: { id: req.params.id },
      data: {
        busNumber: req.body.busNumber,
        numberPlate: req.body.numberPlate,
        driverId: req.body.driverId,
        conductorId: req.body.conductorId,
        route: req.body.route,
        capacity: req.body.capacity,
      },
      include: {
        driver: { select: { id: true, name: true, phone: true } },
        conductor: { select: { id: true, name: true, phone: true } },
        students: {
          select: {
            id: true,
            name: true,
            admissionNo: true,
            parentPhone: true,
            classId: true,
            userId: true,
            parentUserId: true,
          },
        },
      },
    });

    res.json(toLegacyTransportVehicle(vehicle));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// DELETE /api/transport/:id (Admin only)
router.delete('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    await prisma.transportAttendance.deleteMany({ where: { busId: req.params.id } });
    await prisma.transportVehicle.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Bus deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/transport — Role-filtered bus list
router.get('/', auth, async (req, res) => {
  try {
    let filter = {};
    if (req.user.role === 'conductor') {
      filter.conductorId = req.user.id;
    } else if (req.user.role === 'driver') {
      filter.driverId = req.user.id;
    } else if (req.user.role === 'student') {
      const student = await prisma.student.findFirst({
        where: { userId: req.user.id },
        select: { id: true },
      });
      if (student) {
        filter.students = { some: { id: student.id } };
      } else {
        return res.json([]);
      }
    } else if (req.user.role === 'parent') {
      const children = await prisma.student.findMany({
        where: { parentUserId: req.user.id },
        select: { id: true },
      });
      const childIds = children.map(child => child.id);
      if (childIds.length > 0) {
        filter.students = { some: { id: { in: childIds } } };
      } else {
        return res.json([]);
      }
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, vehicles] = await Promise.all([
      prisma.transportVehicle.count({ where: filter }),
      prisma.transportVehicle.findMany({
        where: filter,
        include: {
          driver: { select: { id: true, name: true, phone: true } },
          conductor: { select: { id: true, name: true, phone: true } },
          students: {
            select: {
              id: true,
              name: true,
              admissionNo: true,
              parentPhone: true,
              classId: true,
              userId: true,
              parentUserId: true,
            },
          },
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: vehicles.map(toLegacyTransportVehicle),
      vehicles: vehicles.map(toLegacyTransportVehicle),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: '-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/vehicles', auth, async (req, res) => {
  try {
    let filter = {};
    if (req.user.role === 'conductor') {
      filter.conductorId = req.user.id;
    } else if (req.user.role === 'driver') {
      filter.driverId = req.user.id;
    } else if (req.user.role === 'student') {
      const student = await prisma.student.findFirst({
        where: { userId: req.user.id },
        select: { id: true },
      });
      if (student) {
        filter.students = { some: { id: student.id } };
      } else {
        return res.json(buildTransportVehicleListResponse([], 1, 20, 0));
      }
    } else if (req.user.role === 'parent') {
      const children = await prisma.student.findMany({
        where: { parentUserId: req.user.id },
        select: { id: true },
      });
      const childIds = children.map(child => child.id);
      if (childIds.length > 0) {
        filter.students = { some: { id: { in: childIds } } };
      } else {
        return res.json(buildTransportVehicleListResponse([], 1, 20, 0));
      }
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, vehicles] = await Promise.all([
      prisma.transportVehicle.count({ where: filter }),
      prisma.transportVehicle.findMany({
        where: filter,
        include: {
          driver: { select: { id: true, name: true, phone: true } },
          conductor: { select: { id: true, name: true, phone: true } },
          students: {
            select: {
              id: true,
              name: true,
              admissionNo: true,
              parentPhone: true,
              classId: true,
              userId: true,
              parentUserId: true,
            },
          },
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildTransportVehicleListResponse(vehicles.map(toLegacyTransportVehicle), page, limit, total));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/transport/:id/students (Assign students to bus — Admin)
router.put('/:id/students', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const studentIds = Array.isArray(req.body.students) ? req.body.students : [];
    const vehicle = await prisma.transportVehicle.update({
      where: { id: req.params.id },
      data: {
        students: {
          set: studentIds.map(id => ({ id })),
        },
      },
      include: {
        students: {
          select: { id: true, name: true, admissionNo: true, parentPhone: true },
        },
      },
    });

    res.json(toLegacyTransportVehicle(vehicle));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// POST /api/transport/:id/attendance — Mark student boarding/drop (Driver or Conductor)
router.post('/:id/attendance', auth, roleCheck('superadmin', 'conductor', 'driver'), async (req, res) => {
  try {
    const { studentId, status } = req.body;
    const date = req.body.date || new Date().toISOString().split('T')[0];

    const vehicle = await prisma.transportVehicle.findUnique({
      where: { id: req.params.id },
      include: {
        students: { select: { id: true } },
        driver: { select: { id: true, name: true } },
      },
    });

    if (!vehicle) {
      return res.status(404).json({ msg: 'Bus not found' });
    }

    if (['driver', 'conductor'].includes(req.user.role)) {
      const assignedVehicle = await getTransportVehicleForOperator(req.user, req.params.id);
      if (!assignedVehicle) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    if (!vehicle.students.some(student => String(student.id) === String(studentId))) {
      return res.status(400).json({ msg: 'Student is not assigned to this bus.' });
    }

    const attendance = await prisma.transportAttendance.upsert({
      where: {
        busId_studentId_date: {
          busId: req.params.id,
          studentId,
          date,
        },
      },
      update: {
        status,
        markedById: req.user.id,
      },
      create: {
        busId: req.params.id,
        studentId,
        date,
        status,
        markedById: req.user.id,
      },
      include: {
        student: {
          select: {
            id: true,
            name: true,
            admissionNo: true,
            parentPhone: true,
            parentUserId: true,
          },
        },
        bus: { select: { id: true, busNumber: true, route: true } },
      },
    });

    const studentRecord = attendance.student;
    if (studentRecord && studentRecord.parentUserId) {
      const driverName = vehicle.driver?.name || 'Your driver';
      await notificationService.notifyParentTransport({
        studentId,
        status,
        busNumber: vehicle.busNumber,
        driverName,
        markedBy: req.user.id,
      });
    }

    res.json({ attendance: toLegacyTransportAttendance(attendance), msg: 'Attendance marked and parent notified' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/attendance', auth, async (req, res) => {
  try {
    const { busId, date } = req.query;
    const where = {};

    if (date) {
      where.date = date;
    }

    if (busId) {
      if (!(await canUserAccessBus(req.user, busId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      where.busId = busId;
    } else if (['driver', 'conductor'].includes(req.user.role)) {
      const vehicles = await prisma.transportVehicle.findMany({
        where: req.user.role === 'driver' ? { driverId: req.user.id } : { conductorId: req.user.id },
        select: { id: true },
      });
      where.busId = { in: vehicles.map((vehicle) => vehicle.id) };
    } else if (['student', 'parent'].includes(req.user.role)) {
      const studentIds = await getStudentIdsForUser(req.user);
      if (!studentIds.length) {
        return res.json(buildTransportAttendanceListResponse([], 1, 20, 0));
      }
      where.studentId = { in: studentIds };
    } else if (!['superadmin', 'accounts', 'teacher', 'hr'].includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, records] = await Promise.all([
      prisma.transportAttendance.count({ where }),
      prisma.transportAttendance.findMany({
        where,
        include: {
          student: { select: { id: true, name: true, admissionNo: true } },
          bus: { select: { id: true, busNumber: true, route: true } },
          markedBy: { select: { id: true, name: true, role: true } },
        },
        orderBy: { date: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildTransportAttendanceListResponse(records.map(toLegacyTransportAttendance), page, limit, total, { sort: '-date' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/attendance', auth, roleCheck('superadmin', 'conductor', 'driver'), async (req, res) => {
  try {
    const { busId, studentId, status } = req.body;
    const date = req.body.date || new Date().toISOString().split('T')[0];

    if (!busId) {
      return res.status(400).json({ msg: 'busId is required' });
    }

    const vehicle = await prisma.transportVehicle.findUnique({
      where: { id: busId },
      include: {
        students: { select: { id: true } },
        driver: { select: { id: true, name: true } },
      },
    });

    if (!vehicle) {
      return res.status(404).json({ msg: 'Bus not found' });
    }

    if (['driver', 'conductor'].includes(req.user.role)) {
      const assignedVehicle = await getTransportVehicleForOperator(req.user, busId);
      if (!assignedVehicle) {
        return res.status(403).json({ msg: 'Access denied' });
      }
    }

    if (!vehicle.students.some(student => String(student.id) === String(studentId))) {
      return res.status(400).json({ msg: 'Student is not assigned to this bus.' });
    }

    const attendance = await prisma.transportAttendance.upsert({
      where: {
        busId_studentId_date: {
          busId,
          studentId,
          date,
        },
      },
      update: {
        status,
        markedById: req.user.id,
      },
      create: {
        busId,
        studentId,
        date,
        status,
        markedById: req.user.id,
      },
      include: {
        student: {
          select: {
            id: true,
            name: true,
            admissionNo: true,
            parentPhone: true,
            parentUserId: true,
          },
        },
        bus: { select: { id: true, busNumber: true, route: true } },
      },
    });

    const studentRecord = attendance.student;
    if (studentRecord && studentRecord.parentUserId) {
      const driverName = vehicle.driver?.name || 'Your driver';
      await notificationService.notifyParentTransport({
        studentId,
        status,
        busNumber: vehicle.busNumber,
        driverName,
        markedBy: req.user.id,
      });
    }

    res.json({ attendance: toLegacyTransportAttendance(attendance), msg: 'Attendance marked and parent notified' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/transport/:id/attendance?date=YYYY-MM-DD — Get today's attendance for a bus
router.get('/:id/attendance', auth, async (req, res) => {
  try {
    if (!(await canUserAccessBus(req.user, req.params.id))) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const date = req.query.date || new Date().toISOString().split('T')[0];
    const records = await prisma.transportAttendance.findMany({
      where: { busId: req.params.id, date },
      include: {
        student: { select: { id: true, name: true, admissionNo: true } },
      },
      orderBy: { createdAt: 'desc' },
    });

    res.json(records.map(toLegacyTransportAttendance));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// GET /api/transport/student/:studentId/history — Full attendance history for a student
router.get('/student/:studentId/history', auth, async (req, res) => {
  try {
    const canAccessStudent = ['driver', 'conductor'].includes(req.user.role)
      ? await canTransportOperatorAccessStudent(req.user, req.params.studentId)
      : await canUserAccessStudent(req.user, req.params.studentId);

    if (!canAccessStudent) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const records = await prisma.transportAttendance.findMany({
      where: {
        studentId: req.params.studentId,
        ...(await getHistoryBusFilter(req.user)),
      },
      include: {
        bus: { select: { id: true, busNumber: true, route: true } },
      },
      orderBy: { date: 'desc' },
      take: 60,
    });

    res.json(records.map(toLegacyTransportAttendance));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
