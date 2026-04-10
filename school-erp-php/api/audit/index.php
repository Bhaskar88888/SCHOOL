<?php
/**
 * Audit Logs API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

require_auth();
require_role('superadmin');

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = pagination_limit($_GET['limit'] ?? null);
$filters = [
    'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : null,
    'module' => trim((string) ($_GET['module'] ?? '')),
    'action' => trim((string) ($_GET['action'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'limit' => $limit,
    'offset' => ($page - 1) * $limit,
];

$result = AuditLogger::getLogs($filters);
$logs = array_map('normalize_audit_row', $result['logs']);
$total = (int) ($result['total'] ?? 0);

json_response([
    'data' => $logs,
    'logs' => $logs,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'totalPages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
    ],
    'meta' => [
        'query' => [
            'page' => $page,
            'limit' => $limit,
            'search' => $filters['search'] ?: null,
            'module' => $filters['module'] ?: null,
            'action' => $filters['action'] ?: null,
            'date_from' => $filters['date_from'] ?: null,
            'date_to' => $filters['date_to'] ?: null,
        ],
    ],
]);

function normalize_audit_row($row)
{
    $description = $row['description'] ?? '';
    $newValue = decode_json_if_possible($row['new_value'] ?? null);
    $oldValue = decode_json_if_possible($row['old_value'] ?? null);

    return [
        'id' => $row['row_key'] ?? $row['id'],
        'timestamp' => $row['created_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'action' => $row['action'] ?? '',
        'module' => $row['module'] ?? '',
        'recordId' => $row['record_id'] ?? null,
        'record_id' => $row['record_id'] ?? null,
        'description' => is_string($description) ? $description : json_encode($description),
        'oldValue' => $oldValue,
        'newValue' => $newValue,
        'ipAddress' => $row['ip_address'] ?? null,
        'ip_address' => $row['ip_address'] ?? null,
        'userAgent' => $row['user_agent'] ?? null,
        'user_agent' => $row['user_agent'] ?? null,
        'user' => [
            'id' => $row['user_id'] ?? null,
            'name' => $row['user_name'] ?? 'System',
            'email' => $row['user_email'] ?? null,
            'role' => normalize_role_name($row['user_role'] ?? ''),
        ],
    ];
}

function decode_json_if_possible($value)
{
    if (!is_string($value) || $value === '') {
        return $value;
    }
    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}
