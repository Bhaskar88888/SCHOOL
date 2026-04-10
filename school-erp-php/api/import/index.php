<?php
/**
 * Import API - CSV/Excel Import for Students, Staff, Fees
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

require_auth();
require_role(['admin', 'superadmin', 'hr']);

$module = $_GET['module'] ?? '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file'])) {
        json_response(['error' => 'No file uploaded'], 400);
    }
    
    $file = $_FILES['file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'File upload error'], 400);
    }
    
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        json_response(['error' => 'File size exceeds limit (5MB)'], 400);
    }
    
    $allowedTypes = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!in_array($file['type'], $allowedTypes) && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        json_response(['error' => 'Only CSV and Excel files allowed'], 400);
    }
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/../../uploads/imports';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = uniqid('import_') . '_' . basename($file['name']);
    $filepath = $uploadDir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        json_response(['error' => 'Failed to save file'], 500);
    }
    
    // Process import based on module
    if ($module === 'students') {
        $result = import_students($filepath);
    } elseif ($module === 'staff') {
        $result = import_staff($filepath);
    } elseif ($module === 'fees') {
        $result = import_fees($filepath);
    } else {
        json_response(['error' => 'Invalid module'], 400);
    }
    
    // Clean up uploaded file
    @unlink($filepath);
    
    audit_log('IMPORT', $module, "Imported " . $result['imported'] . " records");
    
    json_response($result);
}

/**
 * Import students from CSV
 */
function import_students($filepath) {
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        return ['error' => 'Failed to open file'];
    }
    
    $headers = fgetcsv($handle);
    $imported = 0;
    $errors = [];
    $rowNum = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        
        if (count($row) < 3) {
            $errors[] = "Row $rowNum: Insufficient data";
            continue;
        }
        
        $data = array_combine($headers, $row);
        
        $name = trim($data['Name'] ?? $data['name'] ?? '');
        $admissionNo = trim($data['Admission No'] ?? $data['admission_no'] ?? '');
        $className = trim($data['Class'] ?? $data['class'] ?? '');
        $dob = trim($data['DOB'] ?? $data['dob'] ?? '');
        $gender = trim($data['Gender'] ?? $data['gender'] ?? 'male');
        $parentName = trim($data['Parent Name'] ?? $data['parent_name'] ?? '');
        $parentPhone = trim($data['Parent Phone'] ?? $data['parent_phone'] ?? '');
        $phone = trim($data['Phone'] ?? $data['phone'] ?? '');
        $email = trim($data['Email'] ?? $data['email'] ?? '');
        $address = trim($data['Address'] ?? $data['address'] ?? '');
        
        if (empty($name) || empty($className)) {
            $errors[] = "Row $rowNum: Name and Class are required";
            continue;
        }
        
        // Get class ID
        $class = db_fetch("SELECT id FROM classes WHERE name = ?", [$className]);
        if (!$class) {
            $errors[] = "Row $rowNum: Class '$className' not found";
            continue;
        }
        
        // Generate admission number if not provided
        if (empty($admissionNo)) {
            $admissionNo = 'ADM' . date('Y') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }
        
        // Check if admission number already exists
        $existing = db_fetch("SELECT id FROM students WHERE admission_no = ?", [$admissionNo]);
        if ($existing) {
            $errors[] = "Row $rowNum: Admission number '$admissionNo' already exists";
            continue;
        }
        
        // Insert student
        try {
            $sql = "INSERT INTO students (admission_no, name, class_id, dob, gender, parent_name, parent_phone, phone, email, address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            db_query($sql, [
                $admissionNo,
                $name,
                $class['id'],
                !empty($dob) ? $dob : null,
                strtolower($gender),
                $parentName,
                $parentPhone,
                $phone,
                $email,
                $address,
            ]);
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row $rowNum: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    return [
        'message' => "Import completed. $imported students imported.",
        'imported' => $imported,
        'errors' => $errors,
        'total_rows' => $rowNum - 1,
    ];
}

/**
 * Import staff from CSV
 */
