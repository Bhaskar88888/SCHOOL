<?php
/**
 * Tally Export API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
require_role(['admin', 'superadmin', 'accounts']);

$payload = get_post_json();
$action = $_GET['action'] ?? ($payload['action'] ?? 'fees');
$format = strtolower($_GET['format'] ?? ($payload['format'] ?? 'xml'));
$dateFrom = $_GET['date_from'] ?? ($payload['startDate'] ?? date('Y-m-01'));
$dateTo = $_GET['date_to'] ?? ($payload['endDate'] ?? date('Y-m-d'));

switch ($action) {
    case 'fees':
        export_tally_fees($dateFrom, $dateTo, $format);
        break;
    case 'payroll':
        export_tally_payroll($payload, $format);
        break;
    case 'vouchers':
        export_tally_vouchers($dateFrom, $dateTo);
        break;
    default:
        json_response(['error' => 'Invalid tally action'], 400);
}

function export_tally_fees($dateFrom, $dateTo, $format)
{
    $rows = db_fetchAll(
        "SELECT f.receipt_no, f.fee_type, f.amount_paid, f.payment_method, f.paid_date,
                s.name AS student_name, s.admission_no
         FROM fees f
         LEFT JOIN students s ON f.student_id = s.id
         WHERE f.paid_date BETWEEN ? AND ?
         ORDER BY f.paid_date ASC",
        [$dateFrom, $dateTo]
    );

    if (!$rows) {
        json_response(['error' => 'No fee payments found in selected date range'], 400);
    }

    $filenameBase = "Tally_Fees_{$dateFrom}_to_{$dateTo}";
    output_tally_export($format, $filenameBase, build_fee_xml($rows), build_fee_json($rows), build_fee_csv($rows));
}

function export_tally_payroll(array $payload, $format)
{
    $monthInput = (string) ($_GET['month'] ?? ($payload['month'] ?? ''));
    $year = (int) ($_GET['year'] ?? ($payload['year'] ?? date('Y')));
    if ($monthInput === '') {
        json_response(['error' => 'Month is required'], 400);
    }

    $monthName = payroll_month_name($monthInput);
    $monthNumeric = payroll_month_numeric($monthInput);

    $rows = db_fetchAll(
        "SELECT p.id, p.month, p.year, p.basic_salary, p.allowances, p.deductions,
                COALESCE(p.net_salary, (p.basic_salary + p.allowances - p.deductions)) AS net_salary,
                u.name AS staff_name,
                " . (db_column_exists('users', 'employee_id') ? 'u.employee_id' : 'NULL') . " AS employee_id,
                " . (db_column_exists('users', 'department') ? 'u.department' : "''") . " AS department,
                " . (db_column_exists('users', 'designation') ? 'u.designation' : "''") . " AS designation
         FROM payroll p
         LEFT JOIN users u ON p.staff_id = u.id
         WHERE p.year = ?
           AND (CAST(p.month AS CHAR) = ? OR CAST(p.month AS CHAR) = ?)",
        [$year, (string) $monthNumeric, $monthName]
    );

    if (!$rows) {
        json_response(['error' => 'No payroll records found'], 400);
    }

    $filenameBase = "Tally_Payroll_{$year}_" . str_pad((string) $monthNumeric, 2, '0', STR_PAD_LEFT);
    output_tally_export($format, $filenameBase, build_payroll_xml($rows, $monthNumeric, $year), build_payroll_json($rows, $monthNumeric, $year), build_payroll_csv($rows, $monthNumeric, $year));
}

function export_tally_vouchers($dateFrom, $dateTo)
{
    $rows = db_fetchAll(
        "SELECT f.receipt_no, f.amount_paid, f.payment_method, f.paid_date, f.fee_type,
                s.name AS student_name, s.admission_no
         FROM fees f
         LEFT JOIN students s ON f.student_id = s.id
         WHERE f.paid_date BETWEEN ? AND ?
         ORDER BY f.paid_date DESC",
        [$dateFrom, $dateTo]
    );

    $vouchers = array_map(function ($row) {
        return [
            'voucherNo' => $row['receipt_no'],
            'date' => $row['paid_date'],
            'partyName' => $row['student_name'],
            'amount' => (float) $row['amount_paid'],
            'mode' => $row['payment_method'],
            'feeType' => $row['fee_type'],
            'admissionNo' => $row['admission_no'],
        ];
    }, $rows);

    json_response([
        'totalVouchers' => count($vouchers),
        'totalAmount' => array_reduce($vouchers, function ($sum, $voucher) {
            return $sum + (float) $voucher['amount'];
        }, 0),
        'vouchers' => $vouchers,
    ]);
}

function output_tally_export($format, $filenameBase, $xml, $json, $csv)
{
    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.json"');
        echo json_encode($json, JSON_PRETTY_PRINT);
        exit;
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
        echo $csv;
        exit;
    }

    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xml"');
    echo $xml;
    exit;
}

function build_fee_xml(array $rows)
{
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<ENVELOPE>\n  <HEADER>\n    <TALLYREQUEST>Import Data</TALLYREQUEST>\n  </HEADER>\n  <BODY>\n    <IMPORTDATA>\n      <REQUESTDESC>\n        <REPORTNAME>Vouchers</REPORTNAME>\n      </REQUESTDESC>\n      <REQUESTDATA>\n";

    foreach ($rows as $row) {
        $xml .= "        <TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n";
        $xml .= "          <VOUCHER VCHTYPE=\"Receipt\" ACTION=\"Create\">\n";
        $xml .= "            <DATE>" . date('d-m-Y', strtotime($row['paid_date'])) . "</DATE>\n";
        $xml .= "            <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>\n";
        $xml .= "            <VOUCHERNUMBER>" . xml_escape($row['receipt_no']) . "</VOUCHERNUMBER>\n";
        $xml .= "            <PARTYLEDGERNAME>" . xml_escape($row['student_name'] ?: 'Student') . "</PARTYLEDGERNAME>\n";
        $xml .= "            <AMOUNT>" . (float) $row['amount_paid'] . "</AMOUNT>\n";
        $xml .= "            <NARRATION>" . xml_escape(($row['fee_type'] ?: 'Fee') . ' - ' . ($row['student_name'] ?: 'Student')) . "</NARRATION>\n";
        $xml .= "          </VOUCHER>\n";
        $xml .= "        </TALLYMESSAGE>\n";
    }

    $xml .= "      </REQUESTDATA>\n    </IMPORTDATA>\n  </BODY>\n</ENVELOPE>\n";
    return $xml;
}

function build_fee_json(array $rows)
{
    return [
        'envelope' => [
            'header' => ['tallyRequest' => 'Import Data'],
            'body' => [
                'importData' => [
                    'requestDesc' => ['reportName' => 'Vouchers'],
                    'requestData' => array_map(function ($row) {
                        return [
                            'tallyMessage' => [
                                'voucher' => [
                                    'vchType' => 'Receipt',
                                    'action' => 'Create',
                                    'date' => date('d-m-Y', strtotime($row['paid_date'])),
                                    'voucherTypeName' => 'Receipt',
                                    'voucherNumber' => $row['receipt_no'],
                                    'partyLedgerName' => $row['student_name'],
                                    'amount' => (float) $row['amount_paid'],
                                    'narration' => ($row['fee_type'] ?: 'Fee') . ' - ' . ($row['student_name'] ?: 'Student'),
                                ],
                            ],
                        ];
                    }, $rows),
                ],
            ],
        ],
    ];
}

function build_fee_csv(array $rows)
{
    $lines = ['Date,Voucher No,Party Name,Amount,Mode,Fee Type,Narration'];
    foreach ($rows as $row) {
        $lines[] = implode(',', [
            csv_field($row['paid_date']),
            csv_field($row['receipt_no']),
            csv_field($row['student_name']),
            csv_field((float) $row['amount_paid']),
            csv_field($row['payment_method']),
            csv_field($row['fee_type']),
            csv_field(($row['fee_type'] ?: 'Fee') . ' - ' . ($row['student_name'] ?: 'Student')),
        ]);
    }
    return implode("\n", $lines);
}

function build_payroll_xml(array $rows, $monthNumeric, $year)
{
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<ENVELOPE>\n  <HEADER>\n    <TALLYREQUEST>Import Data</TALLYREQUEST>\n  </HEADER>\n  <BODY>\n    <IMPORTDATA>\n      <REQUESTDESC>\n        <REPORTNAME>Vouchers</REPORTNAME>\n      </REQUESTDESC>\n      <REQUESTDATA>\n";

    foreach ($rows as $row) {
        $date = sprintf('%02d-01-%04d', $monthNumeric, $year);
        $xml .= "        <TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n";
        $xml .= "          <VOUCHER VCHTYPE=\"Payment\" ACTION=\"Create\">\n";
        $xml .= "            <DATE>" . $date . "</DATE>\n";
        $xml .= "            <VOUCHERTYPENAME>Payment</VOUCHERTYPENAME>\n";
        $xml .= "            <VOUCHERNUMBER>SAL-" . $year . '-' . str_pad((string) $monthNumeric, 2, '0', STR_PAD_LEFT) . "</VOUCHERNUMBER>\n";
        $xml .= "            <PARTYLEDGERNAME>" . xml_escape($row['staff_name'] ?: 'Staff') . "</PARTYLEDGERNAME>\n";
        $xml .= "            <AMOUNT>-" . (float) $row['net_salary'] . "</AMOUNT>\n";
        $xml .= "            <NARRATION>" . xml_escape('Salary for ' . $monthNumeric . '/' . $year . ' - ' . ($row['staff_name'] ?: 'Staff')) . "</NARRATION>\n";
        $xml .= "          </VOUCHER>\n";
        $xml .= "        </TALLYMESSAGE>\n";
    }

    $xml .= "      </REQUESTDATA>\n    </IMPORTDATA>\n  </BODY>\n</ENVELOPE>\n";
    return $xml;
}

function build_payroll_json(array $rows, $monthNumeric, $year)
{
    return [
        'envelope' => [
            'header' => ['tallyRequest' => 'Import Data'],
            'body' => [
                'importData' => [
                    'requestDesc' => ['reportName' => 'Vouchers'],
                    'requestData' => array_map(function ($row) use ($monthNumeric, $year) {
                        return [
                            'tallyMessage' => [
                                'voucher' => [
                                    'vchType' => 'Payment',
                                    'action' => 'Create',
                                    'date' => sprintf('%02d-01-%04d', $monthNumeric, $year),
                                    'voucherTypeName' => 'Payment',
                                    'voucherNumber' => 'SAL-' . $year . '-' . str_pad((string) $monthNumeric, 2, '0', STR_PAD_LEFT),
                                    'partyLedgerName' => $row['staff_name'],
                                    'amount' => -(float) $row['net_salary'],
                                    'narration' => 'Salary for ' . $monthNumeric . '/' . $year,
                                ],
                            ],
                        ];
                    }, $rows),
                ],
            ],
        ],
    ];
}

function build_payroll_csv(array $rows, $monthNumeric, $year)
{
    $lines = ['Date,Voucher No,Party Name,Amount,Department,Designation,Narration'];
    foreach ($rows as $row) {
        $lines[] = implode(',', [
            csv_field(sprintf('%04d-%02d-01', $year, $monthNumeric)),
            csv_field('SAL-' . $year . '-' . str_pad((string) $monthNumeric, 2, '0', STR_PAD_LEFT)),
            csv_field($row['staff_name']),
            csv_field((float) $row['net_salary']),
            csv_field($row['department']),
            csv_field($row['designation']),
            csv_field('Salary for ' . $monthNumeric . '/' . $year),
        ]);
    }
    return implode("\n", $lines);
}

function csv_field($value)
{
    $value = str_replace('"', '""', (string) $value);
    return '"' . $value . '"';
}

function payroll_month_numeric($value)
{
    if (ctype_digit((string) $value)) {
        $month = (int) $value;
        return max(1, min(12, $month));
    }

    $lookup = array_flip([
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ]);
    return $lookup[ucfirst(strtolower((string) $value))] ?? (int) date('n');
}

function payroll_month_name($value)
{
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[payroll_month_numeric($value)] ?? date('F');
}
