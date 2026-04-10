<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $status = sanitize($_GET['status'] ?? '');

    $where = "WHERE (s.name LIKE ? OR f.receipt_no LIKE ?)";
    $params = [$search, $search];
    if ($status) { $where .= " AND balance_status = ?"; $params[] = $status; }

    $sql = "SELECT f.*, s.name as student_name, c.name as class_name,
                   CASE WHEN f.balance_amount <= 0 THEN 'paid' ELSE 'pending' END as balance_status
            FROM fees f
            LEFT JOIN students s ON f.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            $where ORDER BY f.created_at DESC LIMIT $limit OFFSET $offset";

    $total = db_count("SELECT COUNT(*) FROM fees f LEFT JOIN students s ON f.student_id = s.id $where", $params);
    $fees  = db_fetchAll($sql, $params);
    json_response(['data' => $fees, 'total' => (int)$total, 'pages' => ceil($total/$limit)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin','accountant']);
    $data = get_post_json();
    if (empty($data['student_id']) || empty($data['total_amount'])) {
        json_response(['error' => 'Student and amount required'], 400);
    }
    $receiptNo = 'RCP-' . date('Ymd') . '-' . rand(1000,9999);
    $id = db_insert("INSERT INTO fees (student_id, fee_type, total_amount, amount_paid, payment_method, receipt_no, paid_date, due_date, month, year, remarks, collected_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
        [(int)$data['student_id'], sanitize($data['fee_type'] ?? 'Tuition Fee'), (float)$data['total_amount'],
         (float)($data['amount_paid'] ?? $data['total_amount']), sanitize($data['payment_method'] ?? 'cash'),
         $receiptNo, $data['paid_date'] ?? date('Y-m-d'), $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
         sanitize($data['month'] ?? date('F')), (int)($data['year'] ?? date('Y')),
         sanitize($data['remarks'] ?? ''), get_current_user_id()]);
    json_response(['success' => true, 'id' => $id, 'receipt_no' => $receiptNo]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin','admin']);
    $id = (int)($_GET['id'] ?? 0);
    db_query("DELETE FROM fees WHERE id = ?", [$id]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
