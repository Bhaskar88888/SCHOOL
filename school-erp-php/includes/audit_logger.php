<?php
/**
 * Enhanced Audit Logger
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../config/env.php';

class AuditLogger {
    
    /**
     * Log an action
     */
    public static function log($action, $module, $recordId = null, $oldValue = null, $newValue = null) {
        try {
            $userId = get_current_user_id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $sql = "INSERT INTO audit_logs_enhanced 
                    (user_id, action, module, record_id, old_value, new_value, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $userId,
                $action,
                $module,
                $recordId,
                $oldValue ? json_encode($oldValue) : null,
                $newValue ? json_encode($newValue) : null,
                $ipAddress,
                substr($userAgent, 0, 255)
            ];
            
            db_query($sql, $params);
        } catch (Exception $e) {
            // Log silently fails - don't break the application
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log login attempt
     */
    public static function loginAttempt($email, $success, $userId = null) {
        self::log(
            $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED',
            'auth',
            $userId,
            null,
            ['email' => $email, 'success' => $success]
        );
    }
    
    /**
     * Log CRUD operations
     */
    public static function crud($action, $module, $recordId, $oldValue = null, $newValue = null) {
        self::log($action, $module, $recordId, $oldValue, $newValue);
    }
    
    /**
     * Log data export
     */
    public static function export($module, $format) {
        self::log('EXPORT', $module, null, null, ['format' => $format]);
    }
    
    /**
     * Log data import
     */
    public static function import($module, $recordCount) {
        self::log('IMPORT', $module, null, null, ['records' => $recordCount]);
    }
    
    /**
     * Get audit logs with pagination
     */
    public static function getLogs($filters = []) {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['module'])) {
            $where[] = 'a.module = ?';
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = $filters['limit'] ?? PAGINATION_DEFAULT;
        $offset = $filters['offset'] ?? 0;
        
        $sql = "SELECT a.*, u.name as user_name, u.email as user_email 
                FROM audit_logs_enhanced a 
                LEFT JOIN users u ON a.user_id = u.id 
                WHERE $whereClause 
                ORDER BY a.created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $countSql = "SELECT COUNT(*) FROM audit_logs_enhanced WHERE $whereClause";
        
        return [
            'logs' => db_fetchAll($sql, $params),
            'total' => db_count($countSql, $params)
        ];
    }
}
