<?php
/**
 * Authentication Helpers
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/bootstrap.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // SESSION_COOKIE_SECURE: false on HTTP localhost, true on HTTPS production
    $cookieSecure = defined('SESSION_COOKIE_SECURE')
        ? filter_var(SESSION_COOKIE_SECURE, FILTER_VALIDATE_BOOLEAN)
        : false;
    session_set_cookie_params([
        'secure' => $cookieSecure,
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

function request_bearer_token()
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader === '') {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }

    if (!is_string($authHeader) || stripos($authHeader, 'Bearer ') !== 0) {
        return null;
    }

    $token = trim(substr($authHeader, 7));
    return $token !== '' ? $token : null;
}

function resolve_request_user_from_bearer()
{
    static $resolved = false;
    static $user = null;

    if ($resolved) {
        return $user;
    }

    $resolved = true;
    $GLOBALS['bearer_auth_error'] = null;

    if (!is_api_request()) {
        return null;
    }

    $token = request_bearer_token();
    if ($token === null) {
        return null;
    }

    require_once __DIR__ . '/jwt.php';

    try {
        $payload = JWT::decode($token);
    } catch (RuntimeException $e) {
        $GLOBALS['bearer_auth_error'] = $e->getMessage();
        return null;
    }

    $userId = (int) ($payload['user_id'] ?? 0);
    if ($userId <= 0) {
        $GLOBALS['bearer_auth_error'] = 'Invalid token payload';
        return null;
    }

    $dbUser = db_fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$userId]);
    if (!$dbUser) {
        $GLOBALS['bearer_auth_error'] = 'User not found or inactive';
        return null;
    }

    $user = [
        'id' => (int) $dbUser['id'],
        'name' => $dbUser['name'] ?? '',
        'email' => $dbUser['email'] ?? '',
        'role' => normalize_role_name($dbUser['role'] ?? ($payload['role'] ?? '')),
        'avatar' => $dbUser['avatar'] ?? null,
        'phone' => $dbUser['phone'] ?? null,
        'employee_id' => $dbUser['employee_id'] ?? null,
        'raw' => $dbUser,
        'jwt_payload' => $payload,
    ];

    return $user;
}

function request_uses_bearer_auth()
{
    return resolve_request_user_from_bearer() !== null;
}

function bearer_auth_error_message()
{
    resolve_request_user_from_bearer();
    return $GLOBALS['bearer_auth_error'] ?? null;
}

function is_logged_in()
{
    return (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']))
        || resolve_request_user_from_bearer() !== null;
}

function require_auth()
{
    if (!is_logged_in()) {
        if (is_api_request()) {
            http_response_code(401);
            die(json_encode(['error' => bearer_auth_error_message() ?: 'Unauthorized. Please log in.']));
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
    $userRole = get_current_role();
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
    if (!empty($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }

    $user = resolve_request_user_from_bearer();
    return $user['id'] ?? null;
}

function get_current_role()
{
    if (!empty($_SESSION['user_role'])) {
        return normalize_role_name($_SESSION['user_role']);
    }

    $user = resolve_request_user_from_bearer();
    return $user['role'] ?? null;
}

function get_authenticated_user()
{
    if (!is_logged_in())
        return null;
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return [
            'id' => (int) $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => normalize_role_name($_SESSION['user_role']),
            'avatar' => $_SESSION['user_avatar'] ?? null,
            'phone' => $_SESSION['user_phone'] ?? null,
        ];
    }

    $user = resolve_request_user_from_bearer();
    if ($user) {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'avatar' => $user['avatar'],
            'phone' => $user['phone'] ?? null,
        ];
    }

    return [
        'id' => null,
        'name' => null,
        'email' => null,
        'role' => null,
        'avatar' => null,
    ];
}

function login_user($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'] ?? null;
    $_SESSION['user_phone'] = $user['phone'] ?? null;
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
    // Use htmlspecialchars only (strip_tags is redundant since htmlspecialchars encodes HTML chars)
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function normalize_text_input($value)
{
    return trim(str_replace("\0", '', (string) $value));
}

function normalize_role_name($role)
{
    $role = strtolower(trim((string) $role));
    $aliases = [
        'accountant' => 'accounts',
        'accounts' => 'accounts',
        'super admin' => 'superadmin',
        'super-admin' => 'superadmin',
        'superadmin' => 'superadmin',
        'admin' => 'admin',
        'driver' => 'driver',
        'conductor' => 'conductor',
        'teacher' => 'teacher',
        'student' => 'student',
        'parent' => 'parent',
        'staff' => 'staff',
        'hr' => 'hr',
        'canteen' => 'canteen',
        'librarian' => 'librarian',
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

function generate_student_id(PDO $pdo = null)
{
    $year = date('Y');
    $counterName = 'student_uid';
    $ownsTransaction = !db_in_transaction();

    try {
        ensure_counters_table();

        if ($ownsTransaction) {
            db_beginTransaction();
        }

        $counter = db_fetch(
            "SELECT sequence FROM counters WHERE name = ? AND year = ? FOR UPDATE",
            [$counterName, $year]
        );

        $existingSequence = 0;
        if (db_column_exists('students', 'student_uid')) {
            $existingStudent = db_fetch(
                "SELECT student_uid FROM students WHERE student_uid LIKE ? ORDER BY student_uid DESC, id DESC LIMIT 1",
                ["STU-$year-%"]
            );
            if (!empty($existingStudent['student_uid']) && preg_match('/STU-\d{4}-(\d+)$/', $existingStudent['student_uid'], $matches)) {
                $existingSequence = (int) $matches[1];
            }
        }

        if ($counter) {
            $nextSequence = max((int) $counter['sequence'], $existingSequence) + 1;
            db_query(
                "UPDATE counters SET sequence = ? WHERE name = ? AND year = ?",
                [$nextSequence, $counterName, $year]
            );
        } else {
            $nextSequence = $existingSequence + 1;
            db_query(
                "INSERT INTO counters (name, year, sequence) VALUES (?, ?, ?)",
                [$counterName, $year, $nextSequence]
            );
        }

        if ($ownsTransaction) {
            db_commit();
        }

        return sprintf('STU-%s-%04d', $year, $nextSequence);
    } catch (Throwable $e) {
        if ($ownsTransaction) {
            db_rollback();
        }

        error_log('Student ID generation failed: ' . $e->getMessage());
        throw $e;
    }
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
function find_user_by_login_identifier($identifier, $columns = ['*'], $onlyActive = false)
{
    if (!db_table_exists('users')) {
        return null;
    }

    $identifier = trim((string) $identifier);
    if ($identifier === '') {
        return null;
    }

    $hasUsername = db_column_exists('users', 'username');
    $select = '*';

    if (is_array($columns) && $columns !== ['*']) {
        $selected = [];
        foreach ($columns as $column) {
            if (
                $column === '*'
                || db_column_exists('users', $column)
                || in_array($column, ['id', 'email'], true)
                || ($column === 'username' && $hasUsername)
            ) {
                $selected[] = $column;
            }
        }

        if (empty($selected)) {
            $selected = ['id'];
        }

        $select = implode(', ', array_unique($selected));
    }

    $sql = "SELECT $select FROM users WHERE ";
    $params = [];

    if ($hasUsername) {
        $sql .= "(email = ? OR username = ?)";
        $params[] = $identifier;
        $params[] = $identifier;
    } else {
        $sql .= "email = ?";
        $params[] = $identifier;
    }

    if ($onlyActive && db_column_exists('users', 'is_active')) {
        $sql .= " AND is_active = 1";
    }

    $sql .= " LIMIT 1";

    return db_fetch($sql, $params);
}

function is_account_locked($identifier)
{
    if (!defined('LOCKOUT_ENABLED') || !LOCKOUT_ENABLED)
        return false;

    if (!db_table_exists('users'))
        return false;
    if (!db_column_exists('users', 'locked_until'))
        return false;

    $user = find_user_by_login_identifier($identifier, ['id', 'locked_until']);

    if (!$user || !$user['locked_until']) {
        return false;
    }

    $lockUntil = strtotime($user['locked_until']);
    if (time() < $lockUntil) {
        return true;
    }

    // Lock expired - reset
    reset_lockout($identifier);
    return false;
}

/**
 * Record failed login attempt
 */
