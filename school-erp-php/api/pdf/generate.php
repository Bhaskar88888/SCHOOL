<?php
/**
 * PDF Generation API
 * School ERP PHP v3.0
 * Generates PDFs for: Fee Receipts, Payslips, Report Cards, Transfer Certificates
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

require_auth();

$action = $_GET['action'] ?? '';

// Fee Receipt PDF
if ($action === 'fee_receipt') {
    $feeId = $_GET['id'] ?? null;
    
    if (!$feeId) {
        die('Fee ID is required');
    }
    
    $sql = "SELECT f.*, s.name as student_name, s.admission_no, s.dob, s.parent_name, 
                   c.name as class_name, u.name as collected_by_name
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            LEFT JOIN classes c ON s.class_id = c.id 
            LEFT JOIN users u ON f.collected_by = u.id 
            WHERE f.id = ?";
    
    $fee = db_fetch($sql, [$feeId]);
    
    if (!$fee) {
        die('Fee record not found');
    }
    
    generate_fee_receipt($fee);
    audit_log('EXPORT', 'pdf', "Generated fee receipt for fee ID: $feeId");
}

// Payslip PDF
if ($action === 'payslip') {
    $payrollId = $_GET['id'] ?? null;
    
    if (!$payrollId) {
        die('Payroll ID is required');
    }
    
    $sql = "SELECT p.*, u.name as staff_name, u.employee_id, u.department, u.designation
            FROM payroll p 
            LEFT JOIN users u ON p.staff_id = u.id 
            WHERE p.id = ?";
    
    $payroll = db_fetch($sql, [$payrollId]);
    
    if (!$payroll) {
        die('Payroll record not found');
    }
    
    generate_payslip($payroll);
    audit_log('EXPORT', 'pdf', "Generated payslip for payroll ID: $payrollId");
}

// Report Card PDF
if ($action === 'report_card') {
    $studentId = $_GET['student_id'] ?? null;
    $examId = $_GET['exam_id'] ?? null;
    
    if (!$studentId) {
        die('Student ID is required');
    }
    
    // Get student info
    $student = db_fetch(
        "SELECT s.*, c.name as class_name 
         FROM students s 
         LEFT JOIN classes c ON s.class_id = c.id 
         WHERE s.id = ?",
        [$studentId]
    );
    
    if (!$student) {
        die('Student not found');
    }
    
    // Get exam results
    $where = "er.student_id = ?";
    $params = [$studentId];
    
    if ($examId) {
        $where .= " AND er.exam_id = ?";
        $params[] = $examId;
    }
    
    $sql = "SELECT er.*, e.name as exam_name, e.subject, e.exam_date, e.max_marks, e.pass_marks
            FROM exam_results er 
            LEFT JOIN exams e ON er.exam_id = e.id 
            WHERE $where 
            ORDER BY e.exam_date, e.subject";
    
    $results = db_fetchAll($sql, $params);
    
    generate_report_card($student, $results);
    audit_log('EXPORT', 'pdf', "Generated report card for student ID: $studentId");
}

// Transfer Certificate PDF
if ($action === 'transfer_certificate') {
    $studentId = $_GET['student_id'] ?? null;
    
    if (!$studentId) {
        die('Student ID is required');
    }
    
    $student = db_fetch(
        "SELECT s.*, c.name as class_name 
         FROM students s 
         LEFT JOIN classes c ON s.class_id = c.id 
         WHERE s.id = ?",
        [$studentId]
    );
    
    if (!$student) {
        die('Student not found');
    }
    
    generate_transfer_certificate($student);
    audit_log('EXPORT', 'pdf', "Generated transfer certificate for student ID: $studentId");
}

// ============================================
// PDF GENERATION FUNCTIONS
// ============================================

/**
 * Generate Fee Receipt HTML
 */
