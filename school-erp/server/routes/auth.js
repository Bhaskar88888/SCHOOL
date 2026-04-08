const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { generateEmployeeId, generateTeacherId, generateDriverId, generateConductorId } = require('../utils/idGenerator');
const { signJwt } = require('../utils/jwt');
const { toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

const STAFF_ROLES = new Set(['teacher', 'staff', 'hr', 'accounts', 'canteen', 'conductor', 'driver']);
const GENERIC_FORGOT_PASSWORD_MESSAGE = 'If an account exists for that email, password recovery instructions have been generated.';
const MAX_FAILED_LOGIN_ATTEMPTS = parseInt(process.env.MAX_FAILED_LOGIN_ATTEMPTS || '5', 10);
const ACCOUNT_LOCK_MS = parseInt(process.env.ACCOUNT_LOCK_MS || `${30 * 60 * 1000}`, 10);
const MIN_PASSWORD_LENGTH = parseInt(process.env.MIN_PASSWORD_LENGTH || '8', 10);

const USER_UPDATE_FIELDS = [
  'name',
  'email',
  'phone',
  'role',
  'isActive',
  'profilePhoto',
  'employeeId',
  'department',
  'designation',
  'employmentType',
  'dateOfBirth',
  'bloodGroup',
  'highestQualification',
  'experienceYears',
  'pan',
  'aadhaar',
  'joiningDate',
  'emergencyContactName',
  'emergencyContactPhone',
  'staffAddress',
  'basicPay',
  'hra',
  'da',
  'conveyance',
  'pfDeduction',
  'esiDeduction',
  'bankName',
  'accountNumber',
  'ifscCode',
  'casualLeaveBalance',
  'earnedLeaveBalance',
  'sickLeaveBalance',
  'hrNotes',
];

function isStaffRole(role) {
  return STAFF_ROLES.has(role);
}

function normalizeEmail(email) {
  return String(email || '').trim().toLowerCase();
}

function ensureStrongEnoughPassword(password) {
  return typeof password === 'string' && password.length >= MIN_PASSWORD_LENGTH;
}

function generateIdByRole(role) {
  switch (role) {
    case 'teacher':
      return generateTeacherId();
    case 'driver':
      return generateDriverId();
    case 'conductor':
      return generateConductorId();
    default:
      return generateEmployeeId();
  }
}

function pickUserUpdateFields(body) {
  return USER_UPDATE_FIELDS.reduce((acc, field) => {
    if (Object.prototype.hasOwnProperty.call(body, field)) {
      acc[field] = body[field];
    }
    return acc;
  }, {});
}

function buildResetTokenPreview(resetToken) {
  const baseUrl = process.env.FRONTEND_URL || 'http://localhost:3000';
  return `${baseUrl}/reset-password?token=${resetToken}`;
}

function isUniqueConstraintError(error) {
  return error?.code === 'P2002';
}

async function hashPassword(password) {
  const salt = await bcrypt.genSalt(10);
  return bcrypt.hash(password, salt);
}

async function buildLoginUser(user) {
  const payload = {
    id: user.id,
    _id: user.id,
    name: user.name,
    role: user.role,
    email: user.email,
    phone: user.phone,
    employeeId: user.employeeId || null,
    passwordChangeRequired: Boolean(user.passwordChangeRequired),
  };

  if (user.role === 'student') {
    const student = await prisma.student.findFirst({
      where: { userId: user.id },
      select: {
        id: true,
        admissionNo: true,
        studentId: true,
        classId: true,
      },
    });

    payload.studentProfile = student ? {
      studentRecordId: student.id,
      studentId: student.studentId || null,
      admissionNo: student.admissionNo,
      classId: student.classId,
    } : null;
  }

  if (user.role === 'parent') {
    const children = await prisma.student.findMany({
      where: { parentUserId: user.id },
      select: {
        id: true,
        name: true,
        admissionNo: true,
        studentId: true,
        classId: true,
      },
      orderBy: { name: 'asc' },
    });

    payload.children = children.map((child) => ({
      studentRecordId: child.id,
      name: child.name,
      admissionNo: child.admissionNo,
      studentId: child.studentId || null,
      classId: child.classId,
    }));
  }

  return payload;
}

function buildUserListResponse(users, page, limit, total, extraQuery = {}) {
  const data = users.map((user) => toLegacyUser(user));
  return {
    data,
    users: data,
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

router.post('/register', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { name, email, password, role, phone, profilePhoto, employeeId, department, designation } = req.body;
    const normalizedEmail = normalizeEmail(email);

    if (!ensureStrongEnoughPassword(password)) {
      return res.status(400).json({ msg: `Password must be at least ${MIN_PASSWORD_LENGTH} characters long.` });
    }

    const existingUser = await prisma.user.findUnique({ where: { email: normalizedEmail } });
    if (existingUser) {
      return res.status(400).json({ msg: 'User already exists' });
    }

    const resolvedEmployeeId = isStaffRole(role)
      ? (employeeId || await generateIdByRole(role))
      : null;

    const user = await prisma.user.create({
      data: {
        name,
        email: normalizedEmail,
        password: await hashPassword(password),
        role,
        phone,
        profilePhoto,
        employeeId: resolvedEmployeeId,
        department,
        designation,
      },
    });

    res.status(201).json({ msg: 'User created', user: await buildLoginUser(user) });
  } catch (err) {
    if (isUniqueConstraintError(err)) {
      return res.status(400).json({ msg: 'Duplicate email or employee ID detected.' });
    }
    console.error(err);
    res.status(500).send('Server Error');
  }
});

router.post('/create-staff', auth, roleCheck('superadmin', 'hr'), async (req, res) => {
  try {
    const {
      name,
      email,
      password,
      phone,
      role,
      employeeId,
      department,
      designation,
      profilePhoto,
      employmentType,
      dateOfBirth,
      bloodGroup,
      highestQualification,
      experienceYears,
      pan,
      aadhaar,
      joiningDate,
      emergencyContactName,
      emergencyContactPhone,
      staffAddress,
      basicPay,
      hra,
      da,
      conveyance,
      pfDeduction,
      esiDeduction,
      bankName,
      accountNumber,
      ifscCode,
      casualLeaveBalance,
      earnedLeaveBalance,
      sickLeaveBalance,
      hrNotes,
    } = req.body;

    const normalizedEmail = normalizeEmail(email);

    if (!name || !normalizedEmail || !password || !phone || !role || !isStaffRole(role)) {
      return res.status(400).json({ msg: 'Name, email, password, phone, and a valid staff role are required.' });
    }

    if (!ensureStrongEnoughPassword(password)) {
      return res.status(400).json({ msg: `Password must be at least ${MIN_PASSWORD_LENGTH} characters long.` });
    }

    const existingUser = await prisma.user.findUnique({ where: { email: normalizedEmail } });
    if (existingUser) {
      return res.status(400).json({ msg: 'User already exists' });
    }

    const resolvedEmployeeId = employeeId || await generateIdByRole(role);

    const user = await prisma.user.create({
      data: {
        name,
        email: normalizedEmail,
        password: await hashPassword(password),
        phone,
        role,
        employeeId: resolvedEmployeeId,
        department,
        designation,
        profilePhoto,
        employmentType,
        dateOfBirth: dateOfBirth ? new Date(dateOfBirth) : null,
        bloodGroup,
        highestQualification,
        experienceYears: experienceYears ? Number(experienceYears) : null,
        pan,
        aadhaar,
        joiningDate: joiningDate ? new Date(joiningDate) : null,
        emergencyContactName,
        emergencyContactPhone,
        staffAddress: staffAddress || null,
        basicPay: basicPay !== undefined ? Number(basicPay) : 0,
        hra: hra !== undefined ? Number(hra) : 0,
        da: da !== undefined ? Number(da) : 0,
        conveyance: conveyance !== undefined ? Number(conveyance) : 0,
        pfDeduction: pfDeduction !== undefined ? Number(pfDeduction) : 0,
        esiDeduction: esiDeduction !== undefined ? Number(esiDeduction) : 0,
        bankName,
        accountNumber,
        ifscCode,
        casualLeaveBalance: casualLeaveBalance ?? 12,
        earnedLeaveBalance: earnedLeaveBalance ?? 15,
        sickLeaveBalance: sickLeaveBalance ?? 10,
        hrNotes,
      },
    });

    res.status(201).json({ msg: 'Staff member created', user: await buildLoginUser(user) });
  } catch (err) {
    if (isUniqueConstraintError(err)) {
      return res.status(400).json({ msg: 'Duplicate email or employee ID detected.' });
    }
    console.error(err);
    res.status(500).send('Server Error');
  }
});

router.post('/login', async (req, res) => {
  try {
    const { email, password } = req.body;
    const normalizedEmail = normalizeEmail(email);
    const user = await prisma.user.findUnique({ where: { email: normalizedEmail } });

    if (!user) {
      return res.status(400).json({ msg: 'Invalid credentials' });
    }

    if (user.lockUntil && user.lockUntil > new Date()) {
      return res.status(429).json({ msg: 'Account temporarily locked. Please try again later.' });
    }

    const isMatch = await bcrypt.compare(password, user.password);
    if (!isMatch) {
      const nextFailedAttempts = (user.failedLoginAttempts || 0) + 1;
      await prisma.user.update({
        where: { id: user.id },
        data: nextFailedAttempts >= MAX_FAILED_LOGIN_ATTEMPTS
          ? {
              failedLoginAttempts: 0,
              lockUntil: new Date(Date.now() + ACCOUNT_LOCK_MS),
            }
          : {
              failedLoginAttempts: nextFailedAttempts,
            },
      });

      return res.status(400).json({ msg: 'Invalid credentials' });
    }

    if (!user.isActive) {
      return res.status(401).json({ msg: 'Account deactivated' });
    }

    await prisma.user.update({
      where: { id: user.id },
      data: {
        failedLoginAttempts: 0,
        lockUntil: null,
      },
    });

    const payload = { role: user.role, id: user.id };

    signJwt(
      payload,
      { expiresIn: process.env.JWT_EXPIRES_IN },
      async (err, token) => {
        if (err) {
          console.error('JWT signing error:', err);
          return res.status(500).json({ msg: 'Token generation failed' });
        }

        try {
          res.cookie('token', token, {
            httpOnly: true,
            secure: process.env.NODE_ENV === 'production',
            sameSite: 'lax',
            maxAge: 7 * 24 * 60 * 60 * 1000,
          });

          res.json({ token, user: await buildLoginUser(user) });
        } catch (buildErr) {
          console.error('buildLoginUser error:', buildErr);
          res.status(500).json({ msg: 'Failed to build user response' });
        }
      }
    );
  } catch (err) {
    console.error(err);
    res.status(500).send('Server Error');
  }
});

router.post('/logout', (_req, res) => {
  res.clearCookie('token');
  res.json({ msg: 'Logged out successfully' });
});

router.get('/me', auth, async (req, res) => {
  try {
    const user = await prisma.user.findUnique({ where: { id: req.user.id } });
    if (!user) {
      return res.status(404).json({ msg: 'User not found' });
    }

    res.json({ user: await buildLoginUser(user) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/users', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { name, email, password, role, phone, profilePhoto, employeeId, department, designation } = req.body;
    const normalizedEmail = normalizeEmail(email);

    if (!ensureStrongEnoughPassword(password)) {
      return res.status(400).json({ msg: `Password must be at least ${MIN_PASSWORD_LENGTH} characters long.` });
    }

    const existingUser = await prisma.user.findUnique({ where: { email: normalizedEmail } });
    if (existingUser) {
      return res.status(400).json({ msg: 'User already exists' });
    }

    const resolvedEmployeeId = isStaffRole(role)
      ? (employeeId || await generateIdByRole(role))
      : null;

    const user = await prisma.user.create({
      data: {
        name,
        email: normalizedEmail,
        password: await hashPassword(password),
        role,
        phone,
        profilePhoto,
        employeeId: resolvedEmployeeId,
        department,
        designation,
      },
    });

    res.status(201).json({ msg: 'User created', user: await buildLoginUser(user) });
  } catch (err) {
    if (isUniqueConstraintError(err)) {
      return res.status(400).json({ msg: 'Duplicate email or employee ID detected.' });
    }
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/users', auth, roleCheck('superadmin', 'hr', 'accounts'), async (req, res) => {
  try {
    const { page, limit, search } = parsePaginationParams(req.query);
    const role = req.query.role;

    const where = {};
    if (role) {
      where.role = role;
    }
    if (search) {
      where.OR = [
        { name: { contains: search, mode: 'insensitive' } },
        { email: { contains: search, mode: 'insensitive' } },
        { employeeId: { contains: search, mode: 'insensitive' } },
        { phone: { contains: search, mode: 'insensitive' } },
      ];
    }

    const skip = (page - 1) * limit;
    const [total, users] = await Promise.all([
      prisma.user.count({ where }),
      prisma.user.findMany({
        where,
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json(buildUserListResponse(users, page, limit, total, { search, role, sort: '-createdAt' }));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.get('/users/:id', auth, async (req, res) => {
  try {
    const isSelf = req.user.id === req.params.id;
    const canView = isSelf || ['superadmin', 'hr', 'accounts'].includes(req.user.role);
    if (!canView) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const user = await prisma.user.findUnique({ where: { id: req.params.id } });
    if (!user) {
      return res.status(404).json({ msg: 'User not found' });
    }

    res.json({ user: toLegacyUser(user) });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.put('/users/:id', auth, async (req, res) => {
  try {
    const isSelf = req.user.id === req.params.id;
    const isAdmin = req.user.role === 'superadmin';

    // Non-admins can only update their own profile and only safe fields
    if (!isAdmin && !isSelf) {
      return res.status(403).json({ msg: 'You can only update your own profile.' });
    }

    const user = await prisma.user.findUnique({ where: { id: req.params.id } });
    if (!user) return res.status(404).json({ msg: 'User not found' });

    let updates;
    if (isAdmin) {
      // Admin can update all fields
      updates = pickUserUpdateFields(req.body);
      if (Object.prototype.hasOwnProperty.call(updates, 'email')) {
        updates.email = normalizeEmail(updates.email);
      }
      if (isStaffRole(updates.role || user.role) && !updates.employeeId && !user.employeeId) {
        updates.employeeId = await generateIdByRole(updates.role || user.role);
      }
    } else {
      // Self-update: only allow safe personal fields
      const SELF_FIELDS = ['name', 'phone', 'bloodGroup', 'dateOfBirth', 'designation', 'department', 'emergencyContactName', 'emergencyContactPhone'];
      updates = SELF_FIELDS.reduce((acc, f) => {
        if (Object.prototype.hasOwnProperty.call(req.body, f)) acc[f] = req.body[f];
        return acc;
      }, {});
    }

    const data = {
      ...updates,
      dateOfBirth: updates.dateOfBirth ? new Date(updates.dateOfBirth) : updates.dateOfBirth,
      joiningDate: updates.joiningDate ? new Date(updates.joiningDate) : updates.joiningDate,
      experienceYears: updates.experienceYears !== undefined && updates.experienceYears !== null
        ? Number(updates.experienceYears)
        : updates.experienceYears,
    };

    if (isAdmin && Object.prototype.hasOwnProperty.call(req.body, 'password')) {
      if (!ensureStrongEnoughPassword(req.body.password)) {
        return res.status(400).json({ msg: `Password must be at least ${MIN_PASSWORD_LENGTH} characters long.` });
      }
      data.password = await hashPassword(req.body.password);
    }

    const updated = await prisma.user.update({ where: { id: req.params.id }, data });
    res.json(toLegacyUser(updated));
  } catch (err) {
    if (isUniqueConstraintError(err)) {
      return res.status(400).json({ msg: 'Duplicate email or employee ID detected.' });
    }
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.delete('/users/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    await prisma.user.delete({ where: { id: req.params.id } });
    res.json({ msg: 'User deleted' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/forgot-password', async (req, res) => {
  try {
    const { email } = req.body;
    const normalizedEmail = normalizeEmail(email);
    const user = await prisma.user.findUnique({ where: { email: normalizedEmail } });

    if (user) {
      const resetToken = crypto.randomBytes(32).toString('hex');
      const resetTokenHash = crypto.createHash('sha256').update(resetToken).digest('hex');

      await prisma.user.update({
        where: { id: user.id },
        data: {
          passwordResetTokenHash: resetTokenHash,
          passwordResetExpiresAt: new Date(Date.now() + 15 * 60 * 1000),
        },
      });

      const previewUrl = buildResetTokenPreview(resetToken);
      console.log(`Password reset generated for ${user.email}: ${previewUrl}`);

      if (process.env.NODE_ENV !== 'production' && process.env.ALLOW_RESET_TOKEN_PREVIEW === 'true') {
        return res.json({
          msg: GENERIC_FORGOT_PASSWORD_MESSAGE,
          previewResetUrl: previewUrl,
        });
      }
    }

    res.json({ msg: GENERIC_FORGOT_PASSWORD_MESSAGE });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

router.post('/reset-password', async (req, res) => {
  try {
    const { token, password } = req.body;
    if (!token || !password) {
      return res.status(400).json({ msg: 'Reset token and new password are required.' });
    }

    if (!ensureStrongEnoughPassword(password)) {
      return res.status(400).json({ msg: `Password must be at least ${MIN_PASSWORD_LENGTH} characters long.` });
    }

    const tokenHash = crypto.createHash('sha256').update(String(token)).digest('hex');
    const user = await prisma.user.findFirst({
      where: {
        passwordResetTokenHash: tokenHash,
        passwordResetExpiresAt: { gt: new Date() },
      },
    });

    if (!user) {
      return res.status(400).json({ msg: 'Reset token is invalid or expired.' });
    }

    await prisma.user.update({
      where: { id: user.id },
      data: {
        password: await hashPassword(password),
        passwordResetTokenHash: null,
        passwordResetExpiresAt: null,
        failedLoginAttempts: 0,
        lockUntil: null,
        passwordChangeRequired: false,
      },
    });

    res.json({ msg: 'Password reset successful. You can now log in.' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error' });
  }
});

// PUT /api/auth/change-password — Any authenticated user can change their own password
router.put('/change-password', auth, async (req, res) => {
  try {
    const { currentPassword, newPassword } = req.body;

    if (!currentPassword || !newPassword) {
      return res.status(400).json({ msg: 'Current password and new password are required.' });
    }

    if (!ensureStrongEnoughPassword(newPassword)) {
      return res.status(400).json({ msg: `New password must be at least ${MIN_PASSWORD_LENGTH} characters long.` });
    }

    const user = await prisma.user.findUnique({ where: { id: req.user.id } });
    if (!user) return res.status(404).json({ msg: 'User not found' });

    const isMatch = await bcrypt.compare(currentPassword, user.password);
    if (!isMatch) {
      return res.status(400).json({ msg: 'Current password is incorrect.' });
    }

    await prisma.user.update({
      where: { id: req.user.id },
      data: {
        password: await hashPassword(newPassword),
        passwordChangeRequired: false,
        failedLoginAttempts: 0,
      },
    });

    res.clearCookie('token');
    res.json({ msg: 'Password changed successfully. Please log in again.' });
  } catch (err) {
    console.error('Change password error:', err);
    res.status(500).json({ msg: 'Server Error' });
  }
});

module.exports = router;
