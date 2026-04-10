<?php
/**
 * Import API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
require_role(['admin', 'superadmin', 'hr']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$mode = $_GET['mode'] ?? '';
$module = $_GET['module'] ?? '';

if ($mode === 'upload') {
    handle_upload_preview();
}

if ($module !== '') {
    handle_import($module);
}

json_response(['error' => 'Invalid import request'], 400);

function handle_upload_preview()
{
    $saved = save_import_upload();
    $rows = read_import_rows($saved['path']);
    $preview = array_slice($rows, 0, 5);

    json_response([
        'message' => 'File uploaded successfully',
        'importData' => [
            'filepath' => $saved['token'],
            'originalName' => $saved['original_name'],
            'preview' => $preview,
            'totalRows' => count($rows),
        ],
    ]);
}

function handle_import($module)
{
    $payload = get_post_json();
    $defaultPassword = trim((string) ($payload['defaultPassword'] ?? 'Password123'));

    if ($defaultPassword === '') {
        $defaultPassword = 'Password123';
    }

    try {
        if (!empty($payload['filepath'])) {
            $filepath = resolve_import_token($payload['filepath']);
        } else {
            $saved = save_import_upload();
            $filepath = $saved['path'];
        }

        $rows = read_import_rows($filepath);

        switch ($module) {
            case 'students':
                $result = import_students($rows, $defaultPassword);
                break;
            case 'staff':
                $result = import_staff($rows, $defaultPassword);
                break;
            case 'fees':
                $result = import_fees($rows);
                break;
            default:
                json_response(['error' => 'Invalid module'], 400);
        }

        AuditLogger::import($module, $result['imported']);
        @unlink($filepath);
        json_response($result);
    } catch (Exception $e) {
        @unlink($filepath);
        json_response(['error' => $e->getMessage()], 400);
    }
}

function save_import_upload()
{
    if (!isset($_FILES['file'])) {
        json_response(['error' => 'No file uploaded'], 400);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'File upload error'], 400);
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        json_response(['error' => 'File size exceeds limit'], 400);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
        json_response(['error' => 'Only CSV, XLSX, and XLS files are allowed'], 400);
    }

    if ($extension === 'xls') {
        json_response(['error' => 'Legacy XLS files are not supported by this PHP build. Please resave the file as XLSX or CSV.'], 400);
    }

    $uploadDir = import_upload_dir();
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $token = uniqid('import_', true) . '.' . $extension;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $token;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        json_response(['error' => 'Failed to save uploaded file'], 500);
    }

    return [
        'token' => $token,
        'path' => $destination,
        'original_name' => $file['name'],
    ];
}

function resolve_import_token($token)
{
    $safeToken = basename((string) $token);
    if ($safeToken === '') {
        throw new RuntimeException('Invalid import file reference');
    }

    $path = import_upload_dir() . DIRECTORY_SEPARATOR . $safeToken;
    if (!is_file($path)) {
        throw new RuntimeException('Import file not found. Upload the file again.');
    }

    return $path;
}

function import_upload_dir()
{
    return __DIR__ . '/../../uploads/imports';
}

function read_import_rows($filepath)
{
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    if ($extension === 'csv') {
        return read_csv_rows($filepath);
    }
    if ($extension === 'xlsx') {
        return read_xlsx_rows($filepath);
    }
    throw new RuntimeException('Unsupported file format');
}

function read_csv_rows($filepath)
{
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        throw new RuntimeException('Unable to open CSV file');
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return [];
    }
    $headers = normalize_headers($headers);

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (row_is_empty($row)) {
            continue;
        }
        $rows[] = combine_row($headers, $row);
    }

    fclose($handle);
    return $rows;
}

function read_xlsx_rows($filepath)
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available for XLSX import');
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        throw new RuntimeException('Unable to open XLSX file');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $item) {
                $parts = [];
                if (isset($item->t)) {
                    $parts[] = (string) $item->t;
                }
                if (isset($item->r)) {
                    foreach ($item->r as $run) {
                        $parts[] = (string) $run->t;
                    }
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('Worksheet data not found in XLSX file');
    }

    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData->row)) {
        return [];
    }

    $allRows = [];
    foreach ($sheet->sheetData->row as $row) {
        $values = [];
        foreach ($row->c as $cell) {
            $reference = (string) $cell['r'];
            $columnIndex = column_index_from_reference($reference);
            $value = '';

            if (isset($cell->is->t)) {
                $value = (string) $cell->is->t;
            } elseif ((string) $cell['t'] === 's') {
                $sharedIndex = (int) $cell->v;
                $value = $sharedStrings[$sharedIndex] ?? '';
            } elseif (isset($cell->v)) {
                $value = (string) $cell->v;
            }

            $values[$columnIndex] = $value;
        }

        if (!empty($values)) {
            ksort($values);
            $maxIndex = max(array_keys($values));
            $ordered = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $ordered[] = $values[$i] ?? '';
            }
            $allRows[] = $ordered;
        }
    }

    if (empty($allRows)) {
        return [];
    }

    $headers = normalize_headers(array_shift($allRows));
    $rows = [];
    foreach ($allRows as $row) {
        if (row_is_empty($row)) {
            continue;
        }
        $rows[] = combine_row($headers, $row);
    }

    return $rows;
}

function column_index_from_reference($reference)
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper((string) $reference));
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }
    return max(0, $index - 1);
}

function normalize_headers(array $headers)
{
    return array_map(function ($header) {
        return trim((string) $header);
    }, $headers);
}

function combine_row(array $headers, array $row)
{
    $data = [];
    foreach ($headers as $index => $header) {
        if ($header === '') {
            continue;
        }
        $data[$header] = trim((string) ($row[$index] ?? ''));
    }
    return $data;
}

function row_is_empty(array $row)
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}

function import_students(array $rows, $defaultPassword)
{
    $success = [];
    $failed = [];

    foreach ($rows as $index => $row) {
        $rowNumber = $index + 2;
        try {
            $name = row_value($row, ['Name', 'name']);
            $classValue = row_value($row, ['Class', 'class']);
            $admissionNo = row_value($row, ['Admission No', 'admission_no']);

            if ($name === '' || $classValue === '') {
                throw new RuntimeException('Name and Class are required');
            }

            $class = resolve_class($classValue);
            if (!$class) {
                throw new RuntimeException("Class '{$classValue}' not found");
            }

            if ($admissionNo === '') {
                $admissionNo = generate_admission_no();
            }

            $existing = db_fetch("SELECT id FROM students WHERE admission_no = ?", [$admissionNo]);
            if ($existing) {
                throw new RuntimeException("Admission number '{$admissionNo}' already exists");
            }

            db_beginTransaction();

            $studentUserId = null;
            $email = row_value($row, ['Email', 'email']);
            if ($email !== '' && db_column_exists('students', 'user_id')) {
                $existingUser = db_fetch("SELECT id FROM users WHERE email = ?", [$email]);
                if (!$existingUser) {
                    $userData = db_filter_data_for_table('users', [
                        'name' => $name,
                        'email' => $email,
                        'password' => password_hash($defaultPassword, PASSWORD_BCRYPT),
                        'role' => storage_role_name('student'),
                        'phone' => row_value($row, ['Phone', 'phone']),
                        'is_active' => 1,
                    ]);

                    $columns = array_keys($userData);
                    $studentUserId = db_insert(
                        "INSERT INTO users (" . implode(', ', array_map(function ($column) {
                            return "`$column`";
                        }, $columns)) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
                        array_values($userData)
                    );
                } else {
                    $studentUserId = $existingUser['id'];
                }
            }

            $studentData = db_filter_data_for_table('students', [
                'admission_no' => $admissionNo,
                'name' => $name,
                'class_id' => $class['id'],
                'dob' => normalize_date_or_null(row_value($row, ['DOB', 'dob'])),
                'gender' => normalize_gender(row_value($row, ['Gender', 'gender'], 'male')),
                'parent_name' => row_value($row, ['Parent Name', 'parent_name']),
                'parent_phone' => row_value($row, ['Parent Phone', 'parent_phone']),
                'parent_email' => row_value($row, ['Parent Email', 'parent_email']),
                'phone' => row_value($row, ['Phone', 'phone']),
                'email' => $email,
                'address' => row_value($row, ['Address', 'address']),
                'admission_date' => normalize_date_or_null(row_value($row, ['Admission Date', 'admission_date'])),
                'user_id' => $studentUserId,
                'is_active' => 1,
            ]);

            $columns = array_keys($studentData);
            $studentId = db_insert(
                "INSERT INTO students (" . implode(', ', array_map(function ($column) {
                    return "`$column`";
                }, $columns)) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
                array_values($studentData)
            );

            db_commit();
            $success[] = [
                'row' => $rowNumber,
                'name' => $name,
                'admissionNo' => $admissionNo,
                'studentId' => $studentId,
            ];
        } catch (Exception $e) {
            if (db_inTransaction()) {
                db_rollback();
            }
            $failed[] = [
                'row' => $rowNumber,
                'error' => $e->getMessage(),
            ];
        }
    }

    return build_import_response('students', $success, $failed);
}

function import_staff(array $rows, $defaultPassword)
{
    $success = [];
    $failed = [];

    foreach ($rows as $index => $row) {
        $rowNumber = $index + 2;
        try {
            $name = row_value($row, ['Name', 'name']);
            $email = row_value($row, ['Email', 'email']);
            $role = normalize_role_name(row_value($row, ['Role', 'role'], 'teacher'));

            if ($name === '' || $email === '' || $role === '') {
                throw new RuntimeException('Name, Email, and Role are required');
            }

            Validator::reset();
            Validator::email($email);
            Validator::in($role, all_school_roles(), 'role');
            if (Validator::hasErrors()) {
                throw new RuntimeException(implode(', ', Validator::errors()));
            }

            $existing = db_fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                throw new RuntimeException("Email '{$email}' already exists");
            }

            $employeeId = row_value($row, ['Employee ID', 'employee_id']);
            if ($employeeId === '') {
                $employeeId = generate_employee_id();
            }

            $password = row_value($row, ['Password', 'password'], $defaultPassword);
            if ($password === '') {
                $password = $defaultPassword;
            }

            $insertData = db_filter_data_for_table('users', [
                'employee_id' => $employeeId,
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'role' => storage_role_name($role),
                'department' => row_value($row, ['Department', 'department']),
                'designation' => row_value($row, ['Designation', 'designation']),
                'phone' => row_value($row, ['Phone', 'phone']),
                'is_active' => 1,
            ]);

            $columns = array_keys($insertData);
            $userId = db_insert(
                "INSERT INTO users (" . implode(', ', array_map(function ($column) {
                    return "`$column`";
                }, $columns)) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
                array_values($insertData)
            );

            $success[] = [
                'row' => $rowNumber,
                'name' => $name,
                'employeeId' => $employeeId,
                'userId' => $userId,
            ];
        } catch (Exception $e) {
            $failed[] = [
                'row' => $rowNumber,
                'error' => $e->getMessage(),
            ];
        }
    }

    return build_import_response('staff', $success, $failed);
}

function import_fees(array $rows)
{
    $success = [];
    $failed = [];

    foreach ($rows as $index => $row) {
        $rowNumber = $index + 2;
        try {
            $admissionNo = row_value($row, ['Admission No', 'admission_no']);
            $totalAmount = row_value($row, ['Total Amount', 'total_amount']);
            if ($admissionNo === '' || $totalAmount === '') {
                throw new RuntimeException('Admission No and Total Amount are required');
            }

            $student = db_fetch("SELECT id FROM students WHERE admission_no = ?", [$admissionNo]);
            if (!$student) {
                throw new RuntimeException("Student '{$admissionNo}' not found");
            }

            $receiptNo = row_value($row, ['Receipt No', 'receipt_no']);
            if ($receiptNo === '') {
                $receiptNo = generate_receipt_no();
            }

            $insertData = db_filter_data_for_table('fees', [
                'student_id' => $student['id'],
                'fee_type' => row_value($row, ['Fee Type', 'fee_type'], 'Tuition Fee'),
                'total_amount' => (float) $totalAmount,
                'original_amount' => (float) row_value($row, ['Original Amount', 'original_amount'], $totalAmount),
                'amount_paid' => (float) row_value($row, ['Amount Paid', 'amount_paid'], 0),
                'payment_method' => strtolower(row_value($row, ['Payment Method', 'payment_method'], 'cash')),
                'paid_date' => normalize_date_or_null(row_value($row, ['Paid Date', 'paid_date'], date('Y-m-d'))),
                'month' => row_value($row, ['Month', 'month']),
                'year' => (int) row_value($row, ['Year', 'year'], date('Y')),
                'academic_year' => row_value($row, ['Academic Year', 'academic_year'], current_academic_year()),
                'receipt_no' => $receiptNo,
                'discount' => (float) row_value($row, ['Discount', 'discount'], 0),
            ]);

            $columns = array_keys($insertData);
            $feeId = db_insert(
                "INSERT INTO fees (" . implode(', ', array_map(function ($column) {
                    return "`$column`";
                }, $columns)) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
                array_values($insertData)
            );

            $success[] = [
                'row' => $rowNumber,
                'receiptNo' => $receiptNo,
                'admissionNo' => $admissionNo,
                'feeId' => $feeId,
            ];
        } catch (Exception $e) {
            $failed[] = [
                'row' => $rowNumber,
                'error' => $e->getMessage(),
            ];
        }
    }

    return build_import_response('fees', $success, $failed);
}

function build_import_response($module, array $success, array $failed)
{
    $total = count($success) + count($failed);
    return [
        'message' => sprintf('Import completed. %d of %d %s imported.', count($success), $total, $module),
        'imported' => count($success),
        'errors' => array_map(function ($item) {
            return 'Row ' . $item['row'] . ': ' . $item['error'];
        }, $failed),
        'total_rows' => $total,
        'results' => [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
        ],
    ];
}

function row_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
            return trim((string) $row[$key]);
        }
    }
    return $default;
}

function resolve_class($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (ctype_digit($value)) {
        $class = db_fetch("SELECT id, name, section FROM classes WHERE id = ?", [(int) $value]);
        if ($class) {
            return $class;
        }
    }

    $class = db_fetch("SELECT id, name, section FROM classes WHERE name = ?", [$value]);
    if ($class) {
        return $class;
    }

    return db_fetch(
        "SELECT id, name, section FROM classes WHERE TRIM(CONCAT(name, ' ', COALESCE(section, ''))) = ?",
        [$value]
    );
}

function normalize_date_or_null($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function normalize_gender($value)
{
    $value = strtolower(trim((string) $value));
    if (in_array($value, ['male', 'female', 'other'], true)) {
        return $value;
    }
    return 'male';
}

function generate_admission_no()
{
    return 'ADM' . date('Y') . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
}

function generate_employee_id()
{
    return 'EMP' . date('Y') . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generate_receipt_no()
{
    return 'REC' . date('Y') . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
}
