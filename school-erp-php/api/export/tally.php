<?php
/**
 * Tally Accounting Export
 * School ERP PHP v3.0
 * Exports fee and payroll data in Tally-compatible formats (XML, JSON, CSV)
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
require_role(['admin', 'superadmin', 'accounts']);

$action = $_GET['action'] ?? 'fees';
$format = $_GET['format'] ?? 'xml'; // xml, json, csv
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// ============================================
// FEE VOUCHERS EXPORT
// ============================================
if ($action === 'fees') {
    $sql = "SELECT f.receipt_no, f.fee_type, f.total_amount, f.amount_paid, f.payment_method, f.paid_date,
                   s.name as student_name, s.admission_no, c.name as class_name
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            LEFT JOIN classes c ON s.class_id = c.id 
            WHERE f.paid_date BETWEEN ? AND ? 
            ORDER BY f.paid_date";
    
    $fees = db_fetchAll($sql, [$dateFrom, $dateTo]);
    
    if ($format === 'xml') {
        export_tally_xml_fees($fees);
    } elseif ($format === 'json') {
        export_tally_json_fees($fees);
    } else {
        export_tally_csv_fees($fees);
    }
}

// ============================================
// PAYROLL VOUCHERS EXPORT
// ============================================
if ($action === 'payroll') {
    $sql = "SELECT p.id, p.month, p.year, p.basic_salary, p.total_earnings, p.total_deductions, p.net_pay,
                   u.name as staff_name, u.employee_id, u.department
            FROM payroll p 
            LEFT JOIN users u ON p.staff_id = u.id 
            WHERE p.is_paid = 1 
              AND STR_TO_DATE(CONCAT(p.year, '-', p.month, '-01'), '%Y-%m-%d') BETWEEN ? AND ?
            ORDER BY p.year, p.month";
    
    $payrolls = db_fetchAll($sql, [$dateFrom, $dateTo]);
    
    if ($format === 'xml') {
        export_tally_xml_payroll($payrolls);
    } elseif ($format === 'json') {
        export_tally_json_payroll($payrolls);
    } else {
        export_tally_csv_payroll($payrolls);
    }
}

// ============================================
// TALLY XML FEE EXPORT
// ============================================
function export_tally_xml_fees($fees) {
    header('Content-Type: text/xml');
    header('Content-Disposition: attachment; filename="tally_fees_' . date('Y-m-d') . '.xml"');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<ENVELOPE>' . "\n";
    echo '  <HEADER>' . "\n";
    echo '    <VERSION>1</VERSION>' . "\n";
    echo '    <TALLYREQUEST>Import</TALLYREQUEST>' . "\n";
    echo '    <TYPE>Data</TYPE>' . "\n";
    echo '    <ID>All Masters</ID>' . "\n";
    echo '  </HEADER>' . "\n";
    echo '  <BODY>' . "\n";
    echo '    <DESC>' . "\n";
    echo '      <STATICVARIABLES>' . "\n";
    echo '        <SVCURRENTCOMPANY>' . xml_escape(APP_NAME) . '</SVCURRENTCOMPANY>' . "\n";
    echo '      </STATICVARIABLES>' . "\n";
    echo '    </DESC>' . "\n";
    echo '    <DATA>' . "\n";
    
    foreach ($fees as $fee) {
        echo '      <TALLYMESSAGE>' . "\n";
        echo '        <VOUCHER VCHTYPE="Receipt" ACTION="Create">' . "\n";
        echo '          <DATE>' . date('Ymd', strtotime($fee['paid_date'])) . '</DATE>' . "\n";
        echo '          <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>' . "\n";
        echo '          <VOUCHERNUMBER>' . xml_escape($fee['receipt_no']) . '</VOUCHERNUMBER>' . "\n";
        echo '          <REFERENCE>' . xml_escape($fee['receipt_no']) . '</REFERENCE>' . "\n";
        echo '          <PARTYLEDGERNAME>' . xml_escape($fee['student_name']) . '</PARTYLEDGERNAME>' . "\n";
        echo '          <NARRATION>' . xml_escape($fee['fee_type'] . ' for ' . $fee['student_name'] . ' - ' . $fee['class_name']) . '</NARRATION>' . "\n";
        echo '          <ALLLEDGERENTRIES.LIST>' . "\n";
        echo '            <LEDGERNAME>' . xml_escape($fee['student_name']) . '</LEDGERNAME>' . "\n";
        echo '            <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>' . "\n";
        echo '            <AMOUNT>' . $fee['amount_paid'] . '</AMOUNT>' . "\n";
        echo '          </ALLLEDGERENTRIES.LIST>' . "\n";
        echo '          <ALLLEDGERENTRIES.LIST>' . "\n";
        echo '            <LEDGERNAME>Fee Income</LEDGERNAME>' . "\n";
        echo '            <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>' . "\n";
        echo '            <AMOUNT>-' . $fee['amount_paid'] . '</AMOUNT>' . "\n";
        echo '          </ALLLEDGERENTRIES.LIST>' . "\n";
        echo '        </VOUCHER>' . "\n";
        echo '      </TALLYMESSAGE>' . "\n";
    }
    
    echo '    </DATA>' . "\n";
    echo '  </BODY>' . "\n";
    echo '</ENVELOPE>';
    exit;
}

// ============================================
// TALLY JSON FEE EXPORT
// ============================================
function export_tally_json_fees($fees) {
    $vouchers = [];
    
    foreach ($fees as $fee) {
        $vouchers[] = [
            'VoucherType' => 'Receipt',
            'Date' => $fee['paid_date'],
            'VoucherNumber' => $fee['receipt_no'],
            'Reference' => $fee['receipt_no'],
            'PartyLedger' => $fee['student_name'],
            'Narration' => $fee['fee_type'] . ' for ' . $fee['student_name'] . ' - ' . $fee['class_name'],
            'Entries' => [
                [
                    'Ledger' => $fee['student_name'],
                    'IsDeemedPositive' => 'Yes',
                    'Amount' => $fee['amount_paid'],
                ],
                [
                    'Ledger' => 'Fee Income',
                    'IsDeemedPositive' => 'No',
                    'Amount' => -$fee['amount_paid'],
                ],
            ],
        ];
    }
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="tally_fees_' . date('Y-m-d') . '.json"');
    echo json_encode(['vouchers' => $vouchers], JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// TALLY CSV FEE EXPORT
// ============================================
function export_tally_csv_fees($fees) {
    $headers = ['Voucher Type', 'Date', 'Voucher Number', 'Party Ledger', 'Narration', 'Amount'];
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tally_fees_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    foreach ($fees as $fee) {
        fputcsv($output, [
            'Receipt',
            $fee['paid_date'],
            $fee['receipt_no'],
            $fee['student_name'],
            $fee['fee_type'] . ' for ' . $fee['student_name'],
            $fee['amount_paid'],
        ]);
    }
    
    fclose($output);
    exit;
}

// ============================================
// TALLY XML PAYROLL EXPORT
// ============================================
function export_tally_xml_payroll($payrolls) {
    header('Content-Type: text/xml');
    header('Content-Disposition: attachment; filename="tally_payroll_' . date('Y-m-d') . '.xml"');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<ENVELOPE>' . "\n";
    echo '  <HEADER>' . "\n";
    echo '    <VERSION>1</VERSION>' . "\n";
    echo '    <TALLYREQUEST>Import</TALLYREQUEST>' . "\n";
    echo '    <TYPE>Data</TYPE>' . "\n";
    echo '  </HEADER>' . "\n";
    echo '  <BODY>' . "\n";
    echo '    <DATA>' . "\n";
    
    foreach ($payrolls as $payroll) {
        $date = $payroll['year'] . '-' . str_pad($payroll['month'], 2, '0', STR_PAD_LEFT) . '-28';
        
        echo '      <TALLYMESSAGE>' . "\n";
        echo '        <VOUCHER VCHTYPE="Payment" ACTION="Create">' . "\n";
        echo '          <DATE>' . date('Ymd', strtotime($date)) . '</DATE>' . "\n";
        echo '          <VOUCHERTYPENAME>Payment</VOUCHERTYPENAME>' . "\n";
        echo '          <VOUCHERNUMBER>PAY-' . $payroll['employee_id'] . '-' . $payroll['month'] . '-' . $payroll['year'] . '</VOUCHERNUMBER>' . "\n";
        echo '          <PARTYLEDGERNAME>' . xml_escape($payroll['staff_name']) . '</PARTYLEDGERNAME>' . "\n";
        echo '          <NARRATION>Salary for ' . $payroll['month'] . '/' . $payroll['year'] . ' - ' . xml_escape($payroll['staff_name']) . '</NARRATION>' . "\n";
        echo '          <ALLLEDGERENTRIES.LIST>' . "\n";
        echo '            <LEDGERNAME>' . xml_escape($payroll['staff_name']) . '</LEDGERNAME>' . "\n";
        echo '            <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>' . "\n";
        echo '            <AMOUNT>' . $payroll['net_pay'] . '</AMOUNT>' . "\n";
        echo '          </ALLLEDGERENTRIES.LIST>' . "\n";
        echo '          <ALLLEDGERENTRIES.LIST>' . "\n";
        echo '            <LEDGERNAME>Salary Expense</LEDGERNAME>' . "\n";
        echo '            <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>' . "\n";
        echo '            <AMOUNT>-' . $payroll['net_pay'] . '</AMOUNT>' . "\n";
        echo '          </ALLLEDGERENTRIES.LIST>' . "\n";
        echo '        </VOUCHER>' . "\n";
        echo '      </TALLYMESSAGE>' . "\n";
    }
    
    echo '    </DATA>' . "\n";
    echo '  </BODY>' . "\n";
    echo '</ENVELOPE>';
    exit;
}

// ============================================
// TALLY JSON PAYROLL EXPORT
// ============================================
function export_tally_json_payroll($payrolls) {
    $vouchers = [];
    
    foreach ($payrolls as $payroll) {
        $date = $payroll['year'] . '-' . str_pad($payroll['month'], 2, '0', STR_PAD_LEFT) . '-28';
        
        $vouchers[] = [
            'VoucherType' => 'Payment',
            'Date' => $date,
            'VoucherNumber' => 'PAY-' . $payroll['employee_id'] . '-' . $payroll['month'] . '-' . $payroll['year'],
            'PartyLedger' => $payroll['staff_name'],
            'Narration' => 'Salary for ' . $payroll['month'] . '/' . $payroll['year'] . ' - ' . $payroll['staff_name'],
            'Entries' => [
                [
                    'Ledger' => $payroll['staff_name'],
                    'IsDeemedPositive' => 'No',
                    'Amount' => $payroll['net_pay'],
                ],
                [
                    'Ledger' => 'Salary Expense',
                    'IsDeemedPositive' => 'Yes',
                    'Amount' => -$payroll['net_pay'],
                ],
            ],
        ];
    }
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="tally_payroll_' . date('Y-m-d') . '.json"');
    echo json_encode(['vouchers' => $vouchers], JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// TALLY CSV PAYROLL EXPORT
// ============================================
function export_tally_csv_payroll($payrolls) {
    $headers = ['Voucher Type', 'Date', 'Voucher Number', 'Party Ledger', 'Narration', 'Amount'];
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tally_payroll_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    foreach ($payrolls as $payroll) {
        $date = $payroll['year'] . '-' . str_pad($payroll['month'], 2, '0', STR_PAD_LEFT) . '-28';
        
        fputcsv($output, [
            'Payment',
            $date,
            'PAY-' . $payroll['employee_id'] . '-' . $payroll['month'] . '-' . $payroll['year'],
            $payroll['staff_name'],
            'Salary for ' . $payroll['month'] . '/' . $payroll['year'],
            $payroll['net_pay'],
        ]);
    }
    
    fclose($output);
    exit;
}

function xml_escape($value) {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
