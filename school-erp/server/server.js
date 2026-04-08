require('dotenv').config();
const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const compression = require('compression');
const path = require('path');
const connectDB = require('./config/db');
const prisma = require('./config/prisma');
const logger = require('./config/logger');
const { successLogger, errorLogger } = require('./middleware/requestLogger');
const { apiLimiter, authLimiter, paymentLimiter, chatbotLimiter } = require('./middleware/rateLimiter');
const auth = require('./middleware/auth');
const uploadAccess = require('./middleware/uploadAccess');
const { trainChatbot } = require('./ai/nlpEngine');
const { initializeKnowledgeBase } = require('./ai/scanner');
const { createBackup } = require('./scripts/backup-db');

let schedulerLoaded = false;
let backupTimer = null;
let startupPromise = null;

function ensureSchedulerLoaded() {
  if (!schedulerLoaded) {
    require('./scheduler');
    schedulerLoaded = true;
  }
}

function parseAllowedOrigins() {
  const configured = String(process.env.FRONTEND_URL || '')
    .split(',')
    .map(origin => origin.trim())
    .filter(Boolean);

  return new Set([
    ...configured,
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3001',
    'http://localhost:3005',
    'http://127.0.0.1:3005'
  ]);
}

const allowedOrigins = parseAllowedOrigins();

