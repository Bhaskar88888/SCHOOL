const prisma = require('../config/prisma');

/**
 * Middleware to ensure students can only access their own data
 */
async function studentDataScope(req, res, next) {
  try {
    if (req.user.role === 'student') {
      const student = await prisma.student.findFirst({ where: { userId: req.user.id } });
      
      if (!student) {
        return res.status(404).json({ msg: 'Student profile not found' });
      }
      
      // Attach student info to request
      req.studentScope = {
        studentId: student.id,
        admissionNo: student.admissionNo,
        classId: student.classId
      };
    }
    
    next();
  } catch (err) {
    console.error('Student data scope error:', err);
    res.status(500).json({ msg: 'Server error' });
  }
}

/**
 * Middleware to ensure parents can only access their children's data
 */
async function parentDataScope(req, res, next) {
  try {
    if (req.user.role === 'parent') {
      const children = await prisma.student.findMany({
        where: { parentUserId: req.user.id },
        select: { id: true },
      });
      
      // Attach children IDs to request
      req.parentScope = {
        childrenIds: children.map(c => c.id),
        childrenCount: children.length
      };
      
      if (children.length === 0) {
        req.parentScope.noChildren = true;
      }
    }
    
    next();
  } catch (err) {
    console.error('Parent data scope error:', err);
    res.status(500).json({ msg: 'Server error' });
  }
}

/**
 * Helper to apply student/parent scope to queries
 * Use this in your route handlers
 */
function applyDataScope(where, req) {
  const baseWhere = { ...(where || {}) };

  if (req.user.role === 'student' && req.studentScope) {
    return { ...baseWhere, userId: req.user.id };
  }
  
  if (req.user.role === 'parent' && req.parentScope) {
    if (req.parentScope.noChildren) {
      return { ...baseWhere, id: { in: [] } };
    }
    return { ...baseWhere, id: { in: req.parentScope.childrenIds } };
  }
  
  return baseWhere;
}

module.exports = {
  studentDataScope,
  parentDataScope,
  applyDataScope
};
