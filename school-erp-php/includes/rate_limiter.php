<?php
/**
 * Rate Limiting Middleware
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../config/env.php';

class RateLimiter {
    
    private static $storagePath;
    
    public static function init() {
        self::$storagePath = __DIR__ . '/../tmp/rate_limits';
        if (!is_dir(self::$storagePath)) {
            mkdir(self::$storagePath, 0755, true);
        }
    }
    
    /**
     * Get client identifier (IP + User Agent hash)
     */
    private static function getClientId() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return md5($ip . '_' . $ua);
    }
    
    /**
     * Check if client is rate limited
     */
    public static function isLimited($customLimit = null, $customWindow = null) {
        if (!RATE_LIMIT_ENABLED) {
            return false;
        }
        
        self::init();
        
        $clientId = self::getClientId();
        $isAuth = strpos($_SERVER['REQUEST_URI'] ?? '', '/auth/') !== false;
        
        $limit = $customLimit ?? ($isAuth ? RATE_LIMIT_AUTH_REQUESTS : RATE_LIMIT_REQUESTS);
        $window = $customWindow ?? ($isAuth ? RATE_LIMIT_AUTH_WINDOW : RATE_LIMIT_WINDOW);
        
        $file = self::$storagePath . '/' . $clientId . '.json';
        $now = time();
        
        // Read current request count
        $data = ['requests' => [], 'blocked_until' => 0];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true) ?? $data;
        }
        
        // Check if client is blocked
        if ($data['blocked_until'] > $now) {
            return true;
        }
        
        // Remove old requests outside the window
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Add current request
        $data['requests'][] = $now;
        
        // Check if limit exceeded
        if (count($data['requests']) > $limit) {
            $data['blocked_until'] = $now + $window;
            file_put_contents($file, json_encode($data));
            return true;
        }
        
        // Save updated data
        file_put_contents($file, json_encode($data));
        
        return false;
    }
    
    /**
     * Apply rate limiting
     */
    public static function check($customLimit = null, $customWindow = null) {
        if (self::isLimited($customLimit, $customWindow)) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . RATE_LIMIT_AUTH_WINDOW);
            die(json_encode([
                'error' => 'Too many requests. Please try again later.',
                'retry_after' => RATE_LIMIT_AUTH_WINDOW
            ]));
        }
    }
    
    /**
     * Clean up old rate limit files
     */
    public static function cleanup() {
        self::init();
        $now = time();
        $files = glob(self::$storagePath . '/*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if ($data && $data['blocked_until'] < $now && empty($data['requests'])) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get remaining requests for client
     */
    public static function getRemaining() {
        self::init();
        $clientId = self::getClientId();
        $file = self::$storagePath . '/' . $clientId . '.json';
        $now = time();
        
        if (!file_exists($file)) {
            return RATE_LIMIT_REQUESTS;
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true) ?? ['requests' => []];
        
        $recentRequests = array_filter($data['requests'], function($timestamp) use ($now) {
            return ($now - $timestamp) < RATE_LIMIT_WINDOW;
        });
        
        return max(0, RATE_LIMIT_REQUESTS - count($recentRequests));
    }
}
