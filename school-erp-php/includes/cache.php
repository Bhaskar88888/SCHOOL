<?php
/**
 * Simple File-Based Cache
 * School ERP PHP v3.0
 * 
 * Provides 5-minute caching for expensive queries
 * Uses file system (no Redis required)
 */

class Cache {
    
    private static $cacheDir;
    private static $defaultTTL = 300; // 5 minutes
    
    /**
     * Initialize cache directory
     */
    private static function init() {
        if (!self::$cacheDir) {
            self::$cacheDir = __DIR__ . '/../tmp/cache';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
    }
    
    /**
     * Get cached value
     * Returns null if not found or expired
     */
    public static function get($key) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Check if expired
        if ($data && $data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        
        return $data['value'] ?? null;
    }
    
    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default 5 min)
     */
    public static function set($key, $value, $ttl = null) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'expires' => time() + ($ttl ?? self::$defaultTTL),
            'created' => time(),
        ];
        
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Delete cached value
     */
    public static function delete($key) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        
        if (file_exists($file)) {
            @unlink($file);
        }
    }
    
    /**
     * Clear all cache
     */
    public static function clear() {
        self::init();
        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Remember (get from cache or compute and cache)
     * 
     * @param string $key Cache key
     * @param callable $callback Function to compute value
     * @param int $ttl Time to live
     * @return mixed
     */
    public static function remember($key, $callback, $ttl = null) {
        $value = self::get($key);
        
        if ($value === null) {
            $value = $callback();
            self::set($key, $value, $ttl);
        }
        
        return $value;
    }
    
    /**
     * Get cache stats
     */
    public static function stats() {
        self::init();
        $files = glob(self::$cacheDir . '/*.cache');
        $totalSize = 0;
        $valid = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['expires'] >= time()) {
                $valid++;
            } else {
                $expired++;
            }
        }
        
        return [
            'total_files' => count($files),
            'valid' => $valid,
            'expired' => $expired,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
        ];
    }
}

/**
 * Helper functions
 */
function cache_get($key) {
    return Cache::get($key);
}

function cache_set($key, $value, $ttl = null) {
    Cache::set($key, $value, $ttl);
}

function cache_remember($key, $callback, $ttl = null) {
    return Cache::remember($key, $callback, $ttl);
}

function cache_clear() {
    Cache::clear();
}
