/**
 * Audit Middleware
 * Automatically logs critical operations
 */

const prisma = require('../config/prisma');
const logger = require('../config/logger');

const auditMiddleware = (module_name) => {
  return (req, res, next) => {
    // Store original methods
    const originalJson = res.json;
    const originalSend = res.send;

    // Determine action from HTTP method
    const actionMap = {
      'POST': 'CREATE',
      'PUT': 'UPDATE',
      'PATCH': 'UPDATE',
      'DELETE': 'DELETE'
    };

    const action = actionMap[req.method];
    if (!action) {
      return next(); // Skip GET requests
    }

    const sensitiveFields = new Set([
      'password',
      'confirmPassword',
      'oldPassword',
      'newPassword',
      'token',
      'resetToken',
      'passwordResetToken',
      'passwordResetTokenHash',
      'passwordResetExpiresAt',
    ]);

    function sanitizePayload(payload) {
      if (!payload || typeof payload !== 'object') return payload;
      const clone = Array.isArray(payload) ? payload.map(item => sanitizePayload(item)) : { ...payload };
      Object.keys(clone).forEach((key) => {
        if (sensitiveFields.has(key)) {
          clone[key] = '***REDACTED***';
        } else if (typeof clone[key] === 'object' && clone[key] !== null) {
          clone[key] = sanitizePayload(clone[key]);
        }
      });
      return clone;
    }

    function logAudit(data) {
      if (!req.user || !data || data.success === false) return;

      const recordId =
        data?.data?.id ||
        data?.data?._id ||
        data?.recordId ||
        req.params.id;

      const auditEntry = {
        userId: req.user.id || req.user._id,
        action,
        module: module_name,
        recordId,
        oldValue: req.method !== 'POST' ? sanitizePayload(req.body) : undefined,
        newValue: sanitizePayload(data.data || req.body),
        ipAddress: req.ip || req.connection.remoteAddress,
        userAgent: req.headers['user-agent']
      };

      prisma.auditLog.create({ data: auditEntry }).catch(err => {
        logger.error('Audit log creation failed:', err);
      });

      logger.activity(`${action} ${module_name} by ${req.user.email}`, {
        userId: req.user.id || req.user._id,
        action,
        module: module_name,
        recordId: auditEntry.recordId
      });
    }

    res.json = function(data) {
      logAudit(data);
      return originalJson.call(this, data);
    };

    res.send = function(data) {
      try {
        const parsed = typeof data === 'string' ? JSON.parse(data) : data;
        logAudit(parsed);
      } catch (_err) {
        // Non-JSON response, skip audit payload capture
      }
      return originalSend.call(this, data);
    };

    next();
  };
};

module.exports = auditMiddleware;
