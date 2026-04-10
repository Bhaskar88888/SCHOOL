<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
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
        
        // Find class ID by name
        $class = db_fetch("SELECT id FROM classes WHERE name = ?", [$className]);
        $classId = $class ? $class['id'] : 0;
        
        if (!$classId && !empty($className)) {
            // Optionally create class if not exists
            $classId = db_insert("INSERT INTO classes (name) VALUES (?)", [$className]);
        }
        
        try {
            db_insert("INSERT INTO students (name, roll_number, class_id, gender, parent_name, phone, email, address, is_active) VALUES (?,?,?,?,?,?,?,?, 1) ON DUPLICATE KEY UPDATE name=VALUES(name)", 
                [$name, $roll, $classId, $gender, $parent, $phone, $email, $address]);
            $imported++;
        } catch (Exception $e) {
            $errors++;
        }
    }
    
    fclose($handle);
    json_response(['success' => true, 'imported' => $imported, 'errors' => $errors]);
}

json_response(['error' => 'Invalid request'], 400);
