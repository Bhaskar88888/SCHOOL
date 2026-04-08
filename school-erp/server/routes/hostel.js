const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { getStudentRecordsForUser } = require('../utils/accessScope');
const { withLegacyId, toLegacyStudent } = require('../utils/prismaCompat');

const MANAGE_ROLES = ['superadmin', 'accounts', 'teacher', 'staff', 'hr'];

function normalizeRoomStatus(room) {
  if (room.status === 'MAINTENANCE') {
    return 'MAINTENANCE';
  }
  if (room.occupiedBeds >= room.capacity) {
    return 'FULL';
  }
  if (room.occupiedBeds > 0) {
    return 'LIMITED';
  }
  return 'AVAILABLE';
}

function toLegacyRoomType(record) {
  return record ? withLegacyId(record) : null;
}

function toLegacyRoom(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    roomTypeId: record.roomType ? toLegacyRoomType(record.roomType) : record.roomTypeId,
  });
}

function toLegacyFeeStructure(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    roomTypeId: record.roomType ? toLegacyRoomType(record.roomType) : record.roomTypeId,
  });
}

function toLegacyAllocation(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    roomTypeId: record.roomType ? toLegacyRoomType(record.roomType) : record.roomTypeId,
    roomId: record.room ? toLegacyRoom(record.room) : record.roomId,
    feeStructureId: record.feeStructure ? toLegacyFeeStructure(record.feeStructure) : record.feeStructureId,
  });
}

function buildHostelListResponse(key, data) {
  return {
    data,
    [key]: data,
  };
}

