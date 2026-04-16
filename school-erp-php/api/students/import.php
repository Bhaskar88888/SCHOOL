<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();
require_role(['superadmin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Verify CSRF token sent with the form
    require_once __DIR__ . '/../../includes/csrf.php';
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
        json_response(['error' => 'Invalid CSRF token'], 403);
    }

    $file = $_FILES['csv_file']['tmp_name'];
    
    if (!is_uploaded_file($file)) {
        json_response(['error' => 'No file uploaded'], 400);
    }
    
    $handle = fopen($file, "r");
    $header = fgetcsv($handle); // Skip header row
    
    $imported = 0;
    $errors = 0;
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 4) continue; // Basic validation
        
        $name = sanitize($data[0]);
        $roll = sanitize($data[1]);
        $className = sanitize($data[2]);
        $gender = strtolower(sanitize($data[3]));
        $parent = sanitize($data[4] ?? '');
        $phone = sanitize($data[5] ?? '');
        $email = sanitize($data[6] ?? '');
        $address = sanitize($data[7] ?? '');
        
        // Case-insensitive class lookup to prevent duplicates (Bug #37)
        $class = db_fetch("SELECT id FROM classes WHERE LOWER(name) = LOWER(?)", [$className]);
        $classId = $class ? $class['id'] : 0;

        if (!$classId && !empty($className)) {
            $classId = db_insert("INSERT INTO classes (name) VALUES (?)", [$className]);
        }

        try {
            // Generate unique admission number for each import (Bug #7)
            $admissionNo = generate_auto_id('admission', 'ADM');
            db_insert("INSERT INTO students (name, roll_number, class_id, gender, parent_name, phone, email, address, admission_no, dob, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name), class_id=VALUES(class_id)",
                [$name, $roll, $classId, $gender, $parent, $phone, $email, $address, $admissionNo, !empty($data[8]) ? $data[8] : '2000-01-01']);
            $imported++;
        } catch (Exception $e) {
            $errors++;
        }
    }
    
    fclose($handle);
    json_response(['success' => true, 'imported' => $imported, 'errors' => $errors]);
}

json_response(['error' => 'Invalid request'], 400);
