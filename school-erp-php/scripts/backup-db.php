<?php
/**
 * Database Backup Script
 * School ERP PHP v3.0
 * 
 * Usage:
 * php scripts/backup-db.php                  # Create backup
 * php scripts/backup-db.php --list           # List backups
 * php scripts/backup-db.php --restore FILE   # Restore from backup
 * php scripts/backup-db.php --cleanup        # Remove old backups (>7 days)
 * 
 * Add to cron for daily backups:
 * 0 2 * * * /usr/bin/php /path/to/scripts/backup-db.php
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/env_loader.php';

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$action = $argv[1] ?? 'backup';

// ============================================
// CREATE BACKUP
// ============================================
if ($action === 'backup' || $action === '--backup') {
    echo "🗄️ Starting database backup...\n";
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$timestamp}.sql";
    $filepath = $backupDir . '/' . $filename;
    
    // Build mysqldump command
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s --single-transaction --quick --lock-tables=false > %s 2>&1',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );
    
    // Try mysqldump first
    exec($command, $output, $returnVar);
    
    // Fallback to PHP-based backup if mysqldump fails
    if ($returnVar !== 0) {
        echo "⚠️ mysqldump failed, using PHP backup...\n";
        phpBackup($filepath);
    }
    
    if (file_exists($filepath) && filesize($filepath) > 0) {
        // Compress the backup
        $gzFile = $filepath . '.gz';
        $gzHandle = gzopen($gzFile, 'w9');
        $handle = fopen($filepath, 'r');
        
        while (!feof($handle)) {
            gzwrite($gzHandle, fread($handle, 8192));
        }
        
        fclose($handle);
        gzclose($gzHandle);
        unlink($filepath); // Remove uncompressed version
        
        $size = round(filesize($gzFile) / 1024 / 1024, 2);
        echo "✅ Backup created: $filename.gz ($size MB)\n";
        
        // Log backup
        if (db_table_exists('audit_logs')) {
            db_query(
                "INSERT INTO audit_logs (user_id, action, module, description, ip_address) VALUES (0, 'BACKUP', 'database', ?, 'cli')",
                ["Backup created: $filename.gz"]
            );
        }
    } else {
        echo "❌ Backup failed!\n";
        exit(1);
    }
}

// ============================================
// LIST BACKUPS
// ============================================
elseif ($action === '--list') {
    echo "📋 Available backups:\n\n";
    
    $files = glob($backupDir . '/backup_*.sql.gz');
    if (empty($files)) {
        echo "No backups found.\n";
        exit(0);
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        $size = round(filesize($file) / 1024 / 1024, 2);
        $date = date('Y-m-d H:i:s', filemtime($file));
        printf("  %-40s %8s MB  %s\n", $filename, $size, $date);
    }
    
    echo "\nTotal: " . count($files) . " backups\n";
}

// ============================================
// RESTORE BACKUP
// ============================================
elseif ($action === '--restore') {
    $file = $argv[2] ?? null;
    
    if (!$file) {
        echo "❌ Please specify backup file to restore\n";
        echo "Usage: php backup-db.php --restore backup_2025-04-10_02-00-00.sql.gz\n";
        exit(1);
    }
    
    $filepath = $backupDir . '/' . $file;
    
    if (!file_exists($filepath)) {
        echo "❌ File not found: $filepath\n";
        exit(1);
    }
    
    echo "⚠️ WARNING: This will overwrite the current database!\n";
    echo "Continue? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    
    if (strtolower($response) !== 'yes') {
        echo "❌ Restore cancelled\n";
        exit(0);
    }
    
    echo "🔄 Restoring from $file...\n";
    
    // Decompress if .gz
    if (strpos($file, '.gz') !== false) {
        $tempSql = $backupDir . '/temp_restore.sql';
        $gzHandle = gzopen($filepath, 'r');
        $handle = fopen($tempSql, 'w');
        
        while (!gzeof($gzHandle)) {
            fwrite($handle, gzread($gzHandle, 8192));
        }
        
        gzclose($gzHandle);
        fclose($handle);
        $sqlFile = $tempSql;
    } else {
        $sqlFile = $filepath;
    }
    
    // Execute SQL
    $command = sprintf(
        'mysql -h %s -u %s -p%s %s < %s 2>&1',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($sqlFile)
    );
    
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0) {
        echo "✅ Database restored successfully!\n";
    } else {
        echo "❌ Restore failed!\n";
        echo implode("\n", $output) . "\n";
        exit(1);
    }
    
    // Clean up temp file
    if (isset($tempSql) && file_exists($tempSql)) {
        unlink($tempSql);
    }
}

// ============================================
// CLEANUP OLD BACKUPS
// ============================================
elseif ($action === '--cleanup') {
    echo "🧹 Cleaning up old backups...\n";
    
    $files = glob($backupDir . '/backup_*.sql.gz');
    $deleted = 0;
    $cutoff = time() - (7 * 24 * 60 * 60); // 7 days
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
            $deleted++;
            echo "  Deleted: " . basename($file) . "\n";
        }
    }
    
    echo "✅ Deleted $deleted old backups\n";
}

// ============================================
// HELP
// ============================================
else {
    echo "School ERP Database Backup Tool\n\n";
    echo "Usage:\n";
    echo "  php backup-db.php                    # Create backup\n";
    echo "  php backup-db.php --list             # List backups\n";
    echo "  php backup-db.php --restore FILE     # Restore backup\n";
    echo "  php backup-db.php --cleanup          # Remove old backups\n";
    echo "\n";
}

// ============================================
// PHP-BASED BACKUP (Fallback)
// ============================================
function phpBackup($filepath) {
    global $pdo;
    
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    
    $sql = "-- School ERP PHP Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Table structure
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= $create['Create Table'] . ";\n\n";
        
        // Table data
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) continue;
        
        foreach ($rows as $row) {
            $values = array_map(function($value) use ($pdo) {
                return $value === null ? 'NULL' : $pdo->quote($value);
            }, $row);
            
            $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    file_put_contents($filepath, $sql);
}