function import_staff($filepath) {
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        return ['error' => 'Failed to open file'];
    }
    
    $headers = fgetcsv($handle);
    $imported = 0;
    $errors = [];
    $rowNum = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        
        if (count($row) < 3) {
            $errors[] = "Row $rowNum: Insufficient data";
            continue;
        }
        
        $data = array_combine($headers, $row);
        
        $name = trim($data['Name'] ?? $data['name'] ?? '');
        $email = trim($data['Email'] ?? $data['email'] ?? '');
        $role = trim($data['Role'] ?? $data['role'] ?? 'teacher');
        $employeeId = trim($data['Employee ID'] ?? $data['employee_id'] ?? '');
        $department = trim($data['Department'] ?? $data['department'] ?? '');
        $designation = trim($data['Designation'] ?? $data['designation'] ?? '');
        $phone = trim($data['Phone'] ?? $data['phone'] ?? '');
        $password = trim($data['Password'] ?? $data['password'] ?? 'password123');
        
        if (empty($name) || empty($email)) {
            $errors[] = "Row $rowNum: Name and Email are required";
            continue;
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row $rowNum: Invalid email format";
            continue;
        }
        
        // Check if email already exists
        $existing = db_fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = "Row $rowNum: Email '$email' already exists";
            continue;
        }
        
        // Generate employee ID if not provided
        if (empty($employeeId)) {
            $employeeId = 'EMP' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert staff
        try {
            $sql = "INSERT INTO users (employee_id, name, email, password, role, department, designation, phone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            db_query($sql, [
                $employeeId,
                $name,
                $email,
                $hashedPassword,
                strtolower($role),
                $department,
                $designation,
                $phone,
            ]);
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row $rowNum: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    return [
        'message' => "Import completed. $imported staff imported.",
        'imported' => $imported,
        'errors' => $errors,
        'total_rows' => $rowNum - 1,
    ];
}

/**
 * Import fee payments from CSV
 */
function import_fees($filepath) {
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        return ['error' => 'Failed to open file'];
    }
    
    $headers = fgetcsv($handle);
    $imported = 0;
    $errors = [];
    $rowNum = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        
        if (count($row) < 3) {
            $errors[] = "Row $rowNum: Insufficient data";
            continue;
        }
        
        $data = array_combine($headers, $row);
        
        $admissionNo = trim($data['Admission No'] ?? $data['admission_no'] ?? '');
        $feeType = trim($data['Fee Type'] ?? $data['fee_type'] ?? 'Tuition Fee');
        $totalAmount = trim($data['Total Amount'] ?? $data['total_amount'] ?? '');
        $amountPaid = trim($data['Amount Paid'] ?? $data['amount_paid'] ?? 0);
        $paymentMethod = trim($data['Payment Method'] ?? $data['payment_method'] ?? 'cash');
        $paidDate = trim($data['Paid Date'] ?? $data['paid_date'] ?? date('Y-m-d'));
        $month = trim($data['Month'] ?? $data['month'] ?? '');
        $year = trim($data['Year'] ?? $data['year'] ?? date('Y'));
        $receiptNo = trim($data['Receipt No'] ?? $data['receipt_no'] ?? '');
        
        if (empty($admissionNo) || empty($totalAmount)) {
            $errors[] = "Row $rowNum: Admission No and Total Amount are required";
            continue;
        }
        
        // Get student
        $student = db_fetch("SELECT id FROM students WHERE admission_no = ?", [$admissionNo]);
        if (!$student) {
            $errors[] = "Row $rowNum: Student with admission no '$admissionNo' not found";
            continue;
        }
        
        // Generate receipt number if not provided
        if (empty($receiptNo)) {
            $receiptNo = 'REC' . date('Y') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }
        
        // Insert fee record
        try {
            $sql = "INSERT INTO fees (student_id, fee_type, total_amount, amount_paid, payment_method, paid_date, month, year, receipt_no) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            db_query($sql, [
                $student['id'],
                $feeType,
                $totalAmount,
                $amountPaid,
                strtolower($paymentMethod),
                !empty($paidDate) ? $paidDate : null,
                $month,
                $year,
                $receiptNo,
            ]);
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row $rowNum: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    return [
        'message' => "Import completed. $imported fee records imported.",
        'imported' => $imported,
        'errors' => $errors,
        'total_rows' => $rowNum - 1,
    ];
}
