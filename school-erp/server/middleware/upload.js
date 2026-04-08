const multer = require('multer');
const path = require('path');
const fs = require('fs');

// Ensure uploads directory exists
const uploadsDir = path.join(__dirname, '..', 'uploads');
const studentDir = path.join(uploadsDir, 'students');
const staffDir = path.join(uploadsDir, 'staff');
const documentsDir = path.join(uploadsDir, 'documents');

[uploadsDir, studentDir, staffDir, documentsDir].forEach(dir => {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
});

// Storage configuration
const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    let targetDir = documentsDir;
    
    if (file.fieldname === 'tcFile' || file.fieldname === 'birthCertFile') {
      targetDir = studentDir;
    } else if (file.fieldname === 'profilePhoto' || file.fieldname === 'resume' || file.fieldname === 'documents') {
      targetDir = staffDir;
    }
    
    cb(null, targetDir);
  },
  filename: function (req, file, cb) {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, file.fieldname + '-' + uniqueSuffix + path.extname(file.originalname));
  }
});

const ALLOWED_MIME_TYPES = new Set([
  'image/jpeg',
  'image/png',
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

const ALLOWED_EXTENSIONS = new Set(['.jpg', '.jpeg', '.png', '.pdf', '.doc', '.docx']);

// File filter - only allow certain file types
const fileFilter = (req, file, cb) => {
  const extension = path.extname(file.originalname).toLowerCase();
  const extname = ALLOWED_EXTENSIONS.has(extension);
  const mimetype = ALLOWED_MIME_TYPES.has(file.mimetype);

  if (mimetype && extname) {
    return cb(null, true);
  } else {
    cb(new Error('Only JPEG, PNG, PDF, DOC, and DOCX files are allowed'));
  }
};

// Upload middleware
const upload = multer({
  storage: storage,
  limits: {
    fileSize: 5 * 1024 * 1024 // 5MB limit
  },
  fileFilter: fileFilter
});

module.exports = upload;
