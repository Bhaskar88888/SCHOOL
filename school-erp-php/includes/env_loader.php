<?php
/**
 * Environment Variable Loader
 * School ERP PHP v3.0 - Secure Configuration
 * 
 * Loads .env.php file and sets constants
 * Falls back to defaults if file doesn't exist
 */

$envFile = __DIR__ . '/../.env.php';
$exampleFile = __DIR__ . '/../.env.example';

// If .env.php doesn't exist, create from example
if (!file_exists($envFile)) {
    if (file_exists($exampleFile)) {
        copy($exampleFile, $envFile);
        // Generate secure secrets
        $content = file_get_contents($envFile);
        $content = str_replace('CHANGE_THIS_TO_RANDOM_64_CHAR_STRING', bin2hex(random_bytes(32)), $content);
        file_put_contents($envFile, $content);

        // WARNING: User must configure credentials
        error_log("WARNING: .env.php was auto-generated from .env.example. Please configure your database credentials and security settings immediately!");
    }
}

// Load environment variables
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0)
            continue;

        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Skip if already defined
            if (defined($key))
                continue;

            // Define constant
            define($key, $value);
        }
    }
}

// Define fallback defaults for missing values
$defaults = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'school_erp',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4',
    'APP_NAME' => 'School ERP',
    'APP_VERSION' => '3.0',
    'APP_ENV' => 'production',
    'APP_URL' => 'https://school.kashliv.com',
    'APP_DEBUG' => 'false',
    'SESSION_SECRET' => bin2hex(random_bytes(32)),
    'CSRF_SECRET' => bin2hex(random_bytes(32)),
    'ENCRYPTION_KEY' => bin2hex(random_bytes(32)),
    'SESSION_LIFETIME' => 28800,
    'SESSION_GC_MAXLIFETIME' => 86400,
    'SESSION_COOKIE_SECURE' => 'false',
    'SESSION_COOKIE_HTTPONLY' => 'true',
    'SESSION_COOKIE_SAMESITE' => 'Strict',
    'RATE_LIMIT_ENABLED' => 'true',
    'RATE_LIMIT_REQUESTS' => 100,
    'RATE_LIMIT_WINDOW' => 3600,
    'RATE_LIMIT_AUTH_REQUESTS' => 10,
    'RATE_LIMIT_AUTH_WINDOW' => 3600,
    'LOCKOUT_ENABLED' => 'true',
    'LOCKOUT_MAX_ATTEMPTS' => 5,
    'LOCKOUT_DURATION' => 900,
    'RESET_TOKEN_EXPIRY' => 3600,
    'UPLOAD_MAX_SIZE' => 5242880,
    'PAGINATION_DEFAULT' => 20,
    'PAGINATION_MAX' => 100,
    'CURRENT_ACADEMIC_YEAR' => date('Y') . '-' . (date('Y') + 1),
    'APP_TIMEZONE' => 'Asia/Kolkata',
    'SMTP_HOST' => '',
    'SMTP_PORT' => 587,
    'SMS_ENABLED' => 'false',
    'TWILIO_SID' => '',
    'TWILIO_TOKEN' => '',
    'TWILIO_PHONE' => '',
    'GEMINI_API_KEY' => '',
    'GEMINI_MODEL' => 'gemini-1.5-flash',
    'PDF_GENERATOR' => 'html2pdf',
];

foreach ($defaults as $key => $value) {
    if (!defined($key)) {
        define($key, $value);
    }
}

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting based on environment
if (filter_var(APP_DEBUG, FILTER_VALIDATE_BOOLEAN)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
