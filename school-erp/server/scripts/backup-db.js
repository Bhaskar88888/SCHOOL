/**
 * Automated Database Backup Script
 * Creates daily MySQL backups
 * Keeps last 30 days of backups
 */

const { exec } = require('child_process');
const fs = require('fs');
const path = require('path');
const logger = require('../config/logger');
require('dotenv').config();

const BACKUP_DIR = path.join(__dirname, '../backups');
const MAX_BACKUPS = 30;
const DB_URL = process.env.DATABASE_URL || 'mysql://root:@localhost:3306/school_erp_mysql';

function createBackup() {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const backupFile = `backup_${timestamp}.sql`;
  const backupPath = path.join(BACKUP_DIR, backupFile);

  logger.info(`[BACKUP] Starting database backup: ${backupFile}`);

  // Create backups directory if it doesn't exist
  if (!fs.existsSync(BACKUP_DIR)) {
    fs.mkdirSync(BACKUP_DIR, { recursive: true });
    logger.info(`[BACKUP] Created backups directory: ${BACKUP_DIR}`);
  }

  // Parse DATABASE_URL
  const match = DB_URL.match(/mysql:\/\/([^:]+):([^@]*)@([^:]+):(\d+)\/(.+)/);
  if (!match) {
    logger.error(`[BACKUP] Failed: Invalid DATABASE_URL format for backup`);
    return;
  }
  const [, user, pass, host, port, dbname] = match;
  
  const passArg = pass ? `-p"${pass}"` : '';
  // Try calling mysqldump via full XAMPP path if local test, or regular mysqldump if system PATH has it
  const mysqldumpBin = process.platform === 'win32' && process.env.NODE_ENV !== 'production' 
    ? `"C:\\xampp\\mysql\\bin\\mysqldump.exe"` 
    : 'mysqldump';
    
  const dumpCommand = `${mysqldumpBin} -u ${user} ${passArg} -h ${host} -P ${port} ${dbname} > "${backupPath}"`;

  exec(dumpCommand, (error, stdout, stderr) => {
    if (error) {
      logger.error(`[BACKUP] Failed: ${error.message}. Note: 'mysqldump' must be in PATH.`);
      return;
    }

    logger.info(`[BACKUP] Completed successfully: ${backupFile}`);
    logger.info(`[BACKUP] Location: ${backupPath}`);

    // Cleanup old backups
    cleanupOldBackups();
  });
}

function cleanupOldBackups() {
  try {
    const backups = fs.readdirSync(BACKUP_DIR)
      .filter(f => f.startsWith('backup_') && f.endsWith('.sql'))
      .sort()
      .reverse();

    if (backups.length > MAX_BACKUPS) {
      const toDelete = backups.slice(MAX_BACKUPS);

      toDelete.forEach(backup => {
        const backupPath = path.join(BACKUP_DIR, backup);
        fs.unlinkSync(backupPath);
        logger.info(`[BACKUP] Deleted old backup: ${backup}`);
      });

      logger.info(`[BACKUP] Cleaned up ${toDelete.length} old backups, keeping last ${MAX_BACKUPS}`);
    }
  } catch (err) {
    logger.error(`[BACKUP] Cleanup failed: ${err.message}`);
  }
}

// If run directly
if (require.main === module) {
  logger.info('[BACKUP] Manual backup triggered');
  createBackup();
}

module.exports = { createBackup, cleanupOldBackups };
