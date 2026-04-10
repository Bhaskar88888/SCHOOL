<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);

$filename = "students_export_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Headers
fputcsv($output, ['ID', 'Name', 'Roll Number', 'Class', 'Gender', 'DOB', 'Parent Name', 'Phone', 'Email', 'Address', 'Status']);

// Data
$classId = (int)($_GET['class_id'] ?? 0);
$where = $classId ? "WHERE s.class_id = $classId" : "";
$query = "SELECT s.id, s.name, s.roll_number, c.name as class_name, s.gender, s.dob, s.parent_name, s.phone, s.email, s.address, s.is_active 
          FROM students s 
          LEFT JOIN classes c ON s.class_id = c.id 
          $where ORDER BY s.id DESC";

$students = db_fetchAll($query);

foreach ($students as $row) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['roll_number'],
        $row['class_name'] ?: 'N/A',
        ucfirst($row['gender']),
        $row['dob'],
        $row['parent_name'],
        $row['phone'],
        $row['email'],
        $row['address'],
        $row['is_active'] ? 'Active' : 'Inactive'
    ]);
}

fclose($output);
exit;
