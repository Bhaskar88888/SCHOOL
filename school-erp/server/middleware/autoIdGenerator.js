/**
 * Middleware for Auto-Generating IDs
 * Automatically generates Student ID and Staff ID on creation
 */

const { 
  generateStudentId, 
  generateStaffId, 
  generateTeacherId, 
  generateDriverId, 
  generateConductorId 
} = require('../utils/generateId');

/**
 * Middleware to auto-generate Student ID
 */
const autoGenerateStudentId = async (req, res, next) => {
  try {
    if (req.body && !req.body.studentId) {
      req.body.studentId = await generateStudentId();
    }
    next();
  } catch (error) {
    console.error('Error in autoGenerateStudentId middleware:', error);
    res.status(500).json({ msg: 'Error generating student ID', error: error.message });
  }
};

/**
 * Middleware to auto-generate Staff ID
 */
const autoGenerateStaffId = async (req, res, next) => {
  try {
    if (req.body && !req.body.employeeId) {
      req.body.employeeId = await generateStaffId();
    }
    next();
  } catch (error) {
    console.error('Error in autoGenerateStaffId middleware:', error);
    res.status(500).json({ msg: 'Error generating staff ID', error: error.message });
  }
};

/**
 * Middleware to auto-generate ID based on role
 * Handles teacher, driver, conductor, and general staff
 */
const autoGenerateIdByRole = async (req, res, next) => {
  try {
    if (!req.body || req.body.employeeId) {
      return next();
    }

    const role = req.body.role;
    let generatedId;

    switch (role) {
      case 'teacher':
        generatedId = await generateTeacherId();
        break;
      case 'driver':
        generatedId = await generateDriverId();
        break;
      case 'conductor':
        generatedId = await generateConductorId();
        break;
      default:
        generatedId = await generateStaffId();
    }

    req.body.employeeId = generatedId;
    next();
  } catch (error) {
    console.error('Error in autoGenerateIdByRole middleware:', error);
    res.status(500).json({ msg: 'Error generating ID', error: error.message });
  }
};

module.exports = {
  autoGenerateStudentId,
  autoGenerateStaffId,
  autoGenerateIdByRole
};
