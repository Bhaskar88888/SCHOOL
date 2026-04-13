<?php
/**
 * Authentication Helpers
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_logger.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // SESSION_COOKIE_SECURE: false on HTTP localhost, true on HTTPS production
    $cookieSecure = defined('SESSION_COOKIE_SECURE')
        ? filter_var(SESSION_COOKIE_SECURE, FILTER_VALIDATE_BOOLEAN)
        : false;
    session_set_cookie_params([
        'secure'   => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!defined('BASE_URL')) {
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $basePath = str_replace($docRoot, '', $appRoot);
    define('BASE_URL', $basePath);
}

function is_logged_in()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_auth()
{
    if (!is_logged_in()) {
        if (is_api_request()) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized. Please log in.']));
        }
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function require_role($roles)
{
    require_auth();
    if (!is_array($roles))
        $roles = [$roles];
    $userRole = $_SESSION['user_role'] ?? '';
    if (!role_matches($userRole, $roles)) {
        if (is_api_request()) {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden. Insufficient permissions.']));
        }
        header('Location: ' . BASE_URL . '/dashboard.php?error=forbidden');
        exit;
    }
}

function is_api_request()
{
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

function get_current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function get_current_role()
{
    return $_SESSION['user_role'] ?? null;
}

function get_authenticated_user()
{
    if (!is_logged_in())
        return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'avatar' => $_SESSION['user_avatar'] ?? null,
    ];
}

function login_user($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'] ?? null;
    $_SESSION['logged_in_at'] = time();
}

function logout_user()
{
    session_regenerate_id(true);
    session_unset();
    session_destroy();
}

function json_response($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_post_json()
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function sanitize($value)
{
    return htmlspecialchars(strip_tags(trim($value)));
}

function normalize_role_name($role)
{
    $role = strtolower(trim((string) $role));
    $aliases = [
        'accountant'  => 'accounts',
        'accounts'    => 'accounts',
        'super admin' => 'superadmin',
        'super-admin' => 'superadmin',
        'superadmin'  => 'superadmin',
        'admin'       => 'admin',
        'driver'      => 'driver',
        'conductor'   => 'conductor',
        'teacher'     => 'teacher',
        'student'     => 'student',
        'parent'      => 'parent',
        'staff'       => 'staff',
        'hr'          => 'hr',
        'canteen'     => 'canteen',
        'librarian'   => 'librarian',
    ];
    return $aliases[$role] ?? $role;
}

function storage_role_name($role)
{
    $role = normalize_role_name($role);
    $legacy = [
        'accounts' => 'accountant',
    ];
    return $legacy[$role] ?? $role;
}

function role_matches($currentRole, $allowedRoles)
{
    $currentRole = normalize_role_name($currentRole);
    // superadmin and admin both bypass all role restrictions
    if ($currentRole === 'superadmin' || $currentRole === 'admin') {
        return true;
    }
    foreach ((array) $allowedRoles as $allowedRole) {
        if ($currentRole === normalize_role_name($allowedRole)) {
            return true;
        }
    }
    return false;
}

function all_school_roles()
{
    return [
        'superadmin',
        'admin',
        'teacher',
        'student',
        'parent',
        'staff',
        'hr',
        'accounts',
        'canteen',
        'conductor',
        'driver',
        'librarian',
    ];
}

function role_label($role)
{
    $role = normalize_role_name($role);
    $labels = [
        'superadmin' => 'Super Admin',
        'admin' => 'Admin',
        'teacher' => 'Teacher',
        'student' => 'Student',
        'parent' => 'Parent',
        'staff' => 'Staff',
        'hr' => 'HR',
        'accounts' => 'Accounts',
        'librarian' => 'Librarian',
        'canteen' => 'Canteen',
        'conductor' => 'Conductor',
        'driver' => 'Driver',
    ];
    return $labels[$role] ?? ucfirst($role);
}

function current_academic_year(DateTime $date = null)
{
    $date = $date ?: new DateTime('now');
    $year = (int) $date->format('Y');
    $month = (int) $date->format('n');
    $startYear = $month >= 4 ? $year : ($year - 1);
    return $startYear . '-' . ($startYear + 1);
}

function current_academic_year_start(DateTime $date = null)
{
    $date = $date ?: new DateTime('now');
    $year = (int) $date->format('Y');
    $month = (int) $date->format('n');
    $startYear = $month >= 4 ? $year : ($year - 1);
    return sprintf('%04d-04-01', $startYear);
}

function db_table_exists($table)
{
    static $cache = [];
    $table = strtolower(trim((string) $table));
    if ($table === '') {
        return false;
    }
    if (!array_key_exists($table, $cache)) {
        $cache[$table] = (bool) db_fetch(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [DB_NAME, $table]
        );
    }
    return $cache[$table];
}

function db_column_exists($table, $column)
{
    static $cache = [];
    $key = strtolower(trim((string) $table)) . '.' . strtolower(trim((string) $column));
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = (bool) db_fetch(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [DB_NAME, $table, $column]
        );
    }
    return $cache[$key];
}

function db_existing_columns($table, array $columns)
{
    $existing = [];
    foreach ($columns as $column) {
        if (db_column_exists($table, $column)) {
            $existing[] = $column;
        }
    }
    return $existing;
}

function db_filter_data_for_table($table, array $data)
{
    $filtered = [];
    foreach ($data as $column => $value) {
        if (db_column_exists($table, $column)) {
            $filtered[$column] = $value;
        }
    }
    return $filtered;
}

function pagination_limit($requested = null)
{
    $default = defined('PAGINATION_DEFAULT') ? (int) PAGINATION_DEFAULT : 20;
    $max = defined('PAGINATION_MAX') ? (int) PAGINATION_MAX : 100;
    $limit = (int) ($requested ?: $default);
    if ($limit < 1) {
        $limit = $default;
    }
    return min($limit, $max);
}

function pagination_payload($rows, $page, $limit, $total, $extra = [])
{
    return array_merge([
        'data' => $rows,
        'pagination' => [
            'page' => (int) $page,
            'limit' => (int) $limit,
            'total' => (int) $total,
            'totalPages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
        ],
    ], $extra);
}

function safe_download_filename($prefix, $extension)
{
    $prefix = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $prefix);
    $extension = preg_replace('/[^A-Za-z0-9]+/', '', (string) $extension);
    return trim($prefix, '_') . '_' . date('Ymd_His') . '.' . $extension;
}

function csv_string(array $headers, array $rows)
{
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, $headers);
    foreach ($rows as $row) {
        $outputRow = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $value = (string) $value;
            if (preg_match('/^[=+\-@|]/', $value)) {
                $value = "'" . $value;
            }
            $outputRow[] = $value;
        }
        fputcsv($stream, $outputRow);
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return $csv;
}

function send_csv_download($filename, array $headers, array $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo csv_string($headers, $rows);
    exit;
}

function xml_escape($value)
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function audit_log($action, $module, $recordIdOrDescription = null, $oldValue = null, $newValue = null)
{
    $args = func_get_args();

    if (count($args) <= 3) {
        AuditLogger::log($action, $module, null, null, null, (string) ($recordIdOrDescription ?? ''));
        return;
    }

    AuditLogger::log($action, $module, $recordIdOrDescription, $oldValue, $newValue);
}

/**
 * Enhanced Security Functions (v3.0)
 */

