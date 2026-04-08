const { rateLimit } = require('express-rate-limit');

const skipLocalhost = (req) => {
  if (process.env.RATE_LIMIT_SKIP_LOCALHOST !== 'true') {
    return false;
  }

  const ip = req.ip || req.connection?.remoteAddress || '';
  return ip === '127.0.0.1' || ip === '::1' || ip === '::ffff:127.0.0.1';
};

const jwt = require('jsonwebtoken');

function getIpKey(req) {
  return req.ip || req.connection?.remoteAddress || 'unknown';
}

// Key generator for authenticated routes: use JWT user ID instead of IP.
// This prevents a shared school IP (NAT router) from blocking all staff/students.
const userKeyGenerator = (req) => {
  try {
    if (req.user) return `user_${req.user.id || req.user._id}`; // Fast path if auth already ran
    const token = req.header('Authorization')?.replace('Bearer ', '') || req.cookies?.token;
    if (token) {
      const decoded = jwt.decode(token); // Fast decode without crypto verification
      if (decoded) return `user_${decoded.user?.id || decoded.id}`;
    }
  } catch (e) {
    // Fall back to IP if token is missing or invalid
  }
  return getIpKey(req);
};

// ─── General API rate limiter (authenticated routes) ────────────────────────
// Limit by USER ID so one shared school IP doesn't block everyone.
// Production: 500 req / 15 min per user  (generous for an ERP with many API calls)
// Development: skip entirely for localhost
const apiLimiter = rateLimit({
  windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000,
  max: parseInt(process.env.RATE_LIMIT_API_MAX) || 500,
  keyGenerator: userKeyGenerator,
  skip: skipLocalhost,
  message: {
    msg: 'Too many requests, please try again after 15 minutes'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

// ─── Auth route limiter (login/register) ────────────────────────────────────
// Keep IP-based here because the user is unauthenticated (no JWT yet).
// Production: 10 attempts / 15 min per IP  (protects against brute-force)
// Development: skip localhost
const authLimiter = rateLimit({
  windowMs: parseInt(process.env.RATE_LIMIT_AUTH_WINDOW_MS) || 15 * 60 * 1000,
  max: parseInt(process.env.RATE_LIMIT_AUTH_MAX) || 10,
  skip: skipLocalhost,
  skipSuccessfulRequests: true, // Only count failed attempts
  message: {
    msg: 'Too many login attempts, please try again after 15 minutes'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

// ─── File upload limiter ─────────────────────────────────────────────────────
// Limit by user to prevent bulk upload abuse.
// Production: 50 uploads / hour per user
const uploadLimiter = rateLimit({
  windowMs: parseInt(process.env.RATE_LIMIT_UPLOAD_WINDOW_MS) || 60 * 60 * 1000,
  max: parseInt(process.env.RATE_LIMIT_UPLOAD_MAX) || 50,
  keyGenerator: userKeyGenerator,
  skip: skipLocalhost,
  message: {
    msg: 'Too many upload requests, please try again later'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

// ─── Payment limiter ─────────────────────────────────────────────────────────
// Limit by user for security. 100 fee transactions per hour is plenty.
const paymentLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 100,
  keyGenerator: userKeyGenerator,
  skip: skipLocalhost,
  message: {
    msg: 'Too many payment requests, please try again later'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

// ─── SMS limiter ─────────────────────────────────────────────────────────────
// Limit by user to prevent accidental mass SMS sending.
const smsLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 200,
  keyGenerator: userKeyGenerator,
  skip: skipLocalhost,
  message: {
    msg: 'Too many SMS requests, please try again later'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

// ─── Chatbot limiter ─────────────────────────────────────────────────────────
// Limit by user to prevent chatbot abuse/DoS.
// Production: 100 chat messages / 15 min per user (generous for conversation)
// Development: skip localhost
const chatbotLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100,
  keyGenerator: userKeyGenerator,
  skip: skipLocalhost,
  message: {
    msg: 'Too many chatbot messages, please slow down'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

module.exports = {
  apiLimiter,
  authLimiter,
  uploadLimiter,
  paymentLimiter,
  smsLimiter,
  chatbotLimiter
};
