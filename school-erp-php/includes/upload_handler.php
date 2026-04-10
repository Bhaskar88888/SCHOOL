<?php
/**
 * File Upload Handler
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../config/env.php';

class FileUpload {
    
    private static $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    private static $maxSize = UPLOAD_MAX_SIZE; // 5MB
    
    /**
     * Upload student photo/document
     */
    public static function uploadStudentFile($file, $studentId, $type = 'photo') {
        $uploadDir = __DIR__ . '/../uploads/students/';
        return self::upload($file, $uploadDir, $studentId, $type);
    }
    
    /**
     * Upload staff photo/document
     */
    public static function uploadStaffFile($file, $staffId, $type = 'photo') {
        $uploadDir = __DIR__ . '/../uploads/staff/';
        return self::upload($file, $uploadDir, $staffId, $type);
    }
    
    /**
     * Upload book cover image
     */
    public static function uploadBookCover($file, $bookId) {
        $uploadDir = __DIR__ . '/../uploads/books/';
        return self::upload($file, $uploadDir, $bookId, 'cover');
    }
    
    /**
     * Generic upload
     */
    private static function upload($file, $uploadDir, $entityId, $type) {
        // Create directory if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file
        $error = self::validate($file);
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $type . '_' . $entityId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        
        // Return relative path
        $relativePath = 'uploads/' . basename($uploadDir) . '/' . $filename;
        
        return ['success' => true, 'path' => $relativePath, 'filename' => $filename];
    }
    
    /**
     * Validate uploaded file
     */
    private static function validate($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'File upload error';
        }
        
        if ($file['size'] > self::$maxSize) {
            return 'File size exceeds ' . (self::$maxSize / 1024 / 1024) . 'MB limit';
        }
        
        if (!in_array($file['type'], self::$allowedTypes)) {
            return 'File type not allowed. Allowed: JPG, PNG, GIF, PDF, DOC';
        }
        
        // Check for malicious extensions
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh'];
        
        if (in_array($extension, $blockedExtensions)) {
            return 'File extension not allowed for security reasons';
        }
        
        return null;
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
}

/**
 * API endpoint for file uploads
 */
if (basename($_SERVER['PHP_SELF']) === 'upload.php') {
    require_once __DIR__ . '/../includes/auth.php';
    require_auth();
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Method not allowed'], 405);
    }
    
    if (!isset($_FILES['file'])) {
        json_response(['error' => 'No file uploaded'], 400);
    }
    
    $file = $_FILES['file'];
    $entityType = $_POST['entity_type'] ?? '';
    $entityId = $_POST['entity_id'] ?? '';
    
    if (empty($entityType) || empty($entityId)) {
        json_response(['error' => 'Entity type and ID required'], 400);
    }
    
    $result = null;
    
    if ($entityType === 'student') {
        $result = FileUpload::uploadStudentFile($file, $entityId);
    } elseif ($entityType === 'staff') {
        $result = FileUpload::uploadStaffFile($file, $entityId);
    } elseif ($entityType === 'book') {
        $result = FileUpload::uploadBookCover($file, $entityId);
    } else {
        json_response(['error' => 'Invalid entity type'], 400);
    }
    
    if ($result['success']) {
        audit_log('FILE_UPLOAD', $entityType, $entityId, null, ['file' => $result['path']]);
    }
    
    json_response($result, $result['success'] ? 200 : 400);
}