router.get('/dashboard', auth, async (req, res) => {
  try {
    let students = [];
    let roomTypes = [];
    let rooms = [];
    let feeStructures = [];
    let allocations = [];

    if (MANAGE_ROLES.includes(req.user.role)) {
      [students, roomTypes, rooms, feeStructures, allocations] = await Promise.all([
        prisma.student.findMany({
          include: { class: { select: { id: true, name: true, section: true } } },
          orderBy: [{ hostelRequired: 'desc' }, { name: 'asc' }],
        }),
        prisma.hostelRoomType.findMany({ orderBy: { name: 'asc' } }),
        prisma.hostelRoom.findMany({
          include: { roomType: { select: { id: true, name: true, occupancy: true } } },
          orderBy: [{ block: 'asc' }, { floor: 'asc' }, { roomNumber: 'asc' }],
        }),
        prisma.hostelFeeStructure.findMany({
          include: { roomType: { select: { id: true, name: true, occupancy: true } } },
          orderBy: { createdAt: 'desc' },
        }),
        prisma.hostelAllocation.findMany({
          include: {
            student: { include: { class: { select: { id: true, name: true, section: true } } } },
            roomType: { select: { id: true, name: true } },
            room: { select: { id: true, roomNumber: true, block: true, floor: true, capacity: true, occupiedBeds: true, status: true } },
            feeStructure: { select: { id: true, academicYear: true, term: true, billingCycle: true, amount: true, cautionDeposit: true, messCharge: true, maintenanceCharge: true } },
          },
          orderBy: { createdAt: 'desc' },
        }),
      ]);
    } else if (['student', 'parent'].includes(req.user.role)) {
      const linkedStudents = await getStudentRecordsForUser(req.user);
      const studentIds = linkedStudents.map(item => item._id || item.id);
      students = linkedStudents;
      roomTypes = await prisma.hostelRoomType.findMany({ orderBy: { name: 'asc' } });
      allocations = await prisma.hostelAllocation.findMany({
        where: { studentId: { in: studentIds } },
        include: {
          student: { include: { class: { select: { id: true, name: true, section: true } } } },
          roomType: { select: { id: true, name: true } },
          room: { select: { id: true, roomNumber: true, block: true, floor: true, capacity: true, occupiedBeds: true, status: true } },
          feeStructure: { select: { id: true, academicYear: true, term: true, billingCycle: true, amount: true, cautionDeposit: true, messCharge: true, maintenanceCharge: true } },
        },
        orderBy: { createdAt: 'desc' },
      });
      const roomIds = allocations.map(item => item.roomId).filter(Boolean);
      rooms = roomIds.length ? await prisma.hostelRoom.findMany({
        where: { id: { in: roomIds } },
        include: { roomType: { select: { id: true, name: true, occupancy: true } } },
      }) : [];
      const feeIds = allocations.map(item => item.feeStructureId).filter(Boolean);
      feeStructures = feeIds.length ? await prisma.hostelFeeStructure.findMany({
        where: { id: { in: feeIds } },
        include: { roomType: { select: { id: true, name: true, occupancy: true } } },
      }) : [];
    } else {
      return res.status(403).json({ msg: 'Access denied' });
    }

    res.json({
      students: students.map(item => (item.id ? toLegacyStudent(item) : item)),
      roomTypes: roomTypes.map(toLegacyRoomType),
      rooms: rooms.map(toLegacyRoom),
      feeStructures: feeStructures.map(toLegacyFeeStructure),
      allocations: allocations.map(toLegacyAllocation),
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.get('/room-types', auth, async (req, res) => {
  try {
    const roomTypes = await prisma.hostelRoomType.findMany({ orderBy: { name: 'asc' } });
    const data = roomTypes.map(toLegacyRoomType);
    res.json(buildHostelListResponse('roomTypes', data));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.get('/rooms', auth, async (req, res) => {
  try {
    let where = {};

    if (['student', 'parent'].includes(req.user.role)) {
      const linkedStudents = await getStudentRecordsForUser(req.user);
      const studentIds = linkedStudents.map((item) => item._id || item.id);
      const allocations = await prisma.hostelAllocation.findMany({
        where: { studentId: { in: studentIds } },
        select: { roomId: true },
      });
      const roomIds = allocations.map((allocation) => allocation.roomId).filter(Boolean);
      where = roomIds.length ? { id: { in: roomIds } } : { id: { in: [] } };
    } else if (!MANAGE_ROLES.includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const rooms = await prisma.hostelRoom.findMany({
      where,
      include: { roomType: { select: { id: true, name: true, occupancy: true } } },
      orderBy: [{ block: 'asc' }, { floor: 'asc' }, { roomNumber: 'asc' }],
    });

    const data = rooms.map(toLegacyRoom);
    res.json(buildHostelListResponse('rooms', data));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.get('/fee-structures', auth, async (req, res) => {
  try {
    let where = {};

    if (['student', 'parent'].includes(req.user.role)) {
      const linkedStudents = await getStudentRecordsForUser(req.user);
      const studentIds = linkedStudents.map((item) => item._id || item.id);
      const allocations = await prisma.hostelAllocation.findMany({
        where: { studentId: { in: studentIds } },
        select: { feeStructureId: true },
      });
      const feeStructureIds = allocations.map((allocation) => allocation.feeStructureId).filter(Boolean);
      where = feeStructureIds.length ? { id: { in: feeStructureIds } } : { id: { in: [] } };
    } else if (!MANAGE_ROLES.includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const feeStructures = await prisma.hostelFeeStructure.findMany({
      where,
      include: { roomType: { select: { id: true, name: true, occupancy: true } } },
      orderBy: { createdAt: 'desc' },
    });

    const data = feeStructures.map(toLegacyFeeStructure);
    res.json(buildHostelListResponse('feeStructures', data));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.get('/allocations', auth, async (req, res) => {
  try {
    let where = {};

    if (['student', 'parent'].includes(req.user.role)) {
      const linkedStudents = await getStudentRecordsForUser(req.user);
      const studentIds = linkedStudents.map((item) => item._id || item.id);
      where = { studentId: { in: studentIds } };
    } else if (!MANAGE_ROLES.includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const allocations = await prisma.hostelAllocation.findMany({
      where,
      include: {
        student: { include: { class: { select: { id: true, name: true, section: true } } } },
        roomType: { select: { id: true, name: true } },
        room: { select: { id: true, roomNumber: true, block: true, floor: true, capacity: true, occupiedBeds: true, status: true } },
        feeStructure: { select: { id: true, academicYear: true, term: true, billingCycle: true, amount: true, cautionDeposit: true, messCharge: true, maintenanceCharge: true } },
      },
      orderBy: { createdAt: 'desc' },
    });

    const data = allocations.map(toLegacyAllocation);
    res.json(buildHostelListResponse('allocations', data));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/room-types', auth, roleCheck(...MANAGE_ROLES), async (req, res) => {
  try {
    const name = (req.body.name || '').trim();
    const occupancy = Number(req.body.occupancy || 0);
    const defaultFee = Math.max(0, Number(req.body.defaultFee || 0));

    if (!name || occupancy <= 0) {
      return res.status(400).json({ msg: 'Room type name and occupancy are required.' });
    }

    const roomType = await prisma.hostelRoomType.create({
      data: {
        name,
        code: (req.body.code || '').trim(),
        occupancy,
        genderPolicy: req.body.genderPolicy || 'mixed',
        defaultFee,
        amenities: (req.body.amenities || '')
          .split(',')
          .map(item => item.trim())
          .filter(Boolean),
        description: (req.body.description || '').trim(),
      },
    });
    res.status(201).json(toLegacyRoomType(roomType));
  } catch (err) {
    console.error(err);
    if (err.code === 'P2002') {
      return res.status(400).json({ msg: 'A room type with the same name already exists.' });
    }
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/rooms', auth, roleCheck(...MANAGE_ROLES), async (req, res) => {
  try {
    const { roomTypeId } = req.body;
    const roomNumber = (req.body.roomNumber || '').trim();
    const roomType = await prisma.hostelRoomType.findUnique({ where: { id: roomTypeId } });

    if (!roomType || !roomNumber) {
      return res.status(400).json({ msg: 'Room type and room number are required.' });
    }

    const capacity = Math.max(1, Number(req.body.capacity || roomType.occupancy));
    const roomData = {
      roomTypeId,
      roomNumber,
      block: (req.body.block || '').trim(),
      floor: (req.body.floor || '').trim(),
      capacity,
      occupiedBeds: 0,
      status: req.body.status || 'AVAILABLE',
      notes: (req.body.notes || '').trim(),
    };

    roomData.status = normalizeRoomStatus(roomData);
    const room = await prisma.hostelRoom.create({
      data: roomData,
      include: { roomType: { select: { id: true, name: true, occupancy: true } } },
    });

    res.status(201).json(toLegacyRoom(room));
  } catch (err) {
    console.error(err);
    if (err.code === 'P2002') {
      return res.status(400).json({ msg: 'This room number already exists.' });
    }
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/fee-structures', auth, roleCheck(...MANAGE_ROLES), async (req, res) => {
  try {
    const { roomTypeId } = req.body;
    const academicYear = (req.body.academicYear || '').trim();
    const term = (req.body.term || '').trim();
    const amount = Math.max(0, Number(req.body.amount || 0));

    if (!roomTypeId || !academicYear || !term) {
      return res.status(400).json({ msg: 'Room type, academic year, and term are required.' });
    }

    const feeStructure = await prisma.hostelFeeStructure.create({
      data: {
        roomTypeId,
        academicYear,
        term,
        billingCycle: req.body.billingCycle || 'monthly',
        amount,
        cautionDeposit: Math.max(0, Number(req.body.cautionDeposit || 0)),
        messCharge: Math.max(0, Number(req.body.messCharge || 0)),
        maintenanceCharge: Math.max(0, Number(req.body.maintenanceCharge || 0)),
        notes: (req.body.notes || '').trim(),
      },
      include: { roomType: { select: { id: true, name: true, occupancy: true } } },
    });

    res.status(201).json(toLegacyFeeStructure(feeStructure));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/allocations', auth, roleCheck(...MANAGE_ROLES), async (req, res) => {
  try {
    const { studentId, roomId, feeStructureId } = req.body;
    const academicYear = (req.body.academicYear || '').trim();

    if (!studentId || !roomId || !academicYear) {
      return res.status(400).json({ msg: 'Student, room, and academic year are required.' });
    }

    const [student, room, activeAllocation] = await Promise.all([
      prisma.student.findUnique({ where: { id: studentId } }),
      prisma.hostelRoom.findUnique({
        where: { id: roomId },
        include: { roomType: { select: { id: true, name: true, occupancy: true } } },
      }),
      prisma.hostelAllocation.findFirst({ where: { studentId, status: 'ACTIVE' } }),
    ]);

    if (!student) {
      return res.status(404).json({ msg: 'Student not found.' });
    }

    if (!room) {
      return res.status(404).json({ msg: 'Room not found.' });
    }

    if (activeAllocation) {
      return res.status(400).json({ msg: 'This student already has an active hostel allotment.' });
    }

    if (room.occupiedBeds >= room.capacity) {
      return res.status(400).json({ msg: 'Selected room is already full.' });
    }

    let feeStructure = null;
    if (feeStructureId) {
      feeStructure = await prisma.hostelFeeStructure.findUnique({ where: { id: feeStructureId } });
      if (!feeStructure) {
        return res.status(404).json({ msg: 'Fee structure not found.' });
      }
      if (String(feeStructure.roomTypeId) !== String(room.roomTypeId)) {
        return res.status(400).json({ msg: 'Fee structure does not belong to the selected room type.' });
      }
    }

    const allocation = await prisma.$transaction(async (tx) => {
      const updated = await tx.hostelRoom.updateMany({
        where: { id: room.id, occupiedBeds: { lt: room.capacity }, status: { not: 'MAINTENANCE' } },
        data: { occupiedBeds: { increment: 1 } },
      });

      if (updated.count === 0) {
        const err = new Error('ROOM_FULL');
        err.code = 'ROOM_FULL';
        throw err;
      }

      const created = await tx.hostelAllocation.create({
        data: {
          studentId,
          roomTypeId: room.roomTypeId,
          roomId,
          feeStructureId: feeStructure ? feeStructure.id : null,
          academicYear,
          bedLabel: (req.body.bedLabel || '').trim(),
          allotmentDate: req.body.allotmentDate ? new Date(req.body.allotmentDate) : new Date(),
          guardianContactName: (req.body.guardianContactName || student.guardianName || '').trim(),
          guardianContactPhone: (req.body.guardianContactPhone || student.guardianPhone || student.parentPhone || '').trim(),
          notes: (req.body.notes || '').trim(),
        },
      });

      const refreshedRoom = await tx.hostelRoom.findUnique({ where: { id: room.id } });
      if (refreshedRoom) {
        await tx.hostelRoom.update({
          where: { id: room.id },
          data: { status: normalizeRoomStatus(refreshedRoom) },
        });
      }

      return created;
    });

    const populated = await prisma.hostelAllocation.findUnique({
      where: { id: allocation.id },
      include: {
        student: { include: { class: { select: { id: true, name: true, section: true } } } },
        roomType: { select: { id: true, name: true } },
        room: { select: { id: true, roomNumber: true, block: true, floor: true, capacity: true, occupiedBeds: true, status: true } },
        feeStructure: { select: { id: true, academicYear: true, term: true, billingCycle: true, amount: true, cautionDeposit: true, messCharge: true, maintenanceCharge: true } },
      },
    });

    res.status(201).json(toLegacyAllocation(populated));
  } catch (err) {
    if (err?.code === 'ROOM_FULL') {
      return res.status(400).json({ msg: 'Selected room is already full.' });
    }
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.patch('/allocations/:id/vacate', auth, roleCheck(...MANAGE_ROLES), async (req, res) => {
  try {
    const allocation = await prisma.hostelAllocation.findUnique({ where: { id: req.params.id } });
    if (!allocation) {
      return res.status(404).json({ msg: 'Hostel allotment not found.' });
    }

    if (allocation.status !== 'ACTIVE') {
      return res.status(400).json({ msg: 'This allotment is already vacated.' });
    }

    const room = await prisma.hostelRoom.findUnique({ where: { id: allocation.roomId } });
    if (!room) {
      return res.status(404).json({ msg: 'Room not found.' });
    }

    const updatedAllocation = await prisma.$transaction(async (tx) => {
      const updated = await tx.hostelAllocation.update({
        where: { id: allocation.id },
        data: {
          status: 'VACATED',
          vacatedOn: new Date(),
          notes: [allocation.notes, (req.body.notes || '').trim()].filter(Boolean).join(' | '),
        },
      });

      await tx.hostelRoom.updateMany({
        where: { id: room.id, occupiedBeds: { gt: 0 } },
        data: { occupiedBeds: { decrement: 1 } },
      });

      const refreshedRoom = await tx.hostelRoom.findUnique({ where: { id: room.id } });
      if (refreshedRoom) {
        await tx.hostelRoom.update({
          where: { id: room.id },
          data: { status: normalizeRoomStatus(refreshedRoom) },
        });
      }

      return updated;
    });

    const populated = await prisma.hostelAllocation.findUnique({
      where: { id: updatedAllocation.id },
      include: {
        student: { include: { class: { select: { id: true, name: true, section: true } } } },
        roomType: { select: { id: true, name: true } },
        room: { select: { id: true, roomNumber: true, block: true, floor: true, capacity: true, occupiedBeds: true, status: true } },
        feeStructure: { select: { id: true, academicYear: true, term: true, billingCycle: true, amount: true, cautionDeposit: true, messCharge: true, maintenanceCharge: true } },
      },
    });

    res.json({ msg: 'Student vacated from hostel successfully.', allocation: toLegacyAllocation(populated) });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

module.exports = router;
