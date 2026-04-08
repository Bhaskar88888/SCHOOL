/**
 * Morgan Request Logger Middleware
 * Logs all HTTP requests with timing
 */

const morgan = require('morgan');
const logger = require('../config/logger');

// Custom Morgan format
const morganFormat = ':method :url :status :response-time ms - :res[content-length]';

// Create Morgan stream that writes to Winston
const morganStream = {
  write: (message) => {
    logger.info(message.trim());
  }
};

// Error logger for 4xx and 5xx responses
const errorLogger = morgan(morganFormat, {
  skip: (req, res) => res.statusCode < 400,
  stream: {
    write: (message) => {
      logger.error(message.trim());
    }
  }
});

// Success logger for 2xx and 3xx responses
const successLogger = morgan(morganFormat, {
  skip: (req, res) => res.statusCode >= 400,
  stream: morganStream
});

module.exports = {
  successLogger,
  errorLogger
};
