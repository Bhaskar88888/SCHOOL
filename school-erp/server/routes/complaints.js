const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { getTeacherClassIds, getStudentIdsForUser, canUserAccessStudent } = require('../utils/accessScope');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');
const { withLegacyId, toLegacyClass, toLegacyStudent, toLegacyUser } = require('../utils/prismaCompat');

const STAFF_ROLES = ['teacher', 'staff', 'hr', 'accounts', 'canteen', 'conductor', 'driver'];

function buildComplaintTitle(type) {
  if (type === 'teacher_to_parent') return 'Complaint filed by Teacher';
  if (type === 'conductor_to_parent') return 'Complaint filed by Conductor';
  if (type === 'parent_to_teacher') return 'Complaint filed by Parent';
  if (type === 'parent_to_staff') return 'Complaint filed by Parent against Staff';
  return 'Complaint escalated to Admin';
}

function buildComplaintNotificationType(type) {
  if (type === 'teacher_to_parent') return 'complaint_to_parent';
  if (type === 'conductor_to_parent') return 'complaint_to_parent';
  if (type === 'parent_to_teacher') return 'complaint_to_teacher';
  if (type === 'driver_to_student') return 'complaint_to_admin';
  return 'complaint_to_admin';
}

function toLegacyComplaint(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    userId: record.user ? toLegacyUser(record.user) : record.userId,
    targetUserId: record.targetUser ? toLegacyUser(record.targetUser) : record.targetUserId,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    classId: record.class ? toLegacyClass(record.class) : record.classId,
  });
}

