<?php
/**
 * Import Templates Download API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$type = $_GET['type'] ?? '';

$templates = [
    'students' => [
        'filename' => 'students_import_template.csv',
        'headers' => ['Name', 'Admission No', 'Class', 'DOB', 'Gender', 'Parent Name', 'Parent Phone', 'Phone', 'Email', 'Address'],
        'sample' => ['John Doe', 'ADM202500001', 'Class 10 A', '2010-05-15', 'male', 'Jane Doe', '9876543210', '9876543211', 'john@email.com', '123 Main St'],
    ],
    'staff' => [
        'filename' => 'staff_import_template.csv',
        'headers' => ['Name', 'Email', 'Role', 'Employee ID', 'Department', 'Designation', 'Phone', 'Password'],
        'sample' => ['Jane Smith', 'jane@school.com', 'teacher', 'EMP20250001', 'Science', 'Teacher', '9876543212', 'password123'],
    ],
    'fees' => [
        'filename' => 'fees_import_template.csv',
        'headers' => ['Admission No', 'Fee Type', 'Total Amount', 'Amount Paid', 'Payment Method', 'Paid Date', 'Month', 'Year', 'Receipt No'],
        'sample' => ['ADM202500001', 'Tuition Fee', '50000', '50000', 'cash', '2025-04-01', 'April', '2025', 'REC202500001'],
    ],
];

if (empty($type) || !isset($templates[$type])) {
    json_response(['error' => 'Invalid type. Use: students, staff, fees'], 400);
}

$template = $templates[$type];
$csv = implode(',', $template['headers']) . "\n" . implode(',', $template['sample']) . "\n";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $template['filename'] . '"');
echo $csv;
exit;
