<?php
/**
 * Excel Export Service (.xlsx) using PHPSpreadsheet
 * School ERP PHP v3.0
 */

class ExcelExport {
    
    /**
     * Export students to Excel
     */
    public static function students($filters = []) {
        $where = ['s.is_active = 1'];
        $params = [];
        
        if (!empty($filters['class_id'])) {
            $where[] = 's.class_id = ?';
            $params[] = $filters['class_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(s.name LIKE ? OR s.admission_no LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT s.admission_no, s.name, s.roll_number, s.dob, s.gender, 
                       s.parent_name, s.parent_phone, s.phone, s.email, s.address,
                       c.name as class_name
                FROM students s 
                LEFT JOIN classes c ON s.class_id = c.id 
                WHERE $whereClause 
                ORDER BY s.admission_no";
        
        $students = db_fetchAll($sql, $params);
        
        $headers = ['Admission No', 'Name', 'Roll No', 'DOB', 'Gender', 'Class', 'Parent Name', 'Parent Phone', 'Phone', 'Email', 'Address'];
        $data = [];
        
        foreach ($students as $s) {
            $data[] = [
                $s['admission_no'],
                $s['name'],
                $s['roll_number'],
                $s['dob'],
                ucfirst($s['gender']),
                $s['class_name'],
                $s['parent_name'],
                $s['parent_phone'],
                $s['phone'],
                $s['email'],
                $s['address'],
            ];
        }
        
        return self::generateExcel($data, $headers, 'students_' . date('Y-m-d'));
    }
    
    /**
     * Export attendance to Excel
     */
    public static function attendance($filters = []) {
        $dateFrom = $filters['date_from'] ?? date('Y-m-01');
        $dateTo = $filters['date_to'] ?? date('Y-m-d');
        
        $where = ['a.date BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        
        if (!empty($filters['class_id'])) {
            $where[] = 'a.class_id = ?';
            $params[] = $filters['class_id'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT a.date, s.name as student_name, s.admission_no, c.name as class_name, 
                       a.status, a.subject, a.note 
                FROM attendance a 
                LEFT JOIN students s ON a.student_id = s.id 
                LEFT JOIN classes c ON a.class_id = c.id 
                WHERE $whereClause 
                ORDER BY a.date DESC, s.name";
        
        $attendance = db_fetchAll($sql, $params);
        
        $headers = ['Date', 'Student Name', 'Admission No', 'Class', 'Status', 'Subject', 'Note'];
        $data = [];
        
        foreach ($attendance as $a) {
            $data[] = [
                $a['date'],
                $a['student_name'],
                $a['admission_no'],
                $a['class_name'],
                ucfirst($a['status']),
                $a['subject'] ?? '-',
                $a['note'] ?? '-',
            ];
        }
        
        return self::generateExcel($data, $headers, 'attendance_' . $dateFrom . '_to_' . $dateTo);
    }
    
    /**
     * Export fees to Excel
     */
    public static function fees($filters = []) {
        $dateFrom = $filters['date_from'] ?? date('Y-m-01');
        $dateTo = $filters['date_to'] ?? date('Y-m-d');
        
        $sql = "SELECT f.receipt_no, f.fee_type, f.total_amount, f.amount_paid, f.discount, f.balance_amount, 
                       f.payment_method, f.paid_date, f.month, f.year,
                       s.name as student_name, s.admission_no, c.name as class_name
                FROM fees f 
                LEFT JOIN students s ON f.student_id = s.id 
                LEFT JOIN classes c ON s.class_id = c.id 
                WHERE f.paid_date BETWEEN ? AND ? 
                ORDER BY f.paid_date DESC";
        
        $fees = db_fetchAll($sql, [$dateFrom, $dateTo]);
        
        $headers = ['Receipt No', 'Fee Type', 'Total Amount', 'Paid Amount', 'Discount', 'Balance', 'Payment Method', 'Paid Date', 'Month', 'Year', 'Student Name', 'Admission No', 'Class'];
        $data = [];
        
        foreach ($fees as $f) {
            $data[] = [
                $f['receipt_no'],
                $f['fee_type'],
                $f['total_amount'],
                $f['amount_paid'],
                $f['discount'] ?? 0,
                $f['balance_amount'],
                ucfirst($f['payment_method']),
                $f['paid_date'],
                $f['month'],
                $f['year'],
                $f['student_name'],
                $f['admission_no'],
                $f['class_name'],
            ];
        }
        
        return self::generateExcel($data, $headers, 'fees_' . $dateFrom . '_to_' . $dateTo);
    }
    
    /**
     * Export exams/results to Excel
     */
    public static function exam_results($filters = []) {
        $sql = "SELECT e.name as exam_name, e.subject, e.exam_date, e.max_marks,
                       s.name as student_name, s.admission_no, c.name as class_name,
                       er.marks_obtained, er.total_marks, er.grade, er.status
                FROM exam_results er 
                LEFT JOIN exams e ON er.exam_id = e.id 
                LEFT JOIN students s ON er.student_id = s.id 
                LEFT JOIN classes c ON e.class_id = c.id 
                ORDER BY e.exam_date DESC, s.name";
        
        $results = db_fetchAll($sql);
        
        $headers = ['Exam Name', 'Subject', 'Exam Date', 'Max Marks', 'Student Name', 'Admission No', 'Class', 'Marks Obtained', 'Total Marks', 'Grade', 'Status'];
        $data = [];
        
        foreach ($results as $r) {
            $data[] = [
                $r['exam_name'],
                $r['subject'],
                $r['exam_date'],
                $r['max_marks'],
                $r['student_name'],
                $r['admission_no'],
                $r['class_name'],
                $r['marks_obtained'],
                $r['total_marks'] ?? $r['max_marks'],
                $r['grade'] ?? '-',
                ucfirst($r['status']),
            ];
        }
        
        return self::generateExcel($data, $headers, 'exam_results_' . date('Y-m-d'));
    }
    
    /**
     * Export staff to Excel
     */
    public static function staff() {
        $sql = "SELECT employee_id, name, email, role, department, designation, phone, is_active, created_at 
                FROM users 
                WHERE role IN ('teacher', 'admin', 'hr', 'accounts', 'librarian', 'canteen', 'conductor', 'driver') 
                ORDER BY employee_id";
        
        $staff = db_fetchAll($sql);
        
        require_once __DIR__ . '/../includes/auth.php';
        
        $headers = ['Employee ID', 'Name', 'Email', 'Role', 'Department', 'Designation', 'Phone', 'Active', 'Created At'];
        $data = [];
        
        foreach ($staff as $s) {
            $data[] = [
                $s['employee_id'],
                $s['name'],
                $s['email'],
                role_label($s['role']),
                $s['department'] ?? '-',
                $s['designation'] ?? '-',
                $s['phone'] ?? '-',
                $s['is_active'] ? 'Yes' : 'No',
                $s['created_at'],
            ];
        }
        
        return self::generateExcel($data, $headers, 'staff_' . date('Y-m-d'));
    }
    
    /**
     * Generate Excel file
     */
    private static function generateExcel($data, $headers, $filename) {
        // Check if PHPSpreadsheet is available
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return self::generateWithPHPSpreadsheet($data, $headers, $filename);
        }
        
        // Fallback to CSV if PHPSpreadsheet not installed
        return self::generateCSV($data, $headers, $filename);
    }
    
    /**
     * Generate with PHPSpreadsheet
     */
    private static function generateWithPHPSpreadsheet($data, $headers, $filename) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }
        
        // Set data
        $row = 2;
        foreach ($data as $record) {
            $col = 'A';
            foreach ($record as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', $col) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Fallback to CSV
     */
    private static function generateCSV($data, $headers, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        
        foreach ($data as $record) {
            fputcsv($output, $record);
        }
        
        fclose($output);
        exit;
    }
}