router.post('/', auth, async (req, res) => {
  try {
    const { studentId, classId, targetUserId, subject, description } = req.body;
    if (!subject || !description) {
      return res.status(400).json({ msg: 'Subject and description are required.' });
    }

    let type = req.body.type || 'general';
    let resolvedTargetUserId = null;
    let assignedToRole = 'superadmin';
    let resolvedStudentId = null;
    let resolvedClassId = null;

    if (req.user.role === 'teacher' || req.user.role === 'conductor' || req.user.role === 'driver') {
      if (studentId) {
        const student = await prisma.student.findUnique({ where: { id: studentId } });
        if (!student) return res.status(404).json({ msg: 'Student not found' });

        if (req.user.role === 'teacher') {
          const allowedClassIds = await getTeacherClassIds(req.user.id);
          if (!allowedClassIds.includes(String(student.classId))) {
            return res.status(403).json({ msg: 'You can only file complaints for your assigned classes.' });
          }
        }

        if (req.user.role === 'conductor' || req.user.role === 'driver') {
          const checkField = req.user.role === 'conductor' ? 'conductorId' : 'driverId';
          const assignedBus = await prisma.transportVehicle.findFirst({
            where: {
              [checkField]: req.user.id,
              students: { some: { id: student.id } },
            },
            select: { id: true },
          });
          if (!assignedBus) {
            return res.status(403).json({ msg: 'You can only file complaints for students on your assigned bus.' });
          }
        }

        if (req.user.role === 'driver') {
          type = 'driver_to_student';
          resolvedTargetUserId = null;
          assignedToRole = 'superadmin';
        } else {
          type = req.user.role === 'teacher' ? 'teacher_to_parent' : 'conductor_to_parent';
          resolvedTargetUserId = student.parentUserId || null;
          assignedToRole = resolvedTargetUserId ? 'parent' : 'superadmin';
        }
        resolvedStudentId = student.id;
        resolvedClassId = student.classId || null;
      } else {
        if (req.user.role === 'driver') type = 'driver_to_admin';
        else type = req.user.role === 'teacher' ? 'teacher_to_admin' : 'conductor_to_admin';
      }
    } else if (req.user.role === 'parent') {
      const childIds = await getStudentIdsForUser(req.user);

      if (targetUserId) {
        const targetUser = await prisma.user.findFirst({
          where: {
            id: targetUserId,
            role: { in: STAFF_ROLES },
            isActive: true,
          },
          select: { id: true, role: true },
        });
        if (!targetUser) {
          return res.status(404).json({ msg: 'Staff member not found' });
        }

        type = 'parent_to_staff';
        resolvedTargetUserId = targetUser.id;
        assignedToRole = 'superadmin';

        if (req.body.studentId && !childIds.includes(String(req.body.studentId))) {
          return res.status(403).json({ msg: 'You can only file complaints for your own children.' });
        }

        if (req.body.studentId) {
          const linkedStudent = await prisma.student.findUnique({
            where: { id: req.body.studentId },
            select: { id: true, classId: true },
          });
          resolvedStudentId = linkedStudent?.id || null;
          resolvedClassId = linkedStudent?.classId || null;
        }
      } else if (classId) {
        const classData = await prisma.class.findUnique({ where: { id: classId } });
        if (!classData) return res.status(404).json({ msg: 'Class not found' });
        const hasChildInClass = await prisma.student.findFirst({
          where: { id: { in: childIds }, classId },
          select: { id: true },
        });
        if (!hasChildInClass) {
          return res.status(403).json({ msg: 'You can only contact teachers of your child classes.' });
        }
        type = 'parent_to_teacher';
        resolvedTargetUserId = classData.classTeacherId || null;
        assignedToRole = resolvedTargetUserId ? 'teacher' : 'superadmin';
        resolvedStudentId = hasChildInClass.id;
        resolvedClassId = classData.id;
      } else {
        type = 'parent_to_admin';
      }
    } else if (req.user.role === 'student') {
      if (studentId && !(await canUserAccessStudent(req.user, studentId))) {
        return res.status(403).json({ msg: 'Access denied' });
      }
      type = 'student_to_admin';
      resolvedStudentId = studentId || null;
    }

    const complaint = await prisma.complaint.create({
      data: {
        type,
        subject,
        description,
        userId: req.user.id,
        targetUserId: resolvedTargetUserId,
        assignedToRole,
        raisedByRole: req.user.role,
        studentId: resolvedStudentId,
        classId: resolvedClassId,
      },
    });

    const recipients = [];
    const shouldNotifyDirectTarget = ['teacher_to_parent', 'conductor_to_parent', 'parent_to_teacher'].includes(type);
    if (shouldNotifyDirectTarget && resolvedTargetUserId) {
      recipients.push(resolvedTargetUserId);
    }

    const adminUsers = await prisma.user.findMany({
      where: { role: 'superadmin', isActive: true },
      select: { id: true },
    });
    adminUsers.forEach(admin => recipients.push(admin.id));

    const uniqueRecipients = [...new Set(recipients.filter(Boolean).map(value => String(value)))];
    if (uniqueRecipients.length > 0) {
      await prisma.notification.createMany({
        data: uniqueRecipients.map(recipientId => ({
          recipientId,
          senderId: req.user.id,
          title: buildComplaintTitle(type),
          message: description,
          type: buildComplaintNotificationType(type),
          relatedEntityId: complaint.id,
        })),
      });
    }

    res.status(201).json(toLegacyComplaint(complaint));
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/staff-targets', auth, roleCheck('parent', 'superadmin'), async (_req, res) => {
  try {
    const staffMembers = await prisma.user.findMany({
      where: {
        role: { in: STAFF_ROLES },
        isActive: true,
      },
      select: { id: true, name: true, role: true, employeeId: true, department: true, designation: true },
      orderBy: { name: 'asc' },
    });

    res.json(staffMembers.map(withLegacyId));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/', auth, async (req, res) => {
  try {
    let where = {};

    if (req.user.role === 'superadmin') {
      where = {};
    } else if (req.user.role === 'teacher') {
      const teacherClassIds = await getTeacherClassIds(req.user.id);
      where = {
        OR: [
          { userId: req.user.id },
          { targetUserId: req.user.id, type: { not: 'parent_to_staff' } },
          { assignedToRole: 'superadmin', raisedByRole: 'teacher' },
          { classId: { in: teacherClassIds }, raisedByRole: { in: ['driver', 'conductor'] } },
        ],
      };
    } else if (req.user.role === 'conductor') {
      where = {
        OR: [
          { userId: req.user.id },
          { assignedToRole: 'superadmin', raisedByRole: 'conductor' },
        ],
      };
    } else if (['staff', 'hr', 'accounts', 'canteen', 'driver'].includes(req.user.role)) {
      where = {
        OR: [
          { userId: req.user.id },
          { assignedToRole: 'superadmin', raisedByRole: req.user.role },
        ],
      };
    } else {
      where = {
        OR: [
          { userId: req.user.id },
          { targetUserId: req.user.id },
        ],
      };
    }

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * (limit || 50);
    const take = limit || 50;

    const [total, data] = await Promise.all([
      prisma.complaint.count({ where }),
      prisma.complaint.findMany({
        where,
        include: {
          user: { select: { id: true, name: true, role: true } },
          targetUser: { select: { id: true, name: true, role: true } },
          student: { select: { id: true, name: true, admissionNo: true, studentId: true } },
          class: { select: { id: true, name: true, section: true } },
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take,
      }),
    ]);

    const pagination = getPaginationData(page, take, total);
    res.json({
      data: data.map(toLegacyComplaint),
      pagination,
      meta: { query: { page, limit: take, sort: '-createdAt' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/my', auth, async (req, res) => {
  try {
    const where = {
      OR: [{ userId: req.user.id }, { targetUserId: req.user.id }],
    };

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * (limit || 50);
    const take = limit || 50;

    const [total, data] = await Promise.all([
      prisma.complaint.count({ where }),
      prisma.complaint.findMany({
        where,
        include: {
          user: { select: { id: true, name: true, role: true } },
          targetUser: { select: { id: true, name: true, role: true } },
          student: { select: { id: true, name: true, admissionNo: true, studentId: true } },
          class: { select: { id: true, name: true, section: true } },
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take,
      }),
    ]);

    const pagination = getPaginationData(page, take, total);
    res.json({ data: data.map(toLegacyComplaint), pagination, meta: { query: { page, limit: take, sort: '-createdAt' } } });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.put('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const updates = {
      status: req.body.status,
      resolutionNote: req.body.resolutionNote || '',
    };
    const complaint = await prisma.complaint.update({
      where: { id: req.params.id },
      data: updates,
      include: {
        user: { select: { id: true, name: true, role: true } },
        targetUser: { select: { id: true, name: true, role: true } },
      },
    });
    res.json(toLegacyComplaint(complaint));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
