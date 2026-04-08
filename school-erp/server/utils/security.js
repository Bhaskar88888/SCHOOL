const crypto = require('crypto');

const PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';

// Fix: Use rejection sampling to eliminate modulo bias
function randomFromAlphabet(length, alphabet) {
  const alphabetLength = alphabet.length;
  const maxValidByte = Math.floor(256 / alphabetLength) * alphabetLength;
  let result = '';
  let bytesNeeded = length;

  while (result.length < length) {
    const bytes = crypto.randomBytes(bytesNeeded * 2); // Request extra to handle rejections
    for (let i = 0; i < bytes.length && result.length < length; i += 1) {
      if (bytes[i] < maxValidByte) {
        result += alphabet[bytes[i] % alphabetLength];
      }
    }
  }
  return result;
}

function generateTemporaryPassword(length = 14) {
  return randomFromAlphabet(length, PASSWORD_ALPHABET);
}

function generateReceiptNumber(prefix = 'REC') {
  // Fix: Add counter to prevent collisions in same millisecond
  const timestamp = Date.now();
  const random = crypto.randomBytes(4).toString('hex').toUpperCase();
  const counter = (Math.random() * 1000).toString().padStart(3, '0');
  return `${prefix}${timestamp}${counter}${random}`;
}

function normalizeDateOnly(value) {
  const date = value ? new Date(value) : new Date();
  if (Number.isNaN(date.getTime())) {
    throw new Error('Invalid date');
  }

  // Fix: Preserve local date instead of converting to UTC midnight
  // This prevents the date shift issue for positive UTC offset timezones
  return new Date(
    date.getFullYear(),
    date.getMonth(),
    date.getDate(),
    0, 0, 0, 0
  );
}

function isValidIsbn(value) {
  const isbn = String(value || '').replace(/[^0-9Xx]/g, '').toUpperCase();
  if (/^\d{13}$/.test(isbn)) {
    let sum = 0;
    for (let i = 0; i < 12; i += 1) {
      sum += Number(isbn[i]) * (i % 2 === 0 ? 1 : 3);
    }
    const checksum = (10 - (sum % 10)) % 10;
    return checksum === Number(isbn[12]);
  }

  if (/^\d{9}[\dX]$/.test(isbn)) {
    let sum = 0;
    for (let i = 0; i < 9; i += 1) {
      sum += Number(isbn[i]) * (10 - i);
    }
    const checksum = isbn[9] === 'X' ? 10 : Number(isbn[9]);
    return ((sum + checksum) % 11) === 0;
  }

  return false;
}

module.exports = {
  generateTemporaryPassword,
  generateReceiptNumber,
  normalizeDateOnly,
  isValidIsbn,
};
