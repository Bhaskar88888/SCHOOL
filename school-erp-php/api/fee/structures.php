<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_role(['superadmin', 'admin', 'accounts', 'accountant']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

// GET — list fee structures, optionally filtered by class
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $classId    = (int) ($_GET['class_id'] ?? 0);
    $year       = sanitize($_GET['academic_year'] ?? '');
    $params     = [];
    $conditions = [];

    if ($classId > 0) {
        $conditions[] = 'fs.class_id = ?';
        $params[]     = $classId;
    }
    if (!empty($year)) {
        $conditions[] = 'fs.academic_year = ?';
        $params[]     = $year;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $structures = db_fetchAll(
        "SELECT fs.*, c.name AS class_name
         FROM fee_structures fs
         LEFT JOIN classes c ON fs.class_id = c.id
         $where
         ORDER BY fs.academic_year DESC, c.name ASC, fs.fee_type ASC",
        $params
    );

    json_response(['structures' => $structures]);
}

// POST — create a new fee structure
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();

    $action = $data['action'] ?? 'create';

    // Bulk invoice generation: create a fee record for every student in a class
    if ($action === 'generate_invoices') {
        $classId     = (int) ($data['class_id']     ?? 0);
        $structureId = (int) ($data['structure_id'] ?? 0);

        if (!$classId || !$structureId) {
            json_response(['error' => 'class_id and structure_id are required'], 400);
        }

        $structure = db_fetch("SELECT * FROM fee_structures WHERE id = ?", [$structureId]);
        if (!$structure) {
            json_response(['error' => 'Fee structure not found'], 404);
        }

        $students = db_fetchAll(
            "SELECT id FROM students WHERE class_id = ? AND is_active = 1",
            [$classId]
        );

        if (empty($students)) {
            json_response(['error' => 'No active students found in this class'], 404);
        }

        require_once __DIR__ . '/../../includes/notify.php';
        $generated = 0;

        foreach ($students as $student) {
            // Skip if an invoice for this student+type+year already exists
            $existing = db_count(
                "SELECT COUNT(*) FROM fees WHERE student_id = ? AND fee_type = ? AND year = ?",
                [$student['id'], $structure['fee_type'], (int) date('Y', strtotime($structure['academic_year'] . '-01-01'))]
            );
            if ($existing > 0) {
                continue;
            }

            $receiptNo = generate_auto_id('receipt', 'RCP');
            $amount    = (float) $structure['amount'];
            $dueDate   = $structure['due_date'] ?? date('Y-m-d', strtotime('+30 days'));

            db_insert(
                "INSERT INTO fees (student_id, fee_type, total_amount, amount_paid, balance_amount,
                                   payment_method, receipt_no, paid_date, due_date, month, year,
                                   remarks, collected_by, created_at)
                 VALUES (?,?,?,0,?,?,?,NULL,?,?,?,?,?,NOW())",
                [
                    $student['id'],
                    $structure['fee_type'],
                    $amount,
                    $amount,           // balance = full amount (nothing paid yet)
                    'pending',
                    $receiptNo,
                    $dueDate,
                    date('F'),
                    (int) date('Y'),
                    'Auto-generated from fee structure',
                    get_current_user_id(),
                ]
            );
            $generated++;
        }

        audit_log('CREATE', 'fee_invoices', $structureId, null, ['generated' => $generated]);
        json_response(['success' => true, 'generated' => $generated]);
    }

    // Default: create fee structure
    $classId  = (int) ($data['class_id'] ?? 0);
    $feeType  = sanitize($data['fee_type'] ?? '');
    $amount   = (float) ($data['amount'] ?? 0);
    $year     = sanitize($data['academic_year'] ?? date('Y') . '-' . (date('Y') + 1));

    if (!$classId || !$feeType || $amount <= 0) {
        json_response(['error' => 'class_id, fee_type, and amount are required'], 400);
    }

    $id = db_insert(
        "INSERT INTO fee_structures (class_id, fee_type, amount, academic_year, term, due_date, late_fee, description, created_at)
         VALUES (?,?,?,?,?,?,?,?,NOW())",
        [
            $classId,
            $feeType,
            $amount,
            $year,
            sanitize($data['term'] ?? 'Annual'),
            $data['due_date'] ?? null,
            (float) ($data['late_fee'] ?? 0),
            sanitize($data['description'] ?? ''),
        ]
    );

    audit_log('CREATE', 'fee_structures', $id, null, ['class_id' => $classId, 'fee_type' => $feeType, 'amount' => $amount]);
    json_response(['success' => true, 'id' => $id], 201);
}

// PUT — update a fee structure
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = get_post_json();
    $id   = (int) ($data['id'] ?? 0);
    if (!$id) {
        json_response(['error' => 'ID required'], 400);
    }

    db_query(
        "UPDATE fee_structures SET fee_type=?, amount=?, term=?, due_date=?, late_fee=?, description=? WHERE id=?",
        [
            sanitize($data['fee_type'] ?? ''),
            (float) ($data['amount'] ?? 0),
            sanitize($data['term'] ?? 'Annual'),
            $data['due_date'] ?? null,
            (float) ($data['late_fee'] ?? 0),
            sanitize($data['description'] ?? ''),
            $id,
        ]
    );

    audit_log('UPDATE', 'fee_structures', $id, null, $data);
    json_response(['success' => true]);
}

// DELETE — remove a fee structure
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        json_response(['error' => 'ID required'], 400);
    }
    db_query("DELETE FROM fee_structures WHERE id = ?", [$id]);
    audit_log('DELETE', 'fee_structures', $id, null, null);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
