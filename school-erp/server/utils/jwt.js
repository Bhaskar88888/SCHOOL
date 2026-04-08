const crypto = require('crypto');
const jwt = require('jsonwebtoken');

const INSECURE_JWT_SECRETS = new Set([
  '',
  'secret',
  'secret123',
  'your_jwt_secret',
  'change_me',
  'changeme',
  'jwt_secret',
  'super_secret_key_1234567890abcdefghijklmnopqrstuvwxyz',
]);

let cachedSecret = null;

function calculateEntropy(secret) {
  const charCounts = {};
  for (const char of secret) {
    charCounts[char] = (charCounts[char] || 0) + 1;
  }
  const length = secret.length;
  let entropy = 0;
  for (const count of Object.values(charCounts)) {
    const p = count / length;
    if (p > 0) entropy -= p * Math.log2(p);
  }
  return entropy;
}

function isSecureJwtSecret(secret) {
  if (!secret || secret.length < 32 || INSECURE_JWT_SECRETS.has(secret)) return false;
  // Fix: Add entropy check to prevent predictable secrets
  const entropy = calculateEntropy(secret);
  return entropy >= 3.0; // Minimum 3 bits per character entropy
}

function getJwtSecret() {
  if (cachedSecret) return cachedSecret;

  const configuredSecret = String(process.env.JWT_SECRET || '').trim();
  if (isSecureJwtSecret(configuredSecret)) {
    cachedSecret = configuredSecret;
    return cachedSecret;
  }

  if (process.env.NODE_ENV === 'production') {
    throw new Error('JWT_SECRET is missing or insecure. Set a unique secret with at least 32 characters.');
  }

  cachedSecret = crypto.randomBytes(48).toString('hex');
  console.warn('WARNING: insecure JWT_SECRET detected. Using an ephemeral development secret for this session.');
  return cachedSecret;
}

// Fix: signJwt now properly handles both sync and async usage
function signJwt(payload, options, callback) {
  // If callback provided, operate asynchronously
  if (typeof callback === 'function') {
    jwt.sign(payload, getJwtSecret(), options, callback);
    return; // Explicitly return undefined to avoid confusion
  }
  // Synchronous operation - returns the token string
  return jwt.sign(payload, getJwtSecret(), options);
}

// Fix: verifyJwt now catches all JWT errors properly
function verifyJwt(token) {
  try {
    return jwt.verify(token, getJwtSecret());
  } catch (error) {
    if (error instanceof jwt.TokenExpiredError) {
      throw new Error('Token has expired');
    }
    if (error instanceof jwt.JsonWebTokenError) {
      throw new Error('Invalid token');
    }
    if (error instanceof jwt.NotBeforeError) {
      throw new Error('Token not yet valid');
    }
    throw error;
  }
}

// Async version for middleware that prefers async/await
async function verifyJwtAsync(token) {
  return verifyJwt(token);
}

module.exports = {
  getJwtSecret,
  isSecureJwtSecret,
  signJwt,
  verifyJwt,
  verifyJwtAsync,
};
