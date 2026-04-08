/**
 * Winston Logger Configuration
 * Production-grade logging with file rotation
 */

const winston = require('winston');
const path = require('path');

// Create logs directory if it doesn't exist
const fs = require('fs');
const logsDir = path.join(__dirname, '../logs');
let canWriteLogs = true;
try {
  if (!fs.existsSync(logsDir)) {
    fs.mkdirSync(logsDir, { recursive: true });
  }
} catch (error) {
  console.warn('Logging to filesystem disabled (no permission). Falling back to console only.');
  canWriteLogs = false;
}

const transports = [];

// Only add file transports if we have permission to write to the folder
if (canWriteLogs) {
  transports.push(
    new winston.transports.File({
      filename: path.join(logsDir, 'error.log'),
      level: 'error',
      maxsize: 5242880, // 5MB
      maxFiles: 30,
    }),
    new winston.transports.File({
      filename: path.join(logsDir, 'combined.log'),
      maxsize: 5242880,
      maxFiles: 30,
    }),
    new winston.transports.File({
      filename: path.join(logsDir, 'activity.log'),
      maxsize: 5242880,
      maxFiles: 90,
    })
  );
}

// In cloud environments like Render/Heroku, we must log to console to see logs in the dashboard
transports.push(new winston.transports.Console({
  format: winston.format.combine(
    winston.format.colorize(),
    winston.format.simple()
  )
}));

const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: winston.format.combine(
    winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss' }),
    winston.format.errors({ stack: true }),
    winston.format.json()
  ),
  defaultMeta: { service: 'school-erp-api' },
  transports: transports
});

// Convenience methods
logger.activity = (message, meta) => {
  logger.info('[ACTIVITY] ' + message, meta);
};

logger.audit = (message, meta) => {
  logger.info('[AUDIT] ' + message, meta);
};

logger.security = (message, meta) => {
  logger.warn('[SECURITY] ' + message, meta);
};

module.exports = logger;
