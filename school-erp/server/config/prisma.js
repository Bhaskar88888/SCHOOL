const { PrismaClient } = require('@prisma/client');
const bcrypt = require('bcryptjs');

const globalForPrisma = globalThis;

const prisma =
  globalForPrisma.__schoolPrisma ||
  new PrismaClient({
    log: process.env.NODE_ENV === 'development' ? ['error', 'warn'] : ['error'],
  });

if (process.env.NODE_ENV !== 'production') {
  globalForPrisma.__schoolPrisma = prisma;
}

const ROLE_VALUES = new Set([
  'superadmin',
  'admin',
  'principal',
  'staff',
  'teacher',
  'student',
  'parent',
  'hr',
  'accounts',
  'canteen',
  'conductor',
  'driver',
  'unknown',
]);

const ENUM_FIELD_RULES = {
  Attendance: { status: new Set(['present', 'absent', 'late', 'half-day']) },
  StaffAttendance: { status: new Set(['present', 'absent', 'late', 'half-day']) },
  TransportAttendance: { status: new Set(['boarded', 'dropped_off', 'absent']) },
  Complaint: {
    status: new Set(['open', 'in_progress', 'resolved', 'closed']),
    raisedByRole: ROLE_VALUES,
    assignedToRole: ROLE_VALUES,
    type: new Set([
      'teacher_to_parent',
      'conductor_to_parent',
      'parent_to_teacher',
      'parent_to_staff',
      'student_to_admin',
      'parent_to_admin',
      'teacher_to_admin',
      'conductor_to_admin',
      'driver_to_student',
      'driver_to_admin',
      'general',
    ]),
  },
  Leave: {
    type: new Set(['sick', 'casual', 'earned', 'unpaid']),
    status: new Set(['pending', 'approved', 'rejected']),
  },
  LibraryTransaction: { status: new Set(['BORROWED', 'RETURNED', 'LOST']) },
  Notice: { priority: new Set(['normal', 'important', 'urgent']) },
  Notification: {
    type: new Set([
      'homework',
      'complaint_to_parent',
      'complaint_to_teacher',
      'complaint_to_admin',
      'transport',
      'attendance_alert',
      'fee_alert',
      'general',
    ]),
  },
  FeePayment: { paymentMode: new Set(['cash', 'online', 'cheque']) },
  FeeStructure: { type: new Set(['monthly', 'annual', 'exam']) },
  HostelAllocation: { status: new Set(['ACTIVE', 'VACATED']) },
  HostelFeeStructure: { billingCycle: new Set(['monthly', 'quarterly', 'half-yearly', 'annual']) },
  HostelRoom: { status: new Set(['AVAILABLE', 'LIMITED', 'FULL', 'MAINTENANCE']) },
  HostelRoomType: { genderPolicy: new Set(['boys', 'girls', 'mixed']) },
  BusRoute: { vehicleType: new Set(['AC Bus', 'Non-AC Bus', 'Van', 'Mini Bus']) },
  CanteenSale: { paymentMode: new Set(['Cash', 'Wallet', 'Card', 'UPI']) },
  Student: {
    gender: new Set(['male', 'female', 'other']),
    bloodGroup: new Set(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', '']),
    category: new Set(['General', 'OBC', 'SC', 'ST', 'EWS', '']),
  },
  User: {
    role: ROLE_VALUES,
    employmentType: new Set(['permanent', 'contractual', 'part-time', 'visiting', '']),
  },
  ChatbotLog: {
    userRole: ROLE_VALUES,
    language: new Set(['en', 'hi', 'as']),
  },
};

function isBcryptHash(value) {
  return typeof value === 'string' && value.startsWith('$2');
}

async function hashPasswordIfNeeded(value) {
  if (!value || typeof value !== 'string' || isBcryptHash(value)) {
    return value;
  }
  const salt = await bcrypt.genSalt(10);
  return bcrypt.hash(value, salt);
}

function normalizeFeePaymentData(data) {
  if (!data || typeof data !== 'object') return data;
  const normalized = { ...data };

  if (normalized.paymentDate && !normalized.date) {
    normalized.date = normalized.paymentDate;
  }
  if (normalized.date && !normalized.paymentDate) {
    normalized.paymentDate = normalized.date;
  }
  if (normalized.originalAmount == null && normalized.amountPaid != null) {
    normalized.originalAmount = normalized.amountPaid;
  }

  return normalized;
}

function normalizeAttendanceData(data) {
  if (!data || typeof data !== 'object') return data;
  const normalized = { ...data };

  if (normalized.date) {
    const date = new Date(normalized.date);
    if (!Number.isNaN(date.getTime())) {
      normalized.date = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
    }
  }

  if (normalized.subject === '') {
    normalized.subject = null;
  }

  return normalized;
}

function validateChatbotLogLengths(data) {
  if (!data || typeof data !== 'object') return;
  if (typeof data.message === 'string' && data.message.length > 500) {
    throw new Error('ChatbotLog.message exceeds 500 characters.');
  }
  if (typeof data.response === 'string' && data.response.length > 2000) {
    throw new Error('ChatbotLog.response exceeds 2000 characters.');
  }
}

function calculatePayrollTotals(data) {
  if (!data || typeof data !== 'object') return null;
  const fields = [
    'basicSalary',
    'hra',
    'da',
    'conveyance',
    'medicalAllowance',
    'specialAllowance',
    'pfDeduction',
    'taxDeduction',
    'otherDeductions',
  ];

  for (const field of fields) {
    const value = data[field];
    if (value !== undefined && typeof value === 'object' && value !== null) {
      return null;
    }
  }

  const basicSalary = Number(data.basicSalary || 0);
  const hra = Number(data.hra || 0);
  const da = Number(data.da || 0);
  const conveyance = Number(data.conveyance || 0);
  const medicalAllowance = Number(data.medicalAllowance || 0);
  const specialAllowance = Number(data.specialAllowance || 0);
  const pfDeduction = Number(data.pfDeduction || 0);
  const taxDeduction = Number(data.taxDeduction || 0);
  const otherDeductions = Number(data.otherDeductions || 0);

  const totalEarnings = basicSalary + hra + da + conveyance + medicalAllowance + specialAllowance;
  const totalDeductions = pfDeduction + taxDeduction + otherDeductions;

  return {
    totalEarnings,
    totalDeductions,
    netPay: totalEarnings - totalDeductions,
  };
}

function applyEnumValidation(model, data) {
  const rules = ENUM_FIELD_RULES[model];
  if (!rules || !data || typeof data !== 'object') {
    return;
  }

  Object.entries(rules).forEach(([field, allowed]) => {
    const raw = data[field];
    if (raw == null) return;
    const value = raw && typeof raw === 'object' && 'set' in raw ? raw.set : raw;
    if (value == null) return;
    if (!allowed.has(value)) {
      throw new Error(`Invalid ${model}.${field} value: ${value}`);
    }
  });
}

async function transformData(model, data) {
  if (!data || typeof data !== 'object') return data;
  let updated = { ...data };

  if (model === 'User' && 'password' in updated) {
    updated.password = await hashPasswordIfNeeded(updated.password);
  }

  if (model === 'FeePayment') {
    updated = normalizeFeePaymentData(updated);
  }

  if (model === 'Attendance') {
    updated = normalizeAttendanceData(updated);
  }

  if (model === 'ChatbotLog') {
    validateChatbotLogLengths(updated);
  }

  if (model === 'Payroll') {
    const totals = calculatePayrollTotals(updated);
    if (totals) {
      updated = { ...updated, ...totals };
    }
  }

  applyEnumValidation(model, updated);

  return updated;
}

async function transformArgs(model, args) {
  if (!args || typeof args !== 'object') return args;
  const next = { ...args };

  if (Array.isArray(next.data)) {
    next.data = await Promise.all(next.data.map(item => transformData(model, item)));
  } else if (next.data) {
    next.data = await transformData(model, next.data);
  }

  if (next.create) {
    next.create = await transformData(model, next.create);
  }
  if (next.update) {
    next.update = await transformData(model, next.update);
  }

  return next;
}

const mutatingActions = new Set(['create', 'createMany', 'update', 'updateMany', 'upsert']);

const stripResetFields = (record) => {
  if (!record || typeof record !== 'object') return record;
  const copy = { ...record };
  delete copy.passwordResetTokenHash;
  delete copy.passwordResetExpiresAt;
  return copy;
};

let prismaClient = prisma;

if (typeof prisma.$use === 'function') {
  prisma.$use(async (params, next) => {
    if (mutatingActions.has(params.action) && params.model && params.args) {
      params.args = await transformArgs(params.model, params.args);
    }

    const result = await next(params);
    if (params.model === 'User') {
      const shouldOmitResetFields = !(params.args?.select?.passwordResetTokenHash || params.args?.select?.passwordResetExpiresAt);
      if (shouldOmitResetFields) {
        if (Array.isArray(result)) {
          return result.map(stripResetFields);
        }
        return stripResetFields(result);
      }
    }
    return result;
  });
} else if (typeof prisma.$extends === 'function') {
  prismaClient = prisma.$extends({
    query: {
      $allModels: {
        $allOperations: async ({ model, operation, args, query }) => {
          if (mutatingActions.has(operation) && model && args) {
            args = await transformArgs(model, args);
          }

          const result = await query(args);
          if (model === 'User') {
            const shouldOmitResetFields = !(args?.select?.passwordResetTokenHash || args?.select?.passwordResetExpiresAt);
            if (shouldOmitResetFields) {
              if (Array.isArray(result)) {
                return result.map(stripResetFields);
              }
              return stripResetFields(result);
            }
          }
          return result;
        },
      },
    },
  });
}

module.exports = prismaClient;