/**
 * Check account lockout status
 */
function is_account_locked($email)
{
    if (!defined('LOCKOUT_ENABLED') || !LOCKOUT_ENABLED)
        return false;

    if (!db_table_exists('users'))
        return false;

    $sql = "SELECT locked_until FROM users WHERE email = ?";
    $user = db_fetch($sql, [$email]);

    if (!$user || !$user['locked_until']) {
        return false;
    }

    $lockUntil = strtotime($user['locked_until']);
    if (time() < $lockUntil) {
        return true;
    }

    // Lock expired - reset
    reset_lockout($email);
    return false;
}

/**
 * Record failed login attempt
 */
function record_failed_login($email)
{
    if (!defined('LOCKOUT_ENABLED') || !LOCKOUT_ENABLED)
        return;
    if (!db_column_exists('users', 'login_attempts'))
        return;

    $sql = "UPDATE users SET login_attempts = login_attempts + 1 WHERE email = ?";
    db_query($sql, [$email]);

    // Check if max attempts reached
    $user = db_fetch("SELECT login_attempts FROM users WHERE email = ?", [$email]);
    if ($user && ($user['login_attempts'] >= LOCKOUT_MAX_ATTEMPTS)) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        db_query("UPDATE users SET locked_until = ? WHERE email = ?", [$lockUntil, $email]);
    }
}

/**
 * Reset login attempts
 */
function reset_login_attempts($email)
{
    if (!db_column_exists('users', 'login_attempts'))
        return;
    db_query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?", [$email]);
}

/**
 * Reset lockout
 */
function reset_lockout($email)
{
    if (!db_column_exists('users', 'login_attempts'))
        return;
    db_query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?", [$email]);
}

/**
 * Generate password reset token
 */
function generate_reset_token($email)
{
    if (!db_column_exists('users', 'reset_token'))
        return null;

    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + (defined('RESET_TOKEN_EXPIRY') ? RESET_TOKEN_EXPIRY : 3600));

    db_query(
        "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?",
        [$token, $expiry, $email]
    );

    return $token;
}

/**
 * Verify password reset token
 */
function verify_reset_token($token)
{
    if (!db_column_exists('users', 'reset_token'))
        return null;

    $sql = "SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()";
    return db_fetch($sql, [$token]);
}

/**
 * Enhanced login with session security
 */
function login_user_enhanced($user)
{
    // Reset login attempts on successful login
    reset_login_attempts($user['email']);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['employee_id'] = $user['employee_id'] ?? null;
    $_SESSION['user_avatar'] = $user['avatar'] ?? null;
    $_SESSION['logged_in_at'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
}

/**
 * Enhanced logout with audit
 */
function logout_user_enhanced()
{
    // Log logout action
    if (is_logged_in()) {
        audit_log('LOGOUT', 'auth', 'User logged out');
    }

    session_unset();
    session_destroy();
}