function isAllowedOrigin(origin) {
  if (!origin) {
    return true;
  }

  if (allowedOrigins.has(origin)) {
    return true;
  }

  if (process.env.NODE_ENV !== 'production') {
    return /^https?:\/\/(localhost|127\.0\.0\.1|10(?:\.\d{1,3}){3}|192\.168(?:\.\d{1,3}){2}|172\.(1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(:\d+)?$/i.test(origin);
  }

  return false;
}

function scheduleBackupLoop() {
  const now = new Date();
  const tomorrow = new Date(now);
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(2, 0, 0, 0);
  const msUntilBackup = tomorrow.getTime() - now.getTime();

  backupTimer = setTimeout(async () => {
    try {
      logger.info('[SCHEDULER] Running automated database backup...');
      await createBackup();
      logger.info('[SCHEDULER] Backup completed successfully.');
    } catch (error) {
      logger.error('[SCHEDULER] Backup failed:', {
        message: error.message,
        stack: error.stack
      });
    } finally {
      scheduleBackupLoop();
    }
  }, msUntilBackup);

  logger.info(`[SCHEDULER] Next backup scheduled for ${tomorrow.toLocaleString()}`);
}

async function initializeRuntime({ skipAiBootstrap = false, skipScheduler = false } = {}) {
  if (startupPromise) {
    return startupPromise;
  }

  startupPromise = (async () => {
    if (!skipScheduler) {
      ensureSchedulerLoaded();
    }

    await connectDB();

    if (!skipAiBootstrap) {
      try {
        logger.info('Initializing Offline AI Brain...');
        await trainChatbot();
        initializeKnowledgeBase();
        logger.info('✅ Offline AI Brain initialized successfully.');
      } catch (error) {
        logger.error('❌ Failed to initialize AI Brain (non-fatal, chatbot will be unavailable):', {
          message: error.message,
          stack: error.stack
        });
      }
    }
  })();

  try {
    await startupPromise;
  } catch (error) {
    startupPromise = null;
    throw error;
  }
}

function createApp() {
  const app = express();

  logger.info('🚀 School ERP Server starting...');

  // Security middleware
  app.use(helmet({
    contentSecurityPolicy: process.env.NODE_ENV === 'production',
    crossOriginEmbedderPolicy: false
  }));

  // CORS configuration
  app.use(cors({
    origin: function (origin, callback) {
      // Allow requests with no origin (like mobile apps or curl requests)
      if (!origin) return callback(null, true);
      callback(null, true); // Allow ALL origins for local development and LAN
    },
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization']
  }));

  // Request logging with Winston
  app.use(successLogger);
  app.use(errorLogger);

  app.use(require('cookie-parser')());

  // Body parsing middleware with size limits
  app.use(express.json({ limit: '10mb' }));
  app.use(express.urlencoded({ extended: true, limit: '10mb' }));
  app.use(compression());

  // Serve uploaded files through authenticated access checks
  app.use('/uploads', auth, uploadAccess);

  // Serve static files from public directory
  app.use(express.static(path.join(__dirname, 'public')));

  // Apply rate limiting to all API routes
  app.use('/api', apiLimiter);

  // Mount routes with specific rate limiters
  app.use('/api/auth', authLimiter, require('./routes/auth'));
  app.use('/api/classes', require('./routes/class'));
  app.use('/api/students', require('./routes/student'));
  app.use('/api/attendance', require('./routes/attendance'));
  app.use('/api/routine', require('./routes/routine'));
  app.use('/api/routines', require('./routes/routine'));
  app.use('/api/leave', require('./routes/leave'));
  app.use('/api/leaves', require('./routes/leave'));
  app.use('/api/fee', paymentLimiter, require('./routes/fee'));
  app.use('/api/fees', paymentLimiter, require('./routes/fee'));
  app.use('/api/payroll', paymentLimiter, require('./routes/payroll'));
  app.use('/api/remarks', require('./routes/remarks'));
  app.use('/api/complaints', require('./routes/complaints'));
  app.use('/api/exams', require('./routes/exams'));
  app.use('/api/notices', require('./routes/notices'));
  app.use('/api/canteen', require('./routes/canteen'));
  app.use('/api/dashboard', require('./routes/dashboard'));
  app.use('/api/homework', require('./routes/homework'));
  app.use('/api/notifications', require('./routes/notifications'));
  app.use('/api/transport', require('./routes/transport'));
  app.use('/api/bus-routes', require('./routes/busRoutes'));
  // Legacy alias for older clients that still call /api/transport/routes
  app.use('/api/transport/routes', require('./routes/busRoutes'));
  app.use('/api/hostel', require('./routes/hostel'));
  app.use('/api/salary-setup', require('./routes/salarySetup'));
  app.use('/api/staff-attendance', require('./routes/staffAttendance'));
  app.use('/api/library', require('./routes/library'));
  app.use('/api/pdf', require('./routes/pdf'));
  app.use('/api/export', require('./routes/export'));
  app.use('/api/import', require('./routes/import'));
  app.use('/api/tally', require('./routes/tally'));
  app.use('/api/chatbot', chatbotLimiter, require('./routes/chatbot'));
  app.use('/api/archive', require('./routes/archive'));
  app.use('/api/audit', require('./routes/audit'));

  // Health check endpoint
  app.get('/api/health', async (_req, res) => {
    let isDbHealthy = false;
    try {
      await prisma.$queryRawUnsafe('SELECT 1');
      isDbHealthy = true;
    } catch (err) {
      isDbHealthy = false;
    }
    const statusCode = isDbHealthy ? 200 : 503;
    res.status(statusCode).json({
      status: isDbHealthy ? 'ok' : 'degraded',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      database: isDbHealthy ? 'connected' : 'disconnected',
      environment: process.env.NODE_ENV || 'development'
    });
  });

  // Error handling middleware
  app.use((err, req, res, _next) => {
    logger.error('Error:', {
      message: err.message,
      stack: err.stack,
      path: req.path,
      method: req.method,
      ip: req.ip
    });

    if (err.code === 'LIMIT_FILE_SIZE') {
      return res.status(400).json({
        msg: 'File size too large. Maximum size is 5MB.'
      });
    }

    if (err.code === 'LIMIT_UNEXPECTED_FILE') {
      return res.status(400).json({
        msg: 'Unexpected file field'
      });
    }

    if (err instanceof SyntaxError && err.status === 400 && 'body' in err) {
      return res.status(400).json({
        msg: 'Invalid JSON format'
      });
    }

    res.status(err.status || 500).json({
      msg: process.env.NODE_ENV === 'production' ? 'Internal server error' : err.message,
      error: process.env.NODE_ENV === 'development' ? err.stack : undefined
    });
  });

  // 404 handler
  app.use((req, res) => {
    res.status(404).json({
      msg: 'Route not found',
      path: req.originalUrl
    });
  });

  return app;
}

async function startServer(options = {}) {
  const {
    port = process.env.PORT || 5000,
    skipAiBootstrap = process.env.NODE_ENV === 'test',
    skipScheduler = process.env.NODE_ENV === 'test',
  } = options;

  const app = createApp();
  await initializeRuntime({ skipAiBootstrap, skipScheduler });

  const server = await new Promise((resolve, reject) => {
    const instance = app.listen(port, () => resolve(instance));
    instance.on('error', reject);
  });

  const boundAddress = typeof server.address === 'function' ? server.address() : null;
  const resolvedPort =
    boundAddress && typeof boundAddress === 'object' && boundAddress.port
      ? boundAddress.port
      : port;

  if (!skipAiBootstrap) {
    logger.info(`
╔═══════════════════════════════════════════════╗
║                                               ║
║   🏫 EduGlass School ERP Server               ║
║                                               ║
║   Server running on port ${resolvedPort}               ║
║   Environment: ${process.env.NODE_ENV || 'development'}                   ║
║   Database: MySQL Connected                 ║
║   Offline AI: Active & Trained                ║
║   Logging: Winston + Morgan Active            ║
║   Security: Helmet + CORS + Rate Limiting     ║
║                                               ║
║   API: http://localhost:${resolvedPort}/api            ║
║   Health: http://localhost:${resolvedPort}/api/health  ║
║                                               ║
╚═══════════════════════════════════════════════╝
  `);
  } else {
    logger.info(`School ERP Server listening on port ${resolvedPort} (test mode)`);
  }

  if (!skipScheduler && process.env.ENABLE_AUTO_BACKUPS !== 'false' && !backupTimer) {
    scheduleBackupLoop();
  }

  return { app, server };
}

if (require.main === module) {
  startServer().catch((error) => {
    logger.error('Failed to start server', {
      message: error.message,
      stack: error.stack
    });
    process.exit(1);
  });
}

module.exports = {
  createApp,
  startServer,
};
