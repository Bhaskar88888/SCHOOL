<?php
/**
 * PDF Export API - Module-level PDF exports
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$module = $_GET['module'] ?? '';
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

// Helper to generate HTML table for PDF
function generate_pdf_html($title, $headers, $rows) {
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>$title</title>";
    $html .= "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { text-align: center; color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 10px; }
        @media print { body { margin: 0; } }
    </style></head><body>";
    $html .= "<h1>$title</h1>";
    $html .= "<table><thead><tr>";
    foreach ($headers as $h) {
        $html .= "<th>" . htmlspecialchars($h) . "</th>";
    }
    $html .= "</tr></thead><tbody>";
    foreach ($rows as $row) {
        $html .= "<tr>";
        foreach ($headers as $h) {
            $val = $row[$h] ?? '-';
            $html .= "<td>" . htmlspecialchars($val) . "</td>";
        }
        $html .= "</tr>";
    }
    $html .= "</tbody></table>";
    $html .= "<div class='footer'>Generated on " . date('d M Y, h:i A') . " - " . APP_NAME . "</div>";
    $html .= "</body></html>";
    
    return $html;
}

// Students PDF
if ($module === 'students') {
    $sql = "SELECT s.admission_no, s.name, s.roll_number, s.dob, s.gender, s.parent_name, s.parent_phone, s.phone, s.email, s.address, c.name as class_name 
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            WHERE s.is_active = 1 
            ORDER BY s.admission_no";
    $students = db_fetchAll($sql);
    
    $headers = ['Admission No', 'Name', 'Roll No', 'DOB', 'Gender', 'Class', 'Parent Name', 'Parent Phone', 'Phone', 'Email', 'Address'];
    $rows = [];
    foreach ($students as $s) {
        $rows[] = [
            'Admission No' => $s['admission_no'],
            'Name' => $s['name'],
            'Roll No' => $s['roll_number'],
            'DOB' => $s['dob'],
            'Gender' => ucfirst($s['gender']),
            'Class' => $s['class_name'],
            'Parent Name' => $s['parent_name'],
            'Parent Phone' => $s['parent_phone'],
            'Phone' => $s['phone'],
            'Email' => $s['email'],
            'Address' => $s['address'],
        ];
    }
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.html"');
    echo generate_pdf_html('Students List', $headers, $rows);
    exit;
}

// Attendance PDF
if ($module === 'attendance') {
    $dateFrom = $dateFrom ?? date('Y-m-01');
    $dateTo = $dateTo ?? date('Y-m-d');
    
    $sql = "SELECT a.date, s.name as student_name, s.admission_no, c.name as class_name, a.status, a.subject, a.note 
            FROM attendance a 
            LEFT JOIN students s ON a.student_id = s.id 
            LEFT JOIN classes c ON a.class_id = c.id 
            WHERE a.date BETWEEN ? AND ? 
            ORDER BY a.date DESC, s.name";
    $attendance = db_fetchAll($sql, [$dateFrom, $dateTo]);
    
    $headers = ['Date', 'Student Name', 'Admission No', 'Class', 'Status', 'Subject', 'Note'];
    $rows = [];
    foreach ($attendance as $a) {
        $rows[] = [
            'Date' => $a['date'],
            'Student Name' => $a['student_name'],
            'Admission No' => $a['admission_no'],
            'Class' => $a['class_name'],
            'Status' => ucfirst($a['status']),
            'Subject' => $a['subject'] ?? '-',
            'Note' => $a['note'] ?? '-',
        ];
    }
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="attendance_' . $dateFrom . '_to_' . $dateTo . '.html"');
    echo generate_pdf_html('Attendance Report', $headers, $rows);
    exit;
}

// Fees PDF
if ($module === 'fees') {
    $dateFrom = $dateFrom ?? date('Y-m-01');
    $dateTo = $dateTo ?? date('Y-m-d');
    
    $sql = "SELECT f.receipt_no, f.fee_type, f.total_amount, f.amount_paid, f.discount, f.balance_amount, f.payment_method, f.paid_date, s.name as student_name, s.admission_no, c.name as class_name 
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            LEFT JOIN classes c ON s.class_id = c.id 
            WHERE f.paid_date BETWEEN ? AND ? 
            ORDER BY f.paid_date DESC";
    $fees = db_fetchAll($sql, [$dateFrom, $dateTo]);
    
    $headers = ['Receipt No', 'Fee Type', 'Total', 'Paid', 'Discount', 'Balance', 'Method', 'Paid Date', 'Student', 'Class'];
    $rows = [];
    foreach ($fees as $f) {
        $rows[] = [
            'Receipt No' => $f['receipt_no'],
            'Fee Type' => $f['fee_type'],
            'Total' => $f['total_amount'],
            'Paid' => $f['amount_paid'],
            'Discount' => $f['discount'] ?? 0,
            'Balance' => $f['balance_amount'],
            'Method' => ucfirst($f['payment_method']),
            'Paid Date' => $f['paid_date'],
            'Student' => $f['student_name'],
            'Class' => $f['class_name'],
        ];
    }
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="fees_' . $dateFrom . '_to_' . $dateTo . '.html"');
    echo generate_pdf_html('Fee Collection Report', $headers, $rows);
    exit;
}

// Exams PDF
if ($module === 'exams') {
    $sql = "SELECT e.name as exam_name, e.subject, e.exam_date, e.max_marks, s.name as student_name, s.admission_no, c.name as class_name, er.marks_obtained, er.total_marks, er.grade, er.status 
            FROM exam_results er 
            LEFT JOIN exams e ON er.exam_id = e.id 
            LEFT JOIN students s ON er.student_id = s.id 
            LEFT JOIN classes c ON e.class_id = c.id 
            ORDER BY e.exam_date DESC, s.name";
    $exams = db_fetchAll($sql);
    
    $headers = ['Exam', 'Subject', 'Date', 'Max Marks', 'Student', 'Admission No', 'Class', 'Marks', 'Total', 'Grade', 'Status'];
    $rows = [];
    foreach ($exams as $e) {
        $rows[] = [
            'Exam' => $e['exam_name'],
            'Subject' => $e['subject'],
            'Date' => $e['exam_date'],
            'Max Marks' => $e['max_marks'],
            'Student' => $e['student_name'],
            'Admission No' => $e['admission_no'],
            'Class' => $e['class_name'],
            'Marks' => $e['marks_obtained'],
            'Total' => $e['total_marks'] ?? $e['max_marks'],
            'Grade' => $e['grade'] ?? '-',
            'Status' => ucfirst($e['status']),
        ];
    }
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="exams_' . date('Y-m-d') . '.html"');
    echo generate_pdf_html('Exam Results', $headers, $rows);
    exit;
}

// Library PDF
if ($module === 'library') {
    $sql = "SELECT b.title, b.author, b.isbn, b.category, b.total_copies, b.available_copies, li.issue_date, li.due_date, li.return_date, li.fine_amount, li.is_returned, s.name as student_name 
            FROM library_books b 
            LEFT JOIN library_issues li ON b.id = li.book_id 
            LEFT JOIN students s ON li.student_id = s.id 
            ORDER BY b.title";
    $library = db_fetchAll($sql);
    
    $headers = ['Title', 'Author', 'ISBN', 'Category', 'Total', 'Available', 'Issue Date', 'Due Date', 'Return Date', 'Fine', 'Returned', 'Student'];
    $rows = [];
    foreach ($library as $l) {
        $rows[] = [
            'Title' => $l['title'],
            'Author' => $l['author'],
            'ISBN' => $l['isbn'],
            'Category' => $l['category'],
            'Total' => $l['total_copies'],
            'Available' => $l['available_copies'],
            'Issue Date' => $l['issue_date'] ?? '-',
            'Due Date' => $l['due_date'] ?? '-',
            'Return Date' => $l['return_date'] ?? '-',
            'Fine' => $l['fine_amount'] ?? 0,
            'Returned' => $l['is_returned'] ? 'Yes' : 'No',
            'Student' => $l['student_name'] ?? '-',
        ];
    }
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="library_' . date('Y-m-d') . '.html"');
    echo generate_pdf_html('Library Catalog', $headers, $rows);
    exit;
}

// Staff PDF
if ($module === 'staff') {
    require_once __DIR__ . '/../../includes/auth.php';
    
    $sql = "SELECT employee_id, name, email, role, department, designation, phone, is_active, created_at 
            FROM users 
            WHERE role IN ('teacher', 'admin', 'hr', 'accounts', 'librarian', 'canteen', 'conductor', 'driver') AND is_active = 1 
            ORDER BY employee_id";
    $staff = db_fetchAll($sql);
    
    $headers = ['Employee ID', 'Name', 'Email', 'Role', 'Department', 'Designation', 'Phone', 'Active', 'Created At'];
    $rows = [];
    foreach ($staff as $s) {
        $rows[] = [
            'Employee ID' => $s['employee_id'],
            'Name' => $s['name'],
            'Email' => $s['email'],
            'Role' => role_label($s['role']),
            'Department' => $s['department'] ?? '-',
            'Designation' => $s['designation'] ?? '-',
            'Phone' => $s['phone'] ?? '-',
            'Active' => $s['is_active'] ? 'Yes' : 'No',
            'Created At' => $s['created_at'],
        ];
    }
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="staff_' . date('Y-m-d') . '.html"');
    echo generate_pdf_html('Staff Directory', $headers, $rows);
    exit;
}

json_response(['error' => 'Invalid module. Use: students, attendance, fees, exams, library, staff'], 400);
