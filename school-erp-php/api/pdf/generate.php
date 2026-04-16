<?php
/**
 * PDF Generation API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf_helpers.php';

require_auth();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fee_receipt':
        generate_fee_receipt_pdf();
        break;
    case 'payslip':
        generate_payslip_pdf();
        break;
    case 'report_card':
        generate_report_card_pdf();
        break;
    case 'transfer_certificate':
        generate_transfer_certificate_pdf();
        break;
    default:
        json_response(['error' => 'Invalid PDF action'], 400);
}

function generate_fee_receipt_pdf()
{
    $feeId = (int) ($_GET['id'] ?? 0);
    if ($feeId <= 0) {
        json_response(['error' => 'Fee ID is required'], 400);
    }

    $discountExpr = db_column_exists('fees', 'discount') ? 'f.discount' : '0';
    $academicYearExpr = db_column_exists('fees', 'academic_year') ? 'f.academic_year' : "''";
    $parentPhoneExpr = db_column_exists('students', 'parent_phone') ? 's.parent_phone' : 's.phone';

    $fee = db_fetch(
        "SELECT f.*, $discountExpr AS discount, $academicYearExpr AS academic_year,
                s.name AS student_name, s.admission_no, s.parent_name, $parentPhoneExpr AS parent_phone,
                c.name AS class_name, u.name AS collected_by_name
         FROM fees f
         LEFT JOIN students s ON f.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN users u ON f.collected_by = u.id
         WHERE f.id = ?",
        [$feeId]
    );

    if (!$fee) {
        json_response(['error' => 'Fee record not found'], 404);
    }

    $userRole = normalize_role_name(get_current_role());
    $userId = get_current_user_id();
    if (!in_array($userRole, ['superadmin', 'admin', 'accounts', 'accountant'])) {
        if ($userRole === 'student') {
            $student = db_fetch("SELECT id FROM students WHERE user_id=?", [$userId]);
            if (!$student || $student['id'] != $fee['student_id']) json_response(['error' => 'Unauthorized'], 403);
        } elseif ($userRole === 'parent') {
            $student = db_fetch("SELECT id FROM students WHERE parent_phone=?", [(get_authenticated_user()['phone'] ?? '')]);
            // or just use arbitrary for now if parent_user_id isn't guaranteed, actually just reject if not authorized explicitly correctly
            // Let's just do a simpler check, if we don't have parent_user_id we check parent_phone matches user phone
            // Actually complain API uses parent_user_id. Let's assume it exists.
            if (!db_column_exists('students', 'parent_user_id')) {
                 if (get_authenticated_user()['phone'] != $fee['parent_phone']) json_response(['error' => 'Unauthorized'], 403);
            } else {
                 $student = db_fetch("SELECT parent_user_id FROM students WHERE id=?", [$fee['student_id']]);
                 if (!$student || $student['parent_user_id'] != $userId) json_response(['error' => 'Unauthorized'], 403);
            }
        } else {
             json_response(['error' => 'Unauthorized'], 403);
        }
    }

    $balance = isset($fee['balance_amount']) ? (float) $fee['balance_amount'] : ((float) $fee['total_amount'] - (float) $fee['amount_paid']);

    audit_log('EXPORT', 'pdf', 'Generated fee receipt for fee ID ' . $feeId);
    pdf_output_sections(
        safe_download_filename('receipt_' . ($fee['receipt_no'] ?? $feeId), 'pdf'),
        'Fee Receipt',
        [
            [
                'heading' => 'Receipt Details',
                'lines' => [
                    'Receipt No: ' . ($fee['receipt_no'] ?: '-'),
                    'Paid Date: ' . ($fee['paid_date'] ?: '-'),
                    'Academic Year: ' . ($fee['academic_year'] ?: current_academic_year()),
                    'Term: ' . trim(($fee['month'] ?? '-') . ' ' . ($fee['year'] ?? '')),
                ],
            ],
            [
                'heading' => 'Student Details',
                'lines' => [
                    'Student Name: ' . ($fee['student_name'] ?: '-'),
                    'Admission No: ' . ($fee['admission_no'] ?: '-'),
                    'Class: ' . ($fee['class_name'] ?: '-'),
                    'Parent Name: ' . ($fee['parent_name'] ?: '-'),
                    'Parent Phone: ' . ($fee['parent_phone'] ?: '-'),
                ],
            ],
            [
                'heading' => 'Payment Details',
                'lines' => [
                    'Fee Type: ' . ($fee['fee_type'] ?: '-'),
                    'Total Amount: ' . pdf_money($fee['total_amount']),
                    'Discount: ' . pdf_money($fee['discount'] ?? 0),
                    'Amount Paid: ' . pdf_money($fee['amount_paid']),
                    'Balance: ' . pdf_money($balance),
                    'Payment Method: ' . ucfirst($fee['payment_method'] ?? 'cash'),
                    'Collected By: ' . ($fee['collected_by_name'] ?: '-'),
                    'Remarks: ' . ($fee['remarks'] ?: '-'),
                ],
            ],
        ],
        'Generated by ' . APP_NAME
    );
}

function generate_payslip_pdf()
{
    $payrollId = (int) ($_GET['id'] ?? 0);
    if ($payrollId <= 0) {
        json_response(['error' => 'Payroll ID is required'], 400);
    }

    $departmentExpr = db_column_exists('users', 'department') ? 'u.department' : "''";
    $designationExpr = db_column_exists('users', 'designation') ? 'u.designation' : "''";
    $employeeExpr = db_column_exists('users', 'employee_id') ? 'u.employee_id' : 'NULL';

    $payroll = db_fetch(
        "SELECT p.*, $departmentExpr AS department, $designationExpr AS designation, $employeeExpr AS employee_id,
                u.name AS staff_name
         FROM payroll p
         LEFT JOIN users u ON p.staff_id = u.id
         WHERE p.id = ?",
        [$payrollId]
    );

    if (!$payroll) {
        json_response(['error' => 'Payroll record not found'], 404);
    }

    $netSalary = $payroll['net_salary'] ?? (($payroll['basic_salary'] ?? 0) + ($payroll['allowances'] ?? 0) - ($payroll['deductions'] ?? 0));

    audit_log('EXPORT', 'pdf', 'Generated payslip for payroll ID ' . $payrollId);
    pdf_output_sections(
        safe_download_filename('payslip_' . $payrollId, 'pdf'),
        'Payslip',
        [
            [
                'heading' => 'Staff Details',
                'lines' => [
                    'Staff Name: ' . ($payroll['staff_name'] ?: '-'),
                    'Employee ID: ' . ($payroll['employee_id'] ?: '-'),
                    'Department: ' . ($payroll['department'] ?: '-'),
                    'Designation: ' . ($payroll['designation'] ?: '-'),
                    'Month: ' . ($payroll['month'] ?: '-') . ' ' . ($payroll['year'] ?: ''),
                ],
            ],
            [
                'heading' => 'Salary Summary',
                'lines' => [
                    'Basic Salary: ' . pdf_money($payroll['basic_salary'] ?? 0),
                    'Allowances: ' . pdf_money($payroll['allowances'] ?? 0),
                    'Deductions: ' . pdf_money($payroll['deductions'] ?? 0),
                    'Net Salary: ' . pdf_money($netSalary),
                    'Status: ' . ucfirst($payroll['status'] ?? 'pending'),
                    'Paid Date: ' . ($payroll['paid_date'] ?: '-'),
                ],
            ],
        ],
        'Generated by ' . APP_NAME
    );
}

function generate_report_card_pdf()
{
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $examId = (int) ($_GET['exam_id'] ?? 0);
    if ($studentId <= 0) {
        json_response(['error' => 'Student ID is required'], 400);
    }

    $student = db_fetch(
        "SELECT s.*, c.name AS class_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.id = ?",
        [$studentId]
    );

    if (!$student) {
        json_response(['error' => 'Student not found'], 404);
    }

    $totalMarksExpr = db_column_exists('exam_results', 'total_marks') ? 'er.total_marks' : 'e.max_marks';
    $where = ['er.student_id = ?'];
    $params = [$studentId];
    if ($examId > 0) {
        $where[] = 'er.exam_id = ?';
        $params[] = $examId;
    }

    $results = db_fetchAll(
        "SELECT er.marks_obtained, er.grade, er.status, $totalMarksExpr AS total_marks,
                e.name AS exam_name, e.subject, e.exam_date, e.max_marks
         FROM exam_results er
         LEFT JOIN exams e ON er.exam_id = e.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY e.exam_date ASC, e.subject ASC",
        $params
    );

    $obtained = 0;
    $total = 0;
    $resultRows = [];
    foreach ($results as $result) {
        $obtained += (float) ($result['marks_obtained'] ?? 0);
        $total += (float) ($result['total_marks'] ?? 0);
        $percentage = ($result['total_marks'] ?? 0) > 0 ? ((float) $result['marks_obtained'] / (float) $result['total_marks']) * 100 : 0;
        $resultRows[] = [
            'Subject' => $result['subject'] ?: '-',
            'Exam' => $result['exam_name'] ?: '-',
            'Max Marks' => $result['total_marks'] ?? '-',
            'Obtained' => $result['marks_obtained'] ?? '-',
            'Percentage' => number_format($percentage, 2) . '%',
            'Grade' => $result['grade'] ?: '-',
            'Status' => ucfirst($result['status'] ?? ''),
        ];
    }

    $overallPercentage = $total > 0 ? ($obtained / $total) * 100 : 0;

    audit_log('EXPORT', 'pdf', 'Generated report card for student ID ' . $studentId);
    pdf_output_sections(
        safe_download_filename('report_card_' . ($student['admission_no'] ?? $studentId), 'pdf'),
        'Student Report Card',
        [
            [
                'heading' => 'Student Details',
                'lines' => [
                    'Name: ' . ($student['name'] ?: '-'),
                    'Admission No: ' . ($student['admission_no'] ?: '-'),
                    'Class: ' . ($student['class_name'] ?: '-'),
                    'Date of Birth: ' . ($student['dob'] ?: '-'),
                    'Parent Name: ' . ($student['parent_name'] ?: '-'),
                ],
            ],
            pdf_table_section('Exam Results', ['Subject', 'Exam', 'Max Marks', 'Obtained', 'Percentage', 'Grade', 'Status'], $resultRows),
            [
                'heading' => 'Summary',
                'lines' => [
                    'Total Obtained: ' . number_format($obtained, 2),
                    'Total Marks: ' . number_format($total, 2),
                    'Overall Percentage: ' . number_format($overallPercentage, 2) . '%',
                ],
            ],
        ],
        'Generated by ' . APP_NAME
    );
}

function generate_transfer_certificate_pdf()
{
    $studentId = (int) ($_GET['student_id'] ?? 0);
    if ($studentId <= 0) {
        json_response(['error' => 'Student ID is required'], 400);
    }

    $parentPhoneExpr = db_column_exists('students', 'parent_phone') ? 's.parent_phone' : 's.phone';
    $admissionDateExpr = db_column_exists('students', 'admission_date') ? 's.admission_date' : 's.created_at';
    $dischargeDateExpr = db_column_exists('students', 'discharge_date') ? 's.discharge_date' : 'NULL';
    $dischargeReasonExpr = db_column_exists('students', 'discharge_reason') ? 's.discharge_reason' : "''";

    $student = db_fetch(
        "SELECT s.*, c.name AS class_name,
                $parentPhoneExpr AS parent_phone,
                $admissionDateExpr AS admission_date,
                $dischargeDateExpr AS discharge_date,
                $dischargeReasonExpr AS discharge_reason
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.id = ?",
        [$studentId]
    );

    if (!$student) {
        json_response(['error' => 'Student not found'], 404);
    }

    $certificateText = 'This is to certify that ' . ($student['name'] ?: 'the student') .
        ' was enrolled in ' . APP_NAME . ' from ' . ($student['admission_date'] ?: '-') .
        ' to ' . ($student['discharge_date'] ?: 'the present date') .
        '. The student maintained a satisfactory academic record and conduct during the stated period.';

    audit_log('EXPORT', 'pdf', 'Generated transfer certificate for student ID ' . $studentId);
    pdf_output_sections(
        safe_download_filename('transfer_certificate_' . ($student['admission_no'] ?? $studentId), 'pdf'),
        'Transfer Certificate',
        [
            [
                'heading' => 'Student Details',
                'lines' => [
                    'Student Name: ' . ($student['name'] ?: '-'),
                    'Admission Number: ' . ($student['admission_no'] ?: '-'),
                    'Date of Birth: ' . ($student['dob'] ?: '-'),
                    'Gender: ' . ucfirst($student['gender'] ?? ''),
                    'Class: ' . ($student['class_name'] ?: '-'),
                    'Parent Name: ' . ($student['parent_name'] ?: '-'),
                    'Parent Phone: ' . ($student['parent_phone'] ?: '-'),
                    'Admission Date: ' . ($student['admission_date'] ?: '-'),
                    'Discharge Date: ' . ($student['discharge_date'] ?: '-'),
                    'Discharge Reason: ' . ($student['discharge_reason'] ?: '-'),
                ],
            ],
            [
                'heading' => 'Certificate',
                'lines' => [$certificateText],
            ],
        ],
        'Generated by ' . APP_NAME
    );
}
