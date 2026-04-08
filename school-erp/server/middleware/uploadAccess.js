const path = require('path');
const prisma = require('../config/prisma');
const { canUserAccessStudent } = require('../utils/accessScope');

const uploadsRoot = path.join(__dirname, '..', 'uploads');
const ADMIN_UPLOAD_ROLES = new Set(['superadmin', 'hr', 'accounts']);

function normalizeRelativeUploadPath(requestPath) {
  const relativePath = path.posix.normalize(String(requestPath || '').replace(/^\/+/, ''));
  if (!relativePath || relativePath.startsWith('..') || relativePath.includes('../')) {
    return null;
  }
  return relativePath;
}

async function canAccessStudentUpload(user, relativePath) {
  const uploadUrl = `/uploads/${relativePath}`;
  const student = await prisma.student.findFirst({
    where: {
      OR: [
        { tcFileUrl: uploadUrl },
        { birthCertFileUrl: uploadUrl },
      ],
    },
    select: { id: true },
  });

  if (!student) return false;
  if (ADMIN_UPLOAD_ROLES.has(user.role)) return true;
  return canUserAccessStudent(user, student.id);
}

async function canAccessStaffUpload(user, relativePath) {
  if (ADMIN_UPLOAD_ROLES.has(user.role)) return true;

  const uploadUrl = `/uploads/${relativePath}`;
  const owner = await prisma.user.findFirst({
    where: { profilePhoto: uploadUrl },
    select: { id: true },
  });
  return Boolean(owner && String(owner.id) === String(user.id));
}

module.exports = async (req, res) => {
  try {
    const relativePath = normalizeRelativeUploadPath(req.path);
    if (!relativePath) {
      return res.status(400).json({ msg: 'Invalid upload path' });
    }

    const [folder] = relativePath.split('/');
    let allowed = false;

    if (folder === 'students') {
      allowed = await canAccessStudentUpload(req.user, relativePath);
    } else if (folder === 'staff') {
      allowed = await canAccessStaffUpload(req.user, relativePath);
    } else if (folder === 'documents') {
      allowed = ADMIN_UPLOAD_ROLES.has(req.user.role);
    }

    if (!allowed) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    return res.sendFile(relativePath, { root: uploadsRoot });
  } catch (err) {
    console.error('Upload access error:', err);
    return res.status(500).json({ msg: 'Server Error' });
  }
};
