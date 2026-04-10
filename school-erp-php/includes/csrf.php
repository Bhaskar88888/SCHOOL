<?php
/**
 * CSRF Protection Middleware
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../config/env.php';

class CSRFProtection {
    
    private static $tokenName = 'csrf_token';
    
    /**
     * Generate CSRF token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION[self::$tokenName])) {
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[self::$tokenName];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = $_POST[self::$tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION[self::$tokenName] ?? '';
        
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            if (is_api_request()) {
                header('Content-Type: application/json');
                die(json_encode(['error' => 'Invalid or missing CSRF token']));
            }
            die('CSRF token validation failed');
        }
        
        // Regenerate token after use
        $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        return true;
    }
    
    /**
     * Get CSRF token field for forms
     */
    public static function tokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . $token . '">';
    }
    
    /**
     * Get CSRF token meta tag
     */
    public static function tokenMeta() {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . $token . '">';
    }
    
    /**
     * Skip CSRF for GET requests
     */
    public static function shouldSkip() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return in_array($method, ['GET', 'HEAD', 'OPTIONS']);
    }
}

/**
 * Auto-verify CSRF on non-GET requests
 */
function csrf_middleware() {
    if (!CSRFProtection::shouldSkip()) {
        CSRFProtection::verifyToken();
    }
}

/**
 * Check if request is API request
 */
function is_api_request_csrf() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}