function generate_fee_receipt($fee) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="receipt_' . $fee['receipt_no'] . '.html"');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fee Receipt - ' . htmlspecialchars($fee['receipt_no']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0; color: #666; }
        .receipt-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .receipt-info div { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        table th { background-color: #f5f5f5; font-weight: bold; }
        .total-row { font-weight: bold; background-color: #f0f0f0; }
        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Receipt</button>
    </div>
    
    <div class="header">
        <h1>' . APP_NAME . '</h1>
        <p>Fee Receipt</p>
    </div>
    
    <div class="receipt-info">
        <div>
            <strong>Receipt No:</strong> ' . htmlspecialchars($fee['receipt_no']) . '<br>
            <strong>Date:</strong> ' . date('d M Y', strtotime($fee['paid_date'] ?? 'now')) . '
        </div>
        <div>
            <strong>Academic Year:</strong> ' . ($fee['academic_year'] ?? '-') . '<br>
            <strong>Term:</strong> ' . ($fee['month'] ?? '-') . ' ' . ($fee['year'] ?? '-') . '
        </div>
    </div>
    
    <table>
        <tr>
            <th>Student Name</th>
            <td>' . htmlspecialchars($fee['student_name']) . '</td>
        </tr>
        <tr>
            <th>Admission No</th>
            <td>' . htmlspecialchars($fee['admission_no']) . '</td>
        </tr>
        <tr>
            <th>Class</th>
            <td>' . htmlspecialchars($fee['class_name']) . '</td>
        </tr>
        <tr>
            <th>Parent Name</th>
            <td>' . htmlspecialchars($fee['parent_name']) . '</td>
        </tr>
    </table>
    
    <table>
        <tr>
            <th>Fee Type</th>
            <th>Total Amount</th>
            <th>Discount</th>
            <th>Amount Paid</th>
            <th>Balance</th>
        </tr>
        <tr>
            <td>' . htmlspecialchars($fee['fee_type']) . '</td>
            <td>₹' . number_format($fee['total_amount'], 2) . '</td>
            <td>₹' . number_format($fee['discount'] ?? 0, 2) . '</td>
            <td>₹' . number_format($fee['amount_paid'], 2) . '</td>
            <td>₹' . number_format($fee['balance_amount'], 2) . '</td>
        </tr>
    </table>
    
    <table>
        <tr>
            <th>Payment Method</th>
            <td>' . ucfirst($fee['payment_method']) . '</td>
        </tr>
        <tr>
            <th>Collected By</th>
            <td>' . htmlspecialchars($fee['collected_by_name'] ?? '-') . '</td>
        </tr>
        <tr>
            <th>Remarks</th>
            <td>' . htmlspecialchars($fee['remarks'] ?? '-') . '</td>
        </tr>
    </table>
    
    <div class="footer">
        <p>This is a computer-generated receipt. No signature required.</p>
        <p>Generated on ' . date('d M Y, h:i A') . '</p>
    </div>
</body>
</html>';
}

/**
 * Generate Payslip HTML
 */
function generate_payslip($payroll) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="payslip_' . $payroll['month'] . '_' . $payroll['year'] . '.html"');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip - ' . htmlspecialchars($payroll['staff_name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0; color: #666; }
        .employee-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        table th { background-color: #f5f5f5; font-weight: bold; }
        .section-header { background-color: #e0e0e0; font-weight: bold; }
        .total-row { font-weight: bold; background-color: #f0f0f0; }
        .net-pay { font-size: 18px; font-weight: bold; color: #333; }
        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Payslip</button>
    </div>
    
    <div class="header">
        <h1>' . APP_NAME . '</h1>
        <p>Payslip for ' . htmlspecialchars($payroll['month']) . ' ' . $payroll['year'] . '</p>
    </div>
    
    <div class="employee-info">
        <div>
            <strong>Employee Name:</strong> ' . htmlspecialchars($payroll['staff_name']) . '<br>
            <strong>Employee ID:</strong> ' . htmlspecialchars($payroll['employee_id'] ?? '-') . '<br>
            <strong>Department:</strong> ' . htmlspecialchars($payroll['department'] ?? '-') . '
        </div>
        <div>
            <strong>Designation:</strong> ' . htmlspecialchars($payroll['designation'] ?? '-') . '<br>
            <strong>Month:</strong> ' . htmlspecialchars($payroll['month']) . '<br>
            <strong>Year:</strong> ' . $payroll['year'] . '
        </div>
    </div>
    
    <table>
        <tr class="section-header">
            <th colspan="2">Earnings</th>
            <th colspan="2">Deductions</th>
        </tr>
        <tr>
            <td>Basic Salary</td>
            <td>₹' . number_format($payroll['basic_salary'], 2) . '</td>
            <td>PF Deduction</td>
            <td>₹' . number_format($payroll['deductions'], 2) . '</td>
        </tr>
        <tr>
            <td>Allowances</td>
            <td>₹' . number_format($payroll['allowances'], 2) . '</td>
            <td>Tax Deduction</td>
            <td>₹0.00</td>
        </tr>
        <tr class="total-row">
            <td>Total Earnings</td>
            <td>₹' . number_format($payroll['basic_salary'] + $payroll['allowances'], 2) . '</td>
            <td>Total Deductions</td>
            <td>₹' . number_format($payroll['deductions'], 2) . '</td>
        </tr>
    </table>
    
    <div style="margin: 20px 0; padding: 15px; background-color: #f0f0f0; border-left: 4px solid #333;">
        <strong>Net Salary:</strong> <span class="net-pay">₹' . number_format($payroll['net_salary'], 2) . '</span>
    </div>
    
    <div style="margin-top: 30px; display: flex; justify-content: space-between;">
        <div>
            <p>_____________________</p>
            <p>Employee Signature</p>
        </div>
        <div>
            <p>_____________________</p>
            <p>Authorized Signature</p>
        </div>
    </div>
    
    <div class="footer">
        <p>This is a computer-generated payslip.</p>
        <p>Generated on ' . date('d M Y, h:i A') . '</p>
    </div>
</body>
</html>';
}

/**
 * Generate Report Card HTML
 */
function generate_report_card($student, $results) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_card_' . $student['admission_no'] . '.html"');
    
    // Calculate totals
    $totalMarks = 0;
    $totalObtained = 0;
    $totalMaxMarks = 0;
    $passCount = 0;
    $failCount = 0;
    
    foreach ($results as $r) {
        $totalObtained += $r['marks_obtained'];
        $totalMaxMarks += ($r['total_marks'] ?? $r['max_marks']);
        if ($r['status'] === 'pass') $passCount++;
        if ($r['status'] === 'fail') $failCount++;
    }
    
    $overallPercentage = $totalMaxMarks > 0 ? ($totalObtained / $totalMaxMarks) * 100 : 0;
    
    $grade = 'F';
    if ($overallPercentage >= 90) $grade = 'A+';
    elseif ($overallPercentage >= 80) $grade = 'A';
    elseif ($overallPercentage >= 70) $grade = 'B+';
    elseif ($overallPercentage >= 60) $grade = 'B';
    elseif ($overallPercentage >= 50) $grade = 'C';
    elseif ($overallPercentage >= 40) $grade = 'D';
    elseif ($overallPercentage >= 33) $grade = 'E';
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report Card - ' . htmlspecialchars($student['name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0; color: #666; }
        .student-info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        table th { background-color: #f5f5f5; font-weight: bold; }
        .summary { margin: 20px 0; padding: 15px; background-color: #f0f0f0; }
        .grade { font-size: 24px; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Report Card</button>
    </div>
    
    <div class="header">
        <h1>' . APP_NAME . '</h1>
        <p>Student Report Card</p>
    </div>
    
    <div class="student-info">
        <strong>Student Name:</strong> ' . htmlspecialchars($student['name']) . '<br>
        <strong>Admission No:</strong> ' . htmlspecialchars($student['admission_no']) . '<br>
        <strong>Class:</strong> ' . htmlspecialchars($student['class_name']) . '<br>
        <strong>Date of Birth:</strong> ' . date('d M Y', strtotime($student['dob'])) . '<br>
        <strong>Parent Name:</strong> ' . htmlspecialchars($student['parent_name']) . '
    </div>
    
    <table>
        <tr>
            <th>Subject</th>
            <th>Exam</th>
            <th>Max Marks</th>
            <th>Marks Obtained</th>
            <th>Percentage</th>
            <th>Grade</th>
            <th>Status</th>
        </tr>';
    
    foreach ($results as $r) {
        $percentage = ($r['total_marks'] ?? $r['max_marks']) > 0 ? 
                     ($r['marks_obtained'] / ($r['total_marks'] ?? $r['max_marks'])) * 100 : 0;
        
        echo '<tr>
            <td>' . htmlspecialchars($r['subject']) . '</td>
            <td>' . htmlspecialchars($r['exam_name']) . '</td>
            <td>' . ($r['total_marks'] ?? $r['max_marks']) . '</td>
            <td>' . $r['marks_obtained'] . '</td>
            <td>' . number_format($percentage, 2) . '%</td>
            <td>' . ($r['grade'] ?? '-') . '</td>
            <td>' . ucfirst($r['status']) . '</td>
        </tr>';
    }
    
    echo '</table>
    
    <div class="summary">
        <h3>Summary</h3>
        <p><strong>Total Marks Obtained:</strong> ' . number_format($totalObtained, 2) . ' / ' . $totalMaxMarks . '</p>
        <p><strong>Overall Percentage:</strong> ' . number_format($overallPercentage, 2) . '%</p>
        <p><strong>Overall Grade:</strong> <span class="grade">' . $grade . '</span></p>
        <p><strong>Subjects Passed:</strong> ' . $passCount . '</p>
        <p><strong>Subjects Failed:</strong> ' . $failCount . '</p>
    </div>
    
    <div style="margin-top: 30px; display: flex; justify-content: space-between;">
        <div>
            <p>_____________________</p>
            <p>Class Teacher</p>
        </div>
        <div>
            <p>_____________________</p>
            <p>Principal</p>
        </div>
        <div>
            <p>_____________________</p>
            <p>Parent/Guardian</p>
        </div>
    </div>
    
    <div class="footer">
        <p>This is a computer-generated report card.</p>
        <p>Generated on ' . date('d M Y, h:i A') . '</p>
    </div>
</body>
</html>';
}

/**
 * Generate Transfer Certificate HTML
 */
function generate_transfer_certificate($student) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="transfer_certificate_' . $student['admission_no'] . '.html"');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Transfer Certificate - ' . htmlspecialchars($student['name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0; color: #666; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        table th { background-color: #f5f5f5; width: 30%; }
        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Certificate</button>
    </div>
    
    <div class="header">
        <h1>' . APP_NAME . '</h1>
        <p>Transfer Certificate</p>
    </div>
    
    <div class="title">TRANSFER CERTIFICATE</div>
    
    <table>
        <tr>
            <th>Student Name</th>
            <td>' . htmlspecialchars($student['name']) . '</td>
        </tr>
        <tr>
            <th>Admission Number</th>
            <td>' . htmlspecialchars($student['admission_no']) . '</td>
        </tr>
        <tr>
            <th>Date of Birth</th>
            <td>' . date('d M Y', strtotime($student['dob'])) . '</td>
        </tr>
        <tr>
            <th>Gender</th>
            <td>' . ucfirst($student['gender']) . '</td>
        </tr>
        <tr>
            <th>Class/Section</th>
            <td>' . htmlspecialchars($student['class_name']) . '</td>
        </tr>
        <tr>
            <th>Parent/Guardian Name</th>
            <td>' . htmlspecialchars($student['parent_name']) . '</td>
        </tr>
        <tr>
            <th>Parent Phone</th>
            <td>' . htmlspecialchars($student['parent_phone'] ?? $student['phone']) . '</td>
        </tr>
        <tr>
            <th>Admission Date</th>
            <td>' . date('d M Y', strtotime($student['admission_date'] ?? $student['created_at'])) . '</td>
        </tr>
        <tr>
            <th>Discharge Date</th>
            <td>' . ($student['discharge_date'] ? date('d M Y', strtotime($student['discharge_date'])) : '-') . '</td>
        </tr>
        <tr>
            <th>Discharge Reason</th>
            <td>' . htmlspecialchars($student['discharge_reason'] ?? '-') . '</td>
        </tr>
    </table>
    
    <div style="margin-top: 30px;">
        <p><strong>Certification:</strong></p>
        <p>This is to certify that the above student was bonafide student of this institution from ' . 
             date('d M Y', strtotime($student['admission_date'] ?? $student['created_at'])) . 
             ' to ' . ($student['discharge_date'] ? date('d M Y', strtotime($student['discharge_date'])) : 'date') . 
             '. He/She bears a good moral character and has not involved in any malpractice during the study period.</p>
    </div>
    
    <div style="margin-top: 40px; text-align: right;">
        <p>_____________________</p>
        <p>Principal/Head Master</p>
        <p>Date: ' . date('d M Y') . '</p>
    </div>
    
    <div class="footer">
        <p>This is a computer-generated certificate.</p>
        <p>Generated on ' . date('d M Y, h:i A') . '</p>
    </div>
</body>
</html>';
}
