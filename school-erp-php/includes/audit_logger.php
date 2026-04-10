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
    public static function log($action, $module, $recordId = null, $oldValue = null, $newValue = null, $description = null) {
        try {
            $userId = get_current_user_id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            if (db_table_exists('audit_logs_enhanced')) {
                db_query(
                    "INSERT INTO audit_logs_enhanced
                    (user_id, action, module, record_id, old_value, new_value, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $userId,
                        $action,
                        $module,
                        $recordId,
                        $oldValue !== null ? json_encode($oldValue) : null,
                        $newValue !== null ? json_encode($newValue) : null,
                        $ipAddress,
                        substr($userAgent, 0, 255)
                    ]
                );
                return;
            }

            if (db_table_exists('audit_logs')) {
                db_query(
                    "INSERT INTO audit_logs (user_id, action, module, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [
                        $userId,
                        $action,
                        $module,
                        self::legacyDescription($recordId, $oldValue, $newValue, $description),
                        $ipAddress,
                    ]
                );
            }
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
        $sources = self::auditSources();
        if (empty($sources)) {
            return [
                'logs' => [],
                'total' => 0
            ];
        }

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'logs.user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['module'])) {
            $where[] = 'logs.module = ?';
            $params[] = $filters['module'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'logs.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'logs.created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'logs.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(logs.user_name LIKE ? OR logs.user_email LIKE ? OR logs.action LIKE ? OR logs.module LIKE ? OR logs.description LIKE ?)';
            $searchParam = '%' . $filters['search'] . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $whereClause = implode(' AND ', $where);
        $limit = pagination_limit($filters['limit'] ?? PAGINATION_DEFAULT);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $unionSql = implode(' UNION ALL ', array_map(function ($sql) {
            return '(' . $sql . ')';
        }, $sources));

        $sql = "SELECT logs.*
                FROM ($unionSql) logs
                WHERE $whereClause
                ORDER BY logs.created_at DESC
                LIMIT $limit OFFSET $offset";

        $countSql = "SELECT COUNT(*) FROM ($unionSql) logs WHERE $whereClause";

        return [
            'logs' => db_fetchAll($sql, $params),
            'total' => db_count($countSql, $params)
        ];
    }

    private static function legacyDescription($recordId = null, $oldValue = null, $newValue = null, $description = null) {
        if ($description !== null && $description !== '') {
            return (string) $description;
        }

        $parts = [];
        if ($recordId !== null && $recordId !== '') {
            $parts[] = 'Record ID: ' . $recordId;
        }
        if ($oldValue !== null) {
            $parts[] = 'Old: ' . json_encode($oldValue);
        }
        if ($newValue !== null) {
            $parts[] = 'New: ' . json_encode($newValue);
        }
        return implode(' | ', $parts);
    }

    private static function auditSources() {
        $sources = [];

        if (db_table_exists('audit_logs_enhanced')) {
            $sources[] = "SELECT
                CONCAT('enhanced-', a.id) AS row_key,
                a.id,
                a.user_id,
                a.action,
                a.module,
                a.record_id,
                a.old_value,
                a.new_value,
                COALESCE(a.new_value, a.old_value, '') AS description,
                a.ip_address,
                a.user_agent,
                a.created_at,
                u.name AS user_name,
                u.email AS user_email,
                u.role AS user_role
                FROM audit_logs_enhanced a
                LEFT JOIN users u ON a.user_id = u.id";
        }

        if (db_table_exists('audit_logs')) {
            $sources[] = "SELECT
                CONCAT('legacy-', a.id) AS row_key,
                a.id,
                a.user_id,
                a.action,
                a.module,
                NULL AS record_id,
                NULL AS old_value,
                NULL AS new_value,
                COALESCE(a.description, '') AS description,
                a.ip_address,
                NULL AS user_agent,
                a.created_at,
                u.name AS user_name,
                u.email AS user_email,
                u.role AS user_role
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id";
        }

        return $sources;
    }
}
