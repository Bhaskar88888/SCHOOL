<?php
/**
 * Export API - PDF, Excel, CSV for all modules
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

require_auth();

$module = $_GET['module'] ?? '';
$format = $_GET['format'] ?? 'csv'; // pdf, excel, csv

if (empty($module)) {
    json_response(['error' => 'Module is required'], 400);
}

// Export Students
if ($module === 'students') {
    $sql = "SELECT admission_no, name, roll_number, dob, gender, parent_name, parent_phone, phone, email, address 
            FROM students 
            WHERE is_active = 1 
            ORDER BY admission_no";
    
    $students = db_fetchAll($sql);
    
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['Admission No', 'Name', 'Roll No', 'DOB', 'Gender', 'Parent Name', 'Parent Phone', 'Phone', 'Email', 'Address'];
        $rows = [];
        
        foreach ($students as $s) {
            $rows[] = [
                'Admission No' => $s['admission_no'],
                'Name' => $s['name'],
                'Roll No' => $s['roll_number'],
                'DOB' => $s['dob'],
                'Gender' => $s['gender'],
                'Parent Name' => $s['parent_name'],
                'Parent Phone' => $s['parent_phone'],
                'Phone' => $s['phone'],
                'Email' => $s['email'],
                'Address' => $s['address'],
            ];
        }
        
        $filename = safe_download_filename('students', 'csv');
        send_csv_download($filename, $headers, $rows);
        audit_log('EXPORT', 'students', "Exported " . count($students) . " records as $format");
    }
}

// Export Attendance
if ($module === 'attendance') {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $classId = $_GET['class_id'] ?? null;
    
    $where = ['a.date BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];
    
    if ($classId) {
        $where[] = 'a.class_id = ?';
        $params[] = $classId;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT a.date, s.name as student_name, s.admission_no, c.name as class_name, a.status, a.subject, a.note 
            FROM attendance a 
            LEFT JOIN students s ON a.student_id = s.id 
            LEFT JOIN classes c ON a.class_id = c.id 
            WHERE $whereClause 
            ORDER BY a.date DESC, s.name";
    
    $attendance = db_fetchAll($sql, $params);
    
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['Date', 'Student Name', 'Admission No', 'Class', 'Status', 'Subject', 'Note'];
        $rows = [];
        
        foreach ($attendance as $a) {
            $rows[] = [
                'Date' => $a['date'],
                'Student Name' => $a['student_name'],
                'Admission No' => $a['admission_no'],
                'Class' => $a['class_name'],
                'Status' => $a['status'],
                'Subject' => $a['subject'] ?? '-',
                'Note' => $a['note'] ?? '-',
            ];
        }
        
        $filename = safe_download_filename('attendance_' . $dateFrom . '_to_' . $dateTo, 'csv');
        send_csv_download($filename, $headers, $rows);
        audit_log('EXPORT', 'attendance', "Exported " . count($attendance) . " records as $format");
    }
}

// Export Fees
if ($module === 'fees') {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    $sql = "SELECT f.receipt_no, f.fee_type, f.total_amount, f.amount_paid, f.discount, f.balance_amount, 
                   f.payment_method, f.paid_date, f.due_date, f.month, f.year,
                   s.name as student_name, s.admission_no, c.name as class_name
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            LEFT JOIN classes c ON s.class_id = c.id 
            WHERE f.paid_date BETWEEN ? AND ? 
            ORDER BY f.paid_date DESC";
    
    $fees = db_fetchAll($sql, [$dateFrom, $dateTo]);
    
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['Receipt No', 'Fee Type', 'Total Amount', 'Paid Amount', 'Discount', 'Balance', 'Payment Method', 'Paid Date', 'Due Date', 'Month', 'Year', 'Student Name', 'Admission No', 'Class'];
        $rows = [];
        
        foreach ($fees as $f) {
            $rows[] = [
                'Receipt No' => $f['receipt_no'],
                'Fee Type' => $f['fee_type'],
                'Total Amount' => $f['total_amount'],
                'Paid Amount' => $f['amount_paid'],
                'Discount' => $f['discount'] ?? 0,
                'Balance' => $f['balance_amount'],
                'Payment Method' => $f['payment_method'],
                'Paid Date' => $f['paid_date'],
                'Due Date' => $f['due_date'],
                'Month' => $f['month'],
                'Year' => $f['year'],
                'Student Name' => $f['student_name'],
                'Admission No' => $f['admission_no'],
                'Class' => $f['class_name'],
            ];
        }
        
        $filename = safe_download_filename('fees_' . $dateFrom . '_to_' . $dateTo, 'csv');
        send_csv_download($filename, $headers, $rows);
        audit_log('EXPORT', 'fees', "Exported " . count($fees) . " records as $format");
    }
}

// Export Exams
if ($module === 'exams') {
    $sql = "SELECT e.name as exam_name, e.subject, e.exam_date, e.max_marks, e.pass_marks,
                   s.name as student_name, s.admission_no, c.name as class_name,
                   er.marks_obtained, er.grade, er.status, er.total_marks, er.percentage
            FROM exam_results er 
            LEFT JOIN exams e ON er.exam_id = e.id 
            LEFT JOIN students s ON er.student_id = s.id 
            LEFT JOIN classes c ON e.class_id = c.id 
            ORDER BY e.exam_date DESC, s.name";
    
    $exams = db_fetchAll($sql);
    
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['Exam Name', 'Subject', 'Exam Date', 'Max Marks', 'Pass Marks', 'Student Name', 'Admission No', 'Class', 'Marks Obtained', 'Total Marks', 'Grade', 'Status', 'Percentage'];
        $rows = [];
        
        foreach ($exams as $e) {
            $rows[] = [
                'Exam Name' => $e['exam_name'],
                'Subject' => $e['subject'],
                'Exam Date' => $e['exam_date'],
                'Max Marks' => $e['max_marks'],
                'Pass Marks' => $e['pass_marks'],
                'Student Name' => $e['student_name'],
                'Admission No' => $e['admission_no'],
                'Class' => $e['class_name'],
                'Marks Obtained' => $e['marks_obtained'],
                'Total Marks' => $e['total_marks'] ?? $e['max_marks'],
                'Grade' => $e['grade'],
                'Status' => $e['status'],
                'Percentage' => $e['percentage'] ?? ($e['total_marks'] ? round(($e['marks_obtained'] / $e['total_marks']) * 100, 2) : '-'),
            ];
        }
        
        $filename = safe_download_filename('exam_results', 'csv');
        send_csv_download($filename, $headers, $rows);
        audit_log('EXPORT', 'exams', "Exported " . count($exams) . " records as $format");
    }
}

// Export Library
if ($module === 'library') {
    $sql = "SELECT b.title, b.author, b.isbn, b.category, b.total_copies, b.available_copies,
                   li.issue_date, li.due_date, li.return_date, li.fine_amount, li.is_returned,
                   s.name as student_name, s.admission_no
            FROM library_issues li 
            LEFT JOIN library_books b ON li.book_id = b.id 
            LEFT JOIN students s ON li.student_id = s.id 
            ORDER BY li.issue_date DESC";
    
    $library = db_fetchAll($sql);
    
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['Book Title', 'Author', 'ISBN', 'Category', 'Total Copies', 'Available Copies', 'Issue Date', 'Due Date', 'Return Date', 'Fine Amount', 'Returned', 'Student Name', 'Admission No'];
        $rows = [];
        
        foreach ($library as $l) {
            $rows[] = [
                'Book Title' => $l['title'],
                'Author' => $l['author'],
                'ISBN' => $l['isbn'],
                'Category' => $l['category'],
                'Total Copies' => $l['total_copies'],
                'Available Copies' => $l['available_copies'],
                'Issue Date' => $l['issue_date'],
                'Due Date' => $l['due_date'],
                'Return Date' => $l['return_date'] ?? '-',
                'Fine Amount' => $l['fine_amount'],
                'Returned' => $l['is_returned'] ? 'Yes' : 'No',
                'Student Name' => $l['student_name'],
                'Admission No' => $l['admission_no'],
            ];
        }
        
        $filename = safe_download_filename('library_transactions', 'csv');
        send_csv_download($filename, $headers, $rows);
        audit_log('EXPORT', 'library', "Exported " . count($library) . " records as $format");
    }
}

// Export Staff
if ($module === 'staff') {
    $sql = "SELECT employee_id, name, email, role, department, designation, phone, is_active, created_at 
            FROM users 
            WHERE role IN ('teacher', 'admin', 'hr', 'accounts', 'librarian', 'canteen', 'conductor', 'driver') 
            ORDER BY employee_id";
    
    $staff = db_fetchAll($sql);
    
    if ($format === 'csv' || $format === 'excel') {
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
        
        $filename = safe_download_filename('staff', 'csv');
        send_csv_download($filename, $headers, $rows);
        audit_log('EXPORT', 'staff', "Exported " . count($staff) . " records as $format");
    }
}

json_response(['error' => 'Invalid module or format'], 400);
