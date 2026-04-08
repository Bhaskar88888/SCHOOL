/**
 * Winston Logger Configuration
 * Production-grade logging with file rotation
 */

const winston = require('winston');
const path = require('path');

// Create logs directory if it doesn't exist
const fs = require('fs');
const logsDir = path.join(__dirname, '../logs');
if (!fs.existsSync(logsDir)) {
  fs.mkdirSync(logsDir, { recursive: true });
}

const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: winston.format.combine(
    winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss' }),
    winston.format.errors({ stack: true }),
    winston.format.json()
  ),
  defaultMeta: { service: 'school-erp-api' },
  transports: [
    // Error logs
    new winston.transports.File({
      filename: path.join(logsDir, 'error.log'),
      level: 'error',
      maxsize: 5242880, // 5MB
      maxFiles: 30,
    }),
    // Combined logs
    new winston.transports.File({
      filename: path.join(logsDir, 'combined.log'),
      maxsize: 5242880,
      maxFiles: 30,
    }),
    // Activity logs (audit trail)
    new winston.transports.File({
      filename: path.join(logsDir, 'activity.log'),
      maxsize: 5242880,
      maxFiles: 90,
    })
  ]
});

// Console logging in development
if (process.env.NODE_ENV !== 'production') {
  logger.add(new winston.transports.Console({
    format: winston.format.combine(
      winston.format.colorize(),
      winston.format.simple()
    )
  }));
}

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
