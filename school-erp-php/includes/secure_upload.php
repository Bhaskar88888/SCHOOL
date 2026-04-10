<?php
/**
 * Enhanced File Upload Handler with Content Validation
 * School ERP PHP v3.0 - Security Enhanced
 * 
 * Features:
 * - File type validation (MIME + extension)
 * - Content verification (re-save images to prevent malicious files)
 * - Size limits
 * - Safe filename generation
 * - Blocked extension list
 */

require_once __DIR__ . '/../config/env.php';

class SecureFileUpload {
    
    private static $allowedTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];
    
    private static $maxSize = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880; // 5MB
    
    private static $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
        'exe', 'bat', 'cmd', 'com', 'msi',
        'sh', 'bash', 'zsh',
        'js', 'jsx', 'ts', 'tsx',
        'asp', 'aspx', 'jsp', 'jspx',
        'cgi', 'pl', 'py', 'rb',
        'htaccess', 'htpasswd',
        'sql', 'dump',
    ];
    
    /**
     * Upload student photo/document
     */
    public static function uploadStudentFile($file, $studentId, $type = 'photo') {
        $uploadDir = __DIR__ . '/../uploads/students/';
        return self::upload($file, $uploadDir, 'student_' . $studentId, $type);
    }
    
    /**
     * Upload staff photo/document
     */
    public static function uploadStaffFile($file, $staffId, $type = 'photo') {
        $uploadDir = __DIR__ . '/../uploads/staff/';
        return self::upload($file, $uploadDir, 'staff_' . $staffId, $type);
    }
    
    /**
     * Upload book cover image
     */
    public static function uploadBookCover($file, $bookId) {
        $uploadDir = __DIR__ . '/../uploads/books/';
        return self::upload($file, $uploadDir, 'book_' . $bookId, 'cover');
    }
    
    /**
     * Generic secure upload
     */
    private static function upload($file, $uploadDir, $prefix, $type) {
        // Create directory if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            // Add .htaccess to prevent PHP execution
            self::createHtaccess($uploadDir);
        }
        
        // Validate file
        $error = self::validate($file);
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        // For images, re-save to prevent malicious content
        if (strpos($file['type'], 'image/') === 0) {
            $result = self::saveImage($file, $uploadDir, $prefix);
        } else {
            // For non-images, just move with safe filename
            $extension = self::getExtension($file['type']);
            $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'error' => 'Failed to save file'];
            }
            
            $result = [
                'success' => true,
                'filename' => $filename,
                'path' => 'uploads/' . basename($uploadDir) . '/' . $filename,
                'type' => $file['type'],
                'size' => $file['size'],
            ];
        }
        
        return $result;
    }
    
    /**
     * Validate uploaded file
     */
    private static function validate($file) {
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            ];
            return $errors[$file['error']] ?? 'File upload error';
        }
        
        // Check file size
        if ($file['size'] > self::$maxSize) {
            $maxMB = self::$maxSize / 1024 / 1024;
            return "File size exceeds {$maxMB}MB limit";
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!isset(self::$allowedTypes[$mimeType])) {
            return "File type not allowed. Allowed: " . implode(', ', array_keys(self::$allowedTypes));
        }
        
        // Check extension matches MIME type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = self::$allowedTypes[$mimeType];
        if (!in_array($extension, $allowedExtensions)) {
            return "File extension does not match content type";
        }
        
        // Check for blocked extensions
        if (in_array($extension, self::$blockedExtensions)) {
            return "File extension not allowed for security reasons";
        }
        
        // Check for malicious filenames
        if (preg_match('/[<>:"\'|?*]/', $file['name'])) {
            return "Filename contains invalid characters";
        }
        
        return null;
    }
    
    /**
     * Re-save image to prevent malicious content
     */
    private static function saveImage($file, $uploadDir, $prefix) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($file['tmp_name']);
                $extension = 'jpg';
                break;
            case 'image/png':
                $source = imagecreatefrompng($file['tmp_name']);
                $extension = 'png';
                break;
            case 'image/gif':
                $source = imagecreatefromgif($file['tmp_name']);
                $extension = 'gif';
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($file['tmp_name']);
                $extension = 'webp';
                break;
            default:
                return ['success' => false, 'error' => 'Unsupported image type'];
        }
        
        if (!$source) {
            return ['success' => false, 'error' => 'Invalid or corrupted image file'];
        }
        
        // Generate safe filename
        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Save image (this strips any malicious metadata/code)
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($source, $filepath, 90);
                break;
            case 'image/png':
                imagepng($source, $filepath, 9);
                break;
            case 'image/gif':
                imagegif($source, $filepath);
                break;
            case 'image/webp':
                imagewebp($source, $filepath, 90);
                break;
        }
        
        imagedestroy($source);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => 'uploads/' . basename($uploadDir) . '/' . $filename,
            'type' => $mimeType,
            'size' => filesize($filepath),
        ];
    }
    
    /**
     * Get file extension from MIME type
     */
    private static function getExtension($mimeType) {
        return self::$allowedTypes[$mimeType][0] ?? 'bin';
    }
    
    /**
     * Create .htaccess to prevent PHP execution
     */
    private static function createHtaccess($directory) {
        $htaccess = $directory . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Prevent PHP execution\n";
            $content .= "<FilesMatch \"\\.php\">\n";
            $content .= "    Require all denied\n";
            $content .= "</FilesMatch>\n";
            $content .= "# Disable PHP engine\n";
            $content .= "php_flag engine off\n";
            
            file_put_contents($htaccess, $content);
        }
    }
    
    /**
     * Delete uploaded file
     */
    public static function delete($filepath) {
        $fullPath = __DIR__ . '/../' . $filepath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
    
    /**
     * Get file info
     */
    public static function getInfo($filepath) {
        $fullPath = __DIR__ . '/../' . $filepath;
        if (!file_exists($fullPath)) {
            return null;
        }
        
        return [
            'filename' => basename($filepath),
            'size' => filesize($fullPath),
            'type' => mime_content_type($fullPath),
            'created' => filectime($fullPath),
            'modified' => filemtime($fullPath),
        ];
    }
}
