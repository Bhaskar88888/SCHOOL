<?php
/**
 * CSRF Protection Helper
 * School ERP PHP v3.0
 */

class CSRFProtection
{
    private static $tokenKey = 'csrf_token';

    /**
     * Generate a CSRF token
     */
    public static function generateToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::$tokenKey])) {
            $_SESSION[self::$tokenKey] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$tokenKey];
    }

    /**
     * Get the current CSRF token
     */
    public static function getToken()
    {
        return self::generateToken();
    }

    /**
     * Verify CSRF token from request
     */
    public static function verifyToken($token = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::$tokenKey] ?? null;
        $requestToken = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!$sessionToken || !$requestToken || !hash_equals($sessionToken, $requestToken)) {
            if (is_api_request()) {
                http_response_code(403);
                header('Content-Type: application/json');
                die(json_encode(['error' => 'Invalid CSRF token']));
            }
            http_response_code(403);
            die('Invalid CSRF token. Please refresh the page and try again.');
        }

        // Rotate token after use
        $_SESSION[self::$tokenKey] = bin2hex(random_bytes(32));
        return true;
    }

    /**
     * Render a hidden CSRF token input field
     */
    public static function field()
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
