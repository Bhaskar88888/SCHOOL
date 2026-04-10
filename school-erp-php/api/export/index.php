<?php
/**
 * Export API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$module = $_GET['module'] ?? '';
$format = strtolower($_GET['format'] ?? 'csv');

if ($module === '') {
    json_response(['error' => 'Module is required'], 400);
}

if ($format === 'pdf') {
    require_once __DIR__ . '/pdf.php';
    exit;
}

if (!in_array($format, ['csv', 'excel'], true)) {
    json_response(['error' => 'Invalid export format'], 400);
}

switch ($module) {
    case 'students':
        export_students_csv();
        break;
    case 'attendance':
        export_attendance_csv();
        break;
    case 'fees':
        export_fees_csv();
        break;
    case 'exams':
        export_exams_csv();
        break;
    case 'library':
        export_library_csv();
        break;
    case 'staff':
        export_staff_csv();
        break;
    default:
        json_response(['error' => 'Invalid export module'], 400);
}

function export_students_csv()
{
    $parentPhoneExpr = db_column_exists('students', 'parent_phone') ? 's.parent_phone' : 's.phone';
    $rows = db_fetchAll(
        "SELECT s.admission_no, s.name, s.roll_number, s.dob, s.gender, s.parent_name,
                $parentPhoneExpr AS parent_phone, s.phone, s.email, s.address, c.name AS class_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE " . (db_column_exists('students', 'is_active') ? 's.is_active = 1' : '1=1') . "
         ORDER BY s.admission_no"
    );

    $headers = ['Admission No', 'Name', 'Roll No', 'DOB', 'Gender', 'Class', 'Parent Name', 'Parent Phone', 'Phone', 'Email', 'Address'];
    $mapped = array_map(function ($row) {
        return [
            'Admission No' => $row['admission_no'],
            'Name' => $row['name'],
            'Roll No' => $row['roll_number'],
            'DOB' => $row['dob'],
            'Gender' => $row['gender'],
            'Class' => $row['class_name'],
            'Parent Name' => $row['parent_name'],
            'Parent Phone' => $row['parent_phone'],
            'Phone' => $row['phone'],
            'Email' => $row['email'],
            'Address' => $row['address'],
        ];
    }, $rows);

    audit_log('EXPORT', 'students', 'CSV export: ' . count($mapped) . ' rows');
    send_csv_download(safe_download_filename('students', 'csv'), $headers, $mapped);
}

function export_attendance_csv()
{
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $subjectExpr = db_column_exists('attendance', 'subject') ? 'a.subject' : "'-'";

    $rows = db_fetchAll(
        "SELECT a.date, a.status, $subjectExpr AS subject, a.note,
                s.name AS student_name, s.admission_no, c.name AS class_name
         FROM attendance a
         LEFT JOIN students s ON a.student_id = s.id
         LEFT JOIN classes c ON a.class_id = c.id
         WHERE a.date BETWEEN ? AND ?
         ORDER BY a.date DESC, s.name ASC",
        [$dateFrom, $dateTo]
    );

    $headers = ['Date', 'Student Name', 'Admission No', 'Class', 'Status', 'Subject', 'Note'];
    $mapped = array_map(function ($row) {
        return [
            'Date' => $row['date'],
            'Student Name' => $row['student_name'],
            'Admission No' => $row['admission_no'],
            'Class' => $row['class_name'],
            'Status' => $row['status'],
            'Subject' => $row['subject'],
            'Note' => $row['note'],
        ];
    }, $rows);

    audit_log('EXPORT', 'attendance', 'CSV export: ' . count($mapped) . ' rows');
    send_csv_download(safe_download_filename('attendance', 'csv'), $headers, $mapped);
}

function export_fees_csv()
{
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $discountExpr = db_column_exists('fees', 'discount') ? 'f.discount' : '0';

    $rows = db_fetchAll(
        "SELECT f.receipt_no, f.fee_type, f.total_amount, f.amount_paid, $discountExpr AS discount,
                f.balance_amount, f.payment_method, f.paid_date, f.due_date, f.month, f.year,
                s.name AS student_name, s.admission_no, c.name AS class_name
         FROM fees f
         LEFT JOIN students s ON f.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE f.paid_date BETWEEN ? AND ?
         ORDER BY f.paid_date DESC",
        [$dateFrom, $dateTo]
    );

    $headers = ['Receipt No', 'Fee Type', 'Total Amount', 'Paid Amount', 'Discount', 'Balance', 'Payment Method', 'Paid Date', 'Due Date', 'Month', 'Year', 'Student Name', 'Admission No', 'Class'];
    $mapped = array_map(function ($row) {
        return [
            'Receipt No' => $row['receipt_no'],
            'Fee Type' => $row['fee_type'],
            'Total Amount' => $row['total_amount'],
            'Paid Amount' => $row['amount_paid'],
            'Discount' => $row['discount'],
            'Balance' => $row['balance_amount'],
            'Payment Method' => $row['payment_method'],
            'Paid Date' => $row['paid_date'],
            'Due Date' => $row['due_date'],
            'Month' => $row['month'],
            'Year' => $row['year'],
            'Student Name' => $row['student_name'],
            'Admission No' => $row['admission_no'],
            'Class' => $row['class_name'],
        ];
    }, $rows);

    audit_log('EXPORT', 'fees', 'CSV export: ' . count($mapped) . ' rows');
    send_csv_download(safe_download_filename('fees', 'csv'), $headers, $mapped);
}

function export_exams_csv()
{
    $totalMarksExpr = db_column_exists('exam_results', 'total_marks') ? 'er.total_marks' : 'e.max_marks';
    $rows = db_fetchAll(
        "SELECT e.name AS exam_name, e.subject, e.exam_date, e.max_marks, e.pass_marks,
                s.name AS student_name, s.admission_no, c.name AS class_name,
                er.marks_obtained, er.grade, er.status, $totalMarksExpr AS total_marks
         FROM exam_results er
         LEFT JOIN exams e ON er.exam_id = e.id
         LEFT JOIN students s ON er.student_id = s.id
         LEFT JOIN classes c ON e.class_id = c.id
         ORDER BY e.exam_date DESC, s.name ASC"
    );

    $headers = ['Exam Name', 'Subject', 'Exam Date', 'Max Marks', 'Pass Marks', 'Student Name', 'Admission No', 'Class', 'Marks Obtained', 'Total Marks', 'Grade', 'Status'];
    $mapped = array_map(function ($row) {
        return [
            'Exam Name' => $row['exam_name'],
            'Subject' => $row['subject'],
            'Exam Date' => $row['exam_date'],
            'Max Marks' => $row['max_marks'],
            'Pass Marks' => $row['pass_marks'],
            'Student Name' => $row['student_name'],
            'Admission No' => $row['admission_no'],
            'Class' => $row['class_name'],
            'Marks Obtained' => $row['marks_obtained'],
            'Total Marks' => $row['total_marks'],
            'Grade' => $row['grade'],
            'Status' => $row['status'],
        ];
    }, $rows);

    audit_log('EXPORT', 'exams', 'CSV export: ' . count($mapped) . ' rows');
    send_csv_download(safe_download_filename('exam_results', 'csv'), $headers, $mapped);
}

function export_library_csv()
{
    $rows = db_fetchAll(
        "SELECT b.title, b.author, b.isbn, b.category, b.total_copies, b.available_copies,
                li.issue_date, li.due_date, li.return_date, li.fine_amount, li.is_returned,
                s.name AS student_name, s.admission_no
         FROM library_issues li
         LEFT JOIN library_books b ON li.book_id = b.id
         LEFT JOIN students s ON li.student_id = s.id
         ORDER BY li.issue_date DESC"
    );

    $headers = ['Book Title', 'Author', 'ISBN', 'Category', 'Total Copies', 'Available Copies', 'Issue Date', 'Due Date', 'Return Date', 'Fine Amount', 'Returned', 'Student Name', 'Admission No'];
    $mapped = array_map(function ($row) {
        return [
            'Book Title' => $row['title'],
            'Author' => $row['author'],
            'ISBN' => $row['isbn'],
            'Category' => $row['category'],
            'Total Copies' => $row['total_copies'],
            'Available Copies' => $row['available_copies'],
            'Issue Date' => $row['issue_date'],
            'Due Date' => $row['due_date'],
            'Return Date' => $row['return_date'],
            'Fine Amount' => $row['fine_amount'],
            'Returned' => !empty($row['is_returned']) ? 'Yes' : 'No',
            'Student Name' => $row['student_name'],
            'Admission No' => $row['admission_no'],
        ];
    }, $rows);

    audit_log('EXPORT', 'library', 'CSV export: ' . count($mapped) . ' rows');
    send_csv_download(safe_download_filename('library_transactions', 'csv'), $headers, $mapped);
}

function export_staff_csv()
{
    $employeeExpr = db_column_exists('users', 'employee_id') ? 'u.employee_id' : 'NULL';
    $departmentExpr = db_column_exists('users', 'department') ? 'u.department' : "''";
    $designationExpr = db_column_exists('users', 'designation') ? 'u.designation' : "''";
    $phoneExpr = db_column_exists('users', 'phone') ? 'u.phone' : "''";
    $activeExpr = db_column_exists('users', 'is_active') ? 'u.is_active' : '1';

    $rows = db_fetchAll(
        "SELECT $employeeExpr AS employee_id, u.name, u.email, u.role,
                $departmentExpr AS department, $designationExpr AS designation,
                $phoneExpr AS phone, $activeExpr AS is_active, u.created_at
         FROM users u
         WHERE u.role NOT IN ('student', 'parent')
         ORDER BY u.name ASC"
    );

    $headers = ['Employee ID', 'Name', 'Email', 'Role', 'Department', 'Designation', 'Phone', 'Active', 'Created At'];
    $mapped = array_map(function ($row) {
        return [
            'Employee ID' => $row['employee_id'],
            'Name' => $row['name'],
            'Email' => $row['email'],
            'Role' => role_label($row['role']),
            'Department' => $row['department'],
            'Designation' => $row['designation'],
            'Phone' => $row['phone'],
            'Active' => !empty($row['is_active']) ? 'Yes' : 'No',
            'Created At' => $row['created_at'],
        ];
    }, $rows);

    audit_log('EXPORT', 'staff', 'CSV export: ' . count($mapped) . ' rows');
    send_csv_download(safe_download_filename('staff', 'csv'), $headers, $mapped);
}
