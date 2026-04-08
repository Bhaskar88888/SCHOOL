/**
 * Input Validators
 * Server-side validation for all critical endpoints
 */

const { body, param, query, validationResult } = require('express-validator');
const logger = require('../config/logger');

// Validation error handler middleware
const handleValidationErrors = (req, res, next) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) {
    logger.warn('Validation failed:', { errors: errors.array(), path: req.path });
    return res.status(400).json({
      success: false,
      error: {
        code: 'VALIDATION_ERROR',
        message: 'Invalid input data',
        details: errors.array().map(err => ({
          field: err.path,
          message: err.msg
        }))
      }
    });
  }
  next();
};

// Student validators
const validateStudent = [
  body('name').trim().notEmpty().isLength({ min: 2, max: 100 })
    .withMessage('Name must be 2-100 characters'),
  body('classId').isMongoId().withMessage('Valid class ID is required'),
  body('parentPhone').isMobilePhone('any').withMessage('Valid parent phone is required'),
  body('gender').isIn(['male', 'female', 'other']).withMessage('Invalid gender'),
  body('dob').isDate().withMessage('Valid date of birth is required'),
  body('admissionNo').optional().trim().isLength({ max: 50 }),
  body('bloodGroup').optional().isIn(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', '']),
  body('category').optional().isIn(['General', 'OBC', 'SC', 'ST', 'EWS', '']),
  body('email').optional().isEmail().normalizeEmail(),
  handleValidationErrors
];

// User validators
const validateUser = [
  body('name').trim().notEmpty().isLength({ min: 2, max: 100 }),
  body('email').isEmail().normalizeEmail().withMessage('Valid email is required'),
  body('phone').isMobilePhone('any').withMessage('Valid phone is required'),
  body('role').isIn(['superadmin', 'teacher', 'student', 'parent', 'staff', 'hr', 'accounts', 'canteen', 'conductor', 'driver']),
  body('password').optional().isLength({ min: 6 }).withMessage('Password must be at least 6 characters'),
  handleValidationErrors
];

// Fee payment validators
const validateFeePayment = [
  body('studentId').isMongoId().withMessage('Valid student ID is required'),
  body('amountPaid').isFloat({ min: 1 }).withMessage('Amount must be greater than 0'),
  body('paymentMode').isIn(['cash', 'online', 'cheque']).withMessage('Invalid payment mode'),
  body('feeType').optional().trim(),
  handleValidationErrors
];

// Exam validators
const validateExam = [
  body('name').trim().notEmpty().isLength({ min: 2, max: 100 }),
  body('classId').isMongoId().withMessage('Valid class ID is required'),
  body('subject').trim().notEmpty(),
  body('date').isDate().withMessage('Valid exam date is required'),
  body('totalMarks').isInt({ min: 1 }).withMessage('Total marks must be positive'),
  body('passingMarks').isInt({ min: 0 }).withMessage('Passing marks must be non-negative'),
  handleValidationErrors
];

// Attendance validators
const validateAttendance = [
  body('studentId').isMongoId().withMessage('Valid student ID is required'),
  body('classId').isMongoId().withMessage('Valid class ID is required'),
  body('date').isDate().withMessage('Valid date is required'),
  body('status').isIn(['present', 'absent', 'late', 'half-day']).withMessage('Invalid status'),
  handleValidationErrors
];

// Complaint validators
const validateComplaint = [
  body('subject').trim().notEmpty().isLength({ min: 5, max: 200 }),
  body('description').trim().notEmpty().isLength({ min: 10, max: 2000 }),
  body('type').isIn(['teacher_to_parent', 'parent_to_teacher', 'student_to_admin', 'parent_to_admin', 'general']),
  handleValidationErrors
];

// Password change validators
const validatePasswordChange = [
  body('currentPassword').notEmpty().withMessage('Current password is required'),
  body('newPassword').isLength({ min: 6 }).withMessage('New password must be at least 6 characters'),
  handleValidationErrors
];

// Leave validators
const validateLeave = [
  body('type').isIn(['sick', 'casual', 'earned', 'unpaid']),
  body('fromDate').isDate().withMessage('Valid from date is required'),
  body('toDate').isDate().withMessage('Valid to date is required'),
  body('reason').trim().notEmpty().isLength({ min: 10, max: 1000 }),
  handleValidationErrors
];

// Export all validators
module.exports = {
  validateStudent,
  validateUser,
  validateFeePayment,
  validateExam,
  validateAttendance,
  validateComplaint,
  validatePasswordChange,
  validateLeave,
  handleValidationErrors
};
