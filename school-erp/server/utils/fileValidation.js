const fs = require('fs/promises');
const path = require('path');

// Maximum file size: 10MB (configurable)
const MAX_FILE_SIZE = parseInt(process.env.MAX_UPLOAD_SIZE) || 10 * 1024 * 1024;

const MIME_SIGNATURES = {
  'image/jpeg': [Buffer.from([0xff, 0xd8, 0xff])],
  'image/png': [Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])],
  'application/pdf': [Buffer.from('%PDF-')],
  'application/msword': [Buffer.from([0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1])],
  // Fix: Add more specific Office OpenXML signatures
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': [
    Buffer.from([0x50, 0x4b, 0x03, 0x04]), // ZIP format
    Buffer.from([0x50, 0x4b, 0x05, 0x06]), // Empty ZIP archive
  ],
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': [
    Buffer.from([0x50, 0x4b, 0x03, 0x04]),
  ],
};

const MIME_EXTENSIONS = {
  'image/jpeg': new Set(['.jpg', '.jpeg']),
  'image/png': new Set(['.png']),
  'application/pdf': new Set(['.pdf']),
  'application/msword': new Set(['.doc']),
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': new Set(['.docx']),
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': new Set(['.xlsx']),
};

function flattenFiles(filesInput) {
  if (!filesInput) return [];
  if (Array.isArray(filesInput)) return filesInput;
  // Fix: Handle nested objects better
  const values = Object.values(filesInput);
  return values.flat(Infinity);
}

function matchesAnySignature(buffer, signatures) {
  return signatures.some(signature => {
    if (buffer.length < signature.length) return false;
    return buffer.subarray(0, signature.length).equals(signature);
  });
}

async function validateUploadedFiles(filesInput) {
  const files = flattenFiles(filesInput);

  for (const file of files) {
    // Fix: Add file size validation
    if (file.size && file.size > MAX_FILE_SIZE) {
      throw new Error(`File ${file.originalname || 'upload'} exceeds maximum size of ${Math.round(MAX_FILE_SIZE / 1024 / 1024)}MB.`);
    }

    const allowedExtensions = MIME_EXTENSIONS[file.mimetype];
    const allowedSignatures = MIME_SIGNATURES[file.mimetype];
    const extension = path.extname(file.originalname || '').toLowerCase();

    if (!allowedExtensions || !allowedExtensions.has(extension) || !allowedSignatures) {
      throw new Error(`Unsupported file type for ${file.originalname || 'upload'}.`);
    }

    const fileHandle = await fs.open(file.path, 'r');
    try {
      // Fix: Read more bytes for better signature matching
      const { buffer, bytesRead } = await fileHandle.read(Buffer.alloc(32), 0, 32, 0);
      if (!bytesRead || !matchesAnySignature(buffer, allowedSignatures)) {
        throw new Error(`File content does not match the declared type for ${file.originalname || 'upload'}.`);
      }
    } finally {
      await fileHandle.close();
    }
  }
}

async function cleanupUploadedFiles(filesInput) {
  const files = flattenFiles(filesInput);
  await Promise.all(files.map(async (file) => {
    if (!file?.path) return;
    try {
      await fs.unlink(file.path);
    } catch (error) {
      if (error.code !== 'ENOENT') {
        console.error('Failed to remove invalid upload:', error.message);
      }
    }
  }));
}

module.exports = {
  validateUploadedFiles,
  cleanupUploadedFiles,
};
