<?php
/**
 * Enhanced File Upload Handler
 * School ERP PHP v3.1
 * 
 * Features:
 * - Secure file validation (MIME + extension + content)
 * - Image re-saving to strip malicious code
 * - Multiple file type support
 * - File size limits per type
 * - Unique filename generation
 * - Audit logging
 * - Bulk upload support
 * - File metadata tracking
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class FileUpload
{

    // Allowed MIME types by category
    private static $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static $allowedDocTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    private static $allowedSpreadsheetTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    // Blocked extensions for security
    private static $blockedExtensions = [
        'php',
        'php3',
        'php4',
        'php5',
        'php7',
        'php8',
        'phtml',
        'exe',
        'bat',
        'sh',
        'cgi',
        'pl',
        'asp',
        'aspx',
        'jsp',
        'jspx',
        'war',
        'ear',
        'jar',
        'class',
        'htaccess',
        'htpasswd',
        'ini',
        'conf',
        'sql',
        'bak',
        'old',
        'tmp'
    ];

    // Max file sizes by type (in bytes)
    private static $maxSizes = [
        'photo' => 5242880,        // 5MB for photos
        'document' => 10485760,    // 10MB for documents
        'spreadsheet' => 10485760, // 10MB for spreadsheets
        'archive' => 20971520,     // 20MB for archives
        'default' => 5242880       // 5MB default
    ];

    /**
     * Upload student photo/document
     */
    public static function uploadStudentFile($file, $studentId, $type = 'photo')
    {
        $uploadDir = __DIR__ . '/../uploads/students/';
        return self::upload($file, $uploadDir, $studentId, $type);
    }

    /**
     * Upload staff photo/document
     */
    public static function uploadStaffFile($file, $staffId, $type = 'photo')
    {
        $uploadDir = __DIR__ . '/../uploads/staff/';
        return self::upload($file, $uploadDir, $staffId, $type);
    }

    /**
     * Upload book cover image
     */
    public static function uploadBookCover($file, $bookId)
    {
        $uploadDir = __DIR__ . '/../uploads/books/';
        return self::upload($file, $uploadDir, $bookId, 'cover');
    }

    /**
     * Upload notice attachment
     */
    public static function uploadNoticeFile($file, $noticeId)
    {
        $uploadDir = __DIR__ . '/../uploads/notices/';
        return self::upload($file, $uploadDir, $noticeId, 'attachment');
    }

    /**
     * Generic upload with entity type routing
     */
    public static function uploadFile($file, $entityType, $entityId, $type = 'document')
    {
        $uploadDirs = [
            'student' => __DIR__ . '/../uploads/students/',
            'staff' => __DIR__ . '/../uploads/staff/',
            'book' => __DIR__ . '/../uploads/books/',
            'notice' => __DIR__ . '/../uploads/notices/',
            'exam' => __DIR__ . '/../uploads/exams/',
            'fee' => __DIR__ . '/../uploads/fees/',
            'transport' => __DIR__ . '/../uploads/transport/',
            'hostel' => __DIR__ . '/../uploads/hostel/',
            'general' => __DIR__ . '/../uploads/general/'
        ];

        if (!isset($uploadDirs[$entityType])) {
            return ['success' => false, 'error' => 'Invalid entity type'];
        }

        return self::upload($file, $uploadDirs[$entityType], $entityId, $type);
    }

    /**
     * Core upload method with enhanced security
     */
    private static function upload($file, $uploadDir, $entityId, $type)
    {
        try {
            // Create directory if not exists with proper permissions
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    return ['success' => false, 'error' => 'Failed to create upload directory'];
                }
                // Add .htaccess to prevent PHP execution
                self::createHtaccess($uploadDir);
            }

            // Validate file
            $error = self::validate($file, $type);
            if ($error) {
                return ['success' => false, 'error' => $error];
            }

            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $type . '_' . $entityId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Detect actual MIME type (not user-supplied) for security
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actualMimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // For images, re-save to strip malicious code embedded in EXIF
            $actualImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($actualMimeType, $actualImageTypes)) {
                if (!self::reSaveImage($file['tmp_name'], $filepath)) {
                    return ['success' => false, 'error' => 'Failed to process image'];
                }
            } else {
                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    return ['success' => false, 'error' => 'Failed to save file'];
                }
            }

            // Get relative path for database storage
            $relativePath = 'uploads/' . basename($uploadDir) . '/' . $filename;

            // Return metadata (audit log is handled by caller to avoid duplicates)
            return [
                'success' => true,
                'path' => $relativePath,
                'filename' => $filename,
                'size' => $file['size'],
                'type' => $actualMimeType,
                'upload_time' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log('FileUpload error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'File upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Validate uploaded file with enhanced checks
     */
    private static function validate($file, $type)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            return $errors[$file['error']] ?? 'File upload error';
        }

        // Check file size
        $maxSize = self::getMaxSizeForType($type);
        if ($file['size'] > $maxSize) {
            return 'File size exceeds ' . ($maxSize / 1024 / 1024) . 'MB limit';
        }

        // Check file is not empty
        if ($file['size'] === 0) {
            return 'File is empty';
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = array_merge(self::$allowedImageTypes, self::$allowedDocTypes, self::$allowedSpreadsheetTypes);

        if (!in_array($mimeType, $allowedTypes)) {
            return 'File type not allowed. Detected: ' . $mimeType;
        }

        // Check for malicious extensions
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($extension, self::$blockedExtensions)) {
            return 'File extension not allowed for security reasons';
        }

        // Additional check: verify MIME matches extension
        $expectedTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf'
        ];

        if (isset($expectedTypes[$extension]) && $expectedTypes[$extension] !== $mimeType) {
            return 'File extension does not match content type';
        }

        return null;
    }

    /**
     * Re-save image to strip malicious code embedded in EXIF data
     */
    private static function reSaveImage($source, $destination)
    {
        // Get image info
        $imageInfo = getimagesize($source);
        if ($imageInfo === false) {
            return false;
        }

        $mimeType = $imageInfo['mime'];

        // Create image from source
        $image = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($source);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($source);
                } else {
                    return false;
                }
                break;
        }

        if ($image === null) {
            return false;
        }

        // Save image (strips EXIF data)
        $result = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $result = imagejpeg($image, $destination, 90);
                break;
            case 'image/png':
                $result = imagepng($image, $destination, 9);
                break;
            case 'image/gif':
                $result = imagegif($image, $destination);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    $result = imagewebp($image, $destination, 90);
                }
                break;
        }

        imagedestroy($image);
        return $result;
    }

    /**
     * Create .htaccess file to prevent PHP execution
     */
    private static function createHtaccess($dir)
    {
        $htaccess = $dir . '.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Prevent PHP execution\n";
            $content .= "<FilesMatch \"\\.(php|php3|php4|php5|phtml)\">\n";
            $content .= "    Order deny,allow\n";
            $content .= "    Deny from all\n";
            $content .= "</FilesMatch>\n";
            $content .= "\n# Prevent directory listing\n";
            $content .= "Options -Indexes\n";
            file_put_contents($htaccess, $content);
        }
    }

    /**
     * Get max file size for type
     */
    private static function getMaxSizeForType($type)
    {
        if (defined('UPLOAD_MAX_SIZE')) {
            return UPLOAD_MAX_SIZE;
        }

        foreach (self::$maxSizes as $key => $size) {
            if (strpos($type, $key) !== false) {
                return $size;
            }
        }

        return self::$maxSizes['default'];
    }

    /**
     * Delete uploaded file
     */
    public static function delete($filepath)
    {
        // Prevent directory traversal
        $uploadsDir = __DIR__ . '/../uploads/';

        // Ensure uploads directory exists
        if (!is_dir($uploadsDir)) {
            return false;
        }

        $basePath = realpath($uploadsDir);
        $fullPath = realpath(__DIR__ . '/../' . $filepath);

        if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
            error_log('FileUpload: Directory traversal attempt blocked: ' . $filepath);
            return false;
        }

        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                audit_log('FILE_DELETE', $filepath, 'upload', null, ['file' => $filepath]);
                return true;
            }
        }
        return false;
    }

    /**
     * Get file metadata
     */
    public static function getMetadata($filepath)
    {
        $fullPath = __DIR__ . '/../' . $filepath;

        if (!file_exists($fullPath)) {
            return null;
        }

        return [
            'path' => $filepath,
            'size' => filesize($fullPath),
            'type' => mime_content_type($fullPath),
            'modified' => filemtime($fullPath),
            'created' => filectime($fullPath)
        ];
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
        exit;
    }

    // Handle single file upload
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = $_POST['entity_id'] ?? '';
        $fileType = $_POST['file_type'] ?? 'document';

        if (empty($entityType) || empty($entityId)) {
            json_response(['error' => 'Entity type and ID required'], 400);
            exit;
        }

        $result = FileUpload::uploadFile($file, $entityType, $entityId, $fileType);

        if ($result['success']) {
            audit_log('FILE_UPLOAD', $entityType, $entityId, null, ['file' => $result['path']]);
        }

        json_response($result, $result['success'] ? 200 : 400);
        exit;
    }

    // Handle multiple file upload
    if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = $_POST['entity_id'] ?? '';
        $fileType = $_POST['file_type'] ?? 'document';

        if (empty($entityType) || empty($entityId)) {
            json_response(['error' => 'Entity type and ID required'], 400);
            exit;
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['files']['name'][$i],
                    'type' => $_FILES['files']['type'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'error' => $_FILES['files']['error'][$i],
                    'size' => $_FILES['files']['size'][$i]
                ];

                $result = FileUpload::uploadFile($file, $entityType, $entityId, $fileType);
                $results[] = $result;

                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }

        audit_log('BULK_FILE_UPLOAD', $entityType, $entityId, null, [
            'uploaded' => $successCount,
            'failed' => $errorCount
        ]);

        json_response([
            'success' => $successCount > 0,
            'uploaded' => $successCount,
            'failed' => $errorCount,
            'files' => $results
        ], $successCount > 0 ? 200 : 400);
        exit;
    }

    json_response(['error' => 'No file uploaded'], 400);
    exit;
}