function record_failed_login($identifier)
{
    if (!defined('LOCKOUT_ENABLED') || !LOCKOUT_ENABLED)
        return;
    if (!db_column_exists('users', 'login_attempts'))
        return;
    if (!db_column_exists('users', 'locked_until'))
        return;

    $user = find_user_by_login_identifier($identifier, ['id', 'login_attempts']);
    if (!$user) {
        return;
    }

    $sql = "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?";
    db_query($sql, [$user['id']]);

    // Check if max attempts reached
    $user = db_fetch("SELECT login_attempts FROM users WHERE id = ?", [$user['id']]);
    if ($user && ($user['login_attempts'] >= LOCKOUT_MAX_ATTEMPTS)) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        db_query("UPDATE users SET locked_until = ? WHERE id = ?", [$lockUntil, $user['id']]);
    }
}

/**
 * Reset login attempts
 */
function reset_login_attempts($identifier)
{
    if (!db_column_exists('users', 'login_attempts'))
        return;
    if (!db_column_exists('users', 'locked_until'))
        return;

    $user = find_user_by_login_identifier($identifier, ['id']);
    if (!$user) {
        return;
    }

    db_query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?", [$user['id']]);
}

/**
 * Reset lockout
 */
function reset_lockout($identifier)
{
    if (!db_column_exists('users', 'login_attempts'))
        return;
    if (!db_column_exists('users', 'locked_until'))
        return;

    $user = find_user_by_login_identifier($identifier, ['id']);
    if (!$user) {
        return;
    }

    db_query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?", [$user['id']]);
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
 * Send password reset email
 */
function send_reset_email($email, $token)
{
    if (!function_exists('mail'))
        return false;
    // Use configured APP_URL instead of spoofable HTTP_HOST
    $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'http://localhost';
    $resetLink = $baseUrl . BASE_URL . "/reset_password.php?token=" . urlencode($token);

    $subject = "Password Reset Request";
    $message = "Please click the following link to reset your password:\n\n" . $resetLink . "\n\nIf you did not request this, please ignore this email.";
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@localhost';
    $headers = "From: " . $fromEmail . "\r\n";
    return @mail($email, $subject, $message, $headers);
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
    $_SESSION['user_phone'] = $user['phone'] ?? null;
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
