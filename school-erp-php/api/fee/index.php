<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_role(['superadmin', 'admin', 'accounts', 'accountant']);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $status = sanitize($_GET['status'] ?? '');

    $where = "WHERE (s.name LIKE ? OR f.receipt_no LIKE ?)";
    $params = [$search, $search];
    if (db_column_exists('fees', 'is_deleted')) {
        $where .= " AND COALESCE(f.is_deleted, 0) = 0";
    }
    if ($status === 'paid') {
        $where .= " AND f.balance_amount <= 0";
    } elseif ($status === 'pending') {
        $where .= " AND f.balance_amount > 0";
    }

    $sql = "SELECT f.*, s.name as student_name, c.name as class_name,
                   CASE WHEN f.balance_amount <= 0 THEN 'paid' ELSE 'pending' END as balance_status
            FROM fees f
            LEFT JOIN students s ON f.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            $where ORDER BY f.created_at DESC LIMIT $limit OFFSET $offset";

    $total = db_count("SELECT COUNT(*) FROM fees f LEFT JOIN students s ON f.student_id = s.id $where", $params);
    $fees = db_fetchAll($sql, $params);
    json_response(['data' => $fees, 'total' => (int) $total, 'pages' => ceil($total / $limit)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin', 'accounts', 'accountant']);
    $data = get_post_json();
    if (empty($data['student_id']) || empty($data['total_amount'])) {
        json_response(['error' => 'Student and amount required'], 400);
    }
    // Discount reduces what is owed (total), not what was collected
    $totalAmount = (float) $data['total_amount'];
    $discount = (float) ($data['discount'] ?? 0);
    $adjustedTotal = $totalAmount - $discount;  // Net amount owed after discount
    $amountPaid = (float) ($data['amount_paid'] ?? $adjustedTotal);
    $balanceAmount = max(0, $adjustedTotal - $amountPaid);

    try {
        $receiptNo = generate_auto_id('receipt', 'RCP');
    } catch (Throwable $e) {
        json_response(['error' => 'Could not generate unique receipt number'], 500);
    }

    $originalAmountCol = db_column_exists('fees', 'original_amount') ? ', original_amount' : '';
    $originalAmountVal = db_column_exists('fees', 'original_amount') ? ', ?' : '';
    $originalAmountParam = db_column_exists('fees', 'original_amount') ? [$totalAmount] : [];
    $discountCol = db_column_exists('fees', 'discount') ? ', discount' : '';
    $discountVal = db_column_exists('fees', 'discount') ? ', ?' : '';
    $discountParam = db_column_exists('fees', 'discount') ? [$discount] : [];

    $id = db_insert(
        "INSERT INTO fees (student_id, fee_type, total_amount$originalAmountCol, amount_paid, balance_amount, payment_method, receipt_no, paid_date, due_date, month, year, remarks, collected_by$discountCol, created_at) VALUES (?,?,?$originalAmountVal,?,?,?,?,?,?,?,?,?,?$discountVal,NOW())",
        array_merge(
            [
                (int) $data['student_id'],
                sanitize($data['fee_type'] ?? 'Tuition Fee'),
                $adjustedTotal,
            ],
            $originalAmountParam,
            [
                $amountPaid,
                $balanceAmount,
                sanitize($data['payment_method'] ?? 'cash'),
                $receiptNo,
                $data['paid_date'] ?? date('Y-m-d'),
                $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                sanitize($data['month'] ?? date('F')),
                (int) ($data['year'] ?? date('Y')),
                sanitize($data['remarks'] ?? ''),
                get_current_user_id(),
            ],
            $discountParam
        )
    );

    require_once __DIR__ . '/../../includes/notify.php';
    notify_parent_of_student((int) $data['student_id'], 'fee_new', 'New Fee Receipt', "A payment of ₹" . number_format($amountPaid, 2) . " was received for " . sanitize($data['fee_type'] ?? 'Tuition Fee') . ". Receipt No: $receiptNo", get_current_user_id(), 'fees', $id);

    json_response(['success' => true, 'id' => $id, 'receipt_no' => $receiptNo, 'balance' => $balanceAmount]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin', 'admin', 'accounts', 'accountant']);
    $data = get_post_json();
    $id = (int) ($data['id'] ?? 0);
    if (!$id)
        json_response(['error' => 'ID required'], 400);
    db_query(
        "UPDATE fees SET fee_type=?, total_amount=?, amount_paid=?, payment_method=?, remarks=? WHERE id=?",
        [
            sanitize($data['fee_type'] ?? ''),
            (float) ($data['total_amount'] ?? 0),
            (float) ($data['amount_paid'] ?? 0),
            sanitize($data['payment_method'] ?? ''),
            sanitize($data['remarks'] ?? ''),
            $id
        ]
    );
    json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin', 'accounts', 'accountant']);
    $id = (int) ($_GET['id'] ?? 0);
    $fee = db_fetch("SELECT receipt_no FROM fees WHERE id = ?", [$id]);
    if (!$fee) {
        json_response(['error' => 'Fee record not found'], 404);
    }
    // Soft-delete: preserve audit trail instead of hard delete
    if (db_column_exists('fees', 'is_deleted')) {
        db_query("UPDATE fees SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?", [get_current_user_id(), $id]);
    } else {
        db_query("DELETE FROM fees WHERE id = ?", [$id]);
    }
    audit_log('DELETE', 'fees', $id, 'Deleted fee receipt: ' . ($fee['receipt_no'] ?? 'unknown'), null);
    json_response(['success' => true, 'receipt_no' => $fee['receipt_no'] ?? null]);
}

json_response(['error' => 'Method not allowed'], 405);
