<?php
/**
 * Canteen Enhanced API - Wallet, RFID, Sales
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Wallet balance
if (isset($_GET['action']) && $_GET['action'] === 'wallet') {
    $studentId = $_GET['student_id'] ?? null;
    if (!$studentId) {
        json_response(['error' => 'student_id required'], 400);
    }

    $student = db_fetch("SELECT id, name, canteen_balance, rfid_tag_hex FROM students WHERE id = ?", [$studentId]);
    if (!$student) {
        json_response(['error' => 'Student not found'], 404);
    }

    json_response([
        'student_id' => $student['id'],
        'name' => $student['name'],
        'balance' => $student['canteen_balance'],
        'rfid_tag' => $student['rfid_tag_hex'],
    ]);
}

// POST - Wallet topup
if (isset($_GET['action']) && $_GET['action'] === 'topup' && $method === 'POST') {
    $data = get_post_json();
    Validator::required($data, ['student_id', 'amount']);

    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }

    if ((float) $data['amount'] <= 0) {
        json_response(['error' => 'Amount must be greater than zero'], 400);
    }

    try {
        db_beginTransaction();
        db_query("UPDATE students SET canteen_balance = canteen_balance + ? WHERE id = ?", [$data['amount'], $data['student_id']]);
        $student = db_fetch("SELECT canteen_balance FROM students WHERE id = ?", [$data['student_id']]);
        db_commit();
    } catch (Exception $e) {
        db_rollback();
        json_response(['error' => 'Database error during topup'], 500);
    }
    audit_log('WALLET_TOPUP', 'canteen', $data['student_id'], null, ['amount' => $data['amount']]);

    json_response(['message' => 'Wallet topped up', 'new_balance' => $student['canteen_balance']]);
}

// POST - Assign RFID
if (isset($_GET['action']) && $_GET['action'] === 'assign-rfid' && $method === 'POST') {
    require_role(['admin', 'superadmin', 'canteen']);
    $data = get_post_json();
    Validator::required($data, ['student_id', 'rfid_tag']);

    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }

    db_query("UPDATE students SET rfid_tag_hex = ? WHERE id = ?", [$data['rfid_tag'], $data['student_id']]);
    audit_log('RFID_ASSIGN', 'canteen', $data['student_id'], null, ['rfid_tag' => $data['rfid_tag']]);
    json_response(['message' => 'RFID tag assigned']);
}

// POST - RFID Payment
if (isset($_GET['action']) && $_GET['action'] === 'rfid-pay' && $method === 'POST') {
    $data = get_post_json();
    Validator::required($data, ['rfid_tag', 'total']);

    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }

    try {
        db_beginTransaction();

        // Find student by RFID with pessimistic locking
        $student = db_fetch("SELECT id, name, canteen_balance FROM students WHERE rfid_tag_hex = ? FOR UPDATE", [$data['rfid_tag']]);
        if (!$student) {
            db_rollback();
            json_response(['error' => 'RFID tag not found'], 404);
        }

        if ($student['canteen_balance'] < $data['total']) {
            db_rollback();
            json_response(['error' => 'Insufficient balance'], 400);
        }

        // Deduct from wallet
        db_query("UPDATE students SET canteen_balance = canteen_balance - ? WHERE id = ?", [$data['total'], $student['id']]);

        // Record sale
        $sql = "INSERT INTO canteen_sales (total, payment_mode, sold_to, sold_by) VALUES (?, 'wallet', ?, ?)";
        $saleId = db_insert($sql, [$data['total'], $student['id'], get_current_user_id()]);

        // Add sale items
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                db_query(
                    "INSERT INTO canteen_sale_items (sale_id, item_id, quantity, price) VALUES (?, ?, ?, ?)",
                    [$saleId, $item['item_id'], $item['quantity'], $item['price']]
                );
                // Decrement stock
                db_query(
                    "UPDATE canteen_items SET quantity_available = GREATEST(quantity_available - ?, 0) WHERE id = ?",
                    [$item['quantity'], $item['item_id']]
                );
            }
        }

        db_commit();
    } catch (Exception $e) {
        db_rollback();
        json_response(['error' => 'Transaction failed during payment'], 500);
    }

    audit_log('RFID_PAYMENT', 'canteen', $saleId, null, ['student_id' => $student['id'], 'amount' => $data['total']]);

    json_response([
        'success' => true,
        'sale_id' => $saleId,
        'student_name' => $student['name'],
        'amount' => $data['total'],
        'new_balance' => $student['canteen_balance'] - $data['total'],
    ]);
}

// POST - Atomic Sell with stock decrement
if (isset($_GET['action']) && $_GET['action'] === 'sell' && $method === 'POST') {
    require_role(['superadmin', 'admin', 'canteen']);
    $data = get_post_json();
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $total = (float) ($data['total'] ?? 0);
    $soldTo = sanitize($data['sold_to'] ?? '');
    $paymentMode = sanitize($data['payment_mode'] ?? 'cash');
    if (empty($items))
        json_response(['error' => 'items required'], 400);

    try {
        db_beginTransaction();
        foreach ($items as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            if ($itemId) {
                $rows = db_query("UPDATE canteen_items SET quantity_available=quantity_available-? WHERE id=? AND quantity_available>=?", [$qty, $itemId, $qty]);
                if ($rows === 0)
                    throw new Exception('INSUFFICIENT_STOCK');
            }
        }
        $saleId = db_insert(
            "INSERT INTO canteen_sales (total_amount, sold_to, sold_by, payment_mode, created_at) VALUES (?,?,?,?,NOW())",
            [$total, $soldTo, get_current_user_id(), $paymentMode]
        );
        foreach ($items as $item) {
            db_insert(
                "INSERT INTO canteen_sale_items (sale_id, item_id, quantity, price) VALUES (?,?,?,?)",
                [$saleId, $item['item_id'] ?? null, (int) ($item['quantity'] ?? 0), (float) ($item['price'] ?? 0)]
            );
        }
        db_commit();
        json_response(['success' => true, 'sale_id' => $saleId], 201);
    } catch (Exception $e) {
        db_rollback();
        if ($e->getMessage() === 'INSUFFICIENT_STOCK')
            json_response(['error' => 'Insufficient stock'], 400);
        json_response(['error' => $e->getMessage()], 500);
    }
}

// PUT - Restock item
if ($method === 'PUT' && preg_match('/\/(\d+)\/restock$/', $_SERVER['REQUEST_URI'], $matches)) {
    require_role(['admin', 'superadmin', 'canteen']);
    $itemId = $matches[1];
    $data = get_post_json();

    if (empty($data['quantity'])) {
        json_response(['error' => 'quantity required'], 400);
    }

    db_query("UPDATE canteen_items SET quantity_available = quantity_available + ?, is_available = 1 WHERE id = ?", [$data['quantity'], $itemId]);

    json_response(['message' => 'Item restocked']);
}

// Regular canteen API continues...
// GET - List sales
if (isset($_GET['action']) && $_GET['action'] === 'sales') {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');

    $sales = db_fetchAll("SELECT cs.*, u1.name as student_name, u2.name as sold_by_name 
                          FROM canteen_sales cs 
                          LEFT JOIN students u1 ON cs.sold_to = u1.id 
                          LEFT JOIN users u2 ON cs.sold_by = u2.id 
                          WHERE cs.sale_date BETWEEN ? AND ? 
                          ORDER BY cs.sale_date DESC", [$dateFrom, $dateTo]);

    json_response(['sales' => $sales]);
}

// File ends here
