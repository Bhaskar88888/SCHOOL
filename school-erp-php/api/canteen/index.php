<?php
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

ensure_canteen_schema();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['stats'])) {
        $table = canteen_sales_table();
        $dateColumn = canteen_sales_date_column($table);
        $amountColumn = canteen_sales_amount_column($table);
        $today = date('Y-m-d');
        $revenue = db_fetch(
            "SELECT COALESCE(SUM($amountColumn), 0) AS today_revenue FROM $table WHERE DATE($dateColumn) = ?",
            [$today]
        );
        json_response(['today_revenue' => (float) ($revenue['today_revenue'] ?? 0)]);
    }

    if (isset($_GET['sales'])) {
        $table = canteen_sales_table();
        $dateColumn = canteen_sales_date_column($table);
        $amountColumn = canteen_sales_amount_column($table);
        $buyerColumn = canteen_sales_buyer_column($table);
        $date = sanitize($_GET['date'] ?? date('Y-m-d'));

        if (canteen_sales_use_line_items($table)) {
            $sales = db_fetchAll(
                "SELECT s.id,
                        s.$amountColumn AS total_price,
                        s.$dateColumn AS order_date,
                        u.name AS buyer_name,
                        COALESCE(SUM(si.quantity), 0) AS quantity,
                        COALESCE(GROUP_CONCAT(i.name SEPARATOR ', '), 'Sale') AS item_name
                 FROM $table s
                 LEFT JOIN canteen_sale_items si ON si.sale_id = s.id
                 LEFT JOIN canteen_items i ON si.item_id = i.id
                 LEFT JOIN users u ON " . ($buyerColumn ? "s.$buyerColumn" : 'NULL') . " = u.id
                 WHERE DATE(s.$dateColumn) = ?
                 GROUP BY s.id, s.$amountColumn, s.$dateColumn, u.name
                 ORDER BY s.$dateColumn DESC",
                [$date]
            );
        } else {
            $sales = db_fetchAll(
                "SELECT s.id,
                        s.$amountColumn AS total_price,
                        s.quantity,
                        s.$dateColumn AS order_date,
                        i.name AS item_name,
                        u.name AS buyer_name
                 FROM $table s
                 LEFT JOIN canteen_items i ON s.item_id = i.id
                 LEFT JOIN users u ON " . ($buyerColumn ? "s.$buyerColumn" : 'NULL') . " = u.id
                 WHERE DATE(s.$dateColumn) = ?
                 ORDER BY s.$dateColumn DESC",
                [$date]
            );
        }

        json_response($sales);
    }

    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $items = db_fetchAll("SELECT * FROM canteen_items WHERE name LIKE ? ORDER BY name", [$search]);
    json_response($items);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $id = db_insert(
        "INSERT INTO canteen_items (name, category, price, is_available, available_qty) VALUES (?,?,?, 1, ?) ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price), available_qty=VALUES(available_qty)",
        [sanitize($data['name']), sanitize($data['category'] ?? 'General'), (float) $data['price'], (int) ($data['available_qty'] ?? 100)]
    );
    json_response(['success' => true, 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (isset($_GET['action']) && $_GET['action'] === 'update') {
        require_role(['superadmin', 'admin']);
        $data = get_post_json();
        $id = (int) $_GET['id'];
        $updates = [];
        $params = [];
        foreach (['name', 'category', 'price', 'is_available', 'available_qty'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = is_string($data[$field]) ? sanitize($data[$field]) : $data[$field];
            }
        }
        if (!empty($updates)) {
            $params[] = $id;
            db_query("UPDATE canteen_items SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            json_response(['success' => true]);
        }
        json_response(['error' => 'No data to update'], 400);
    }

    $data = get_post_json();
    $itemId = (int) ($data['item_id'] ?? 0);
    $qty = (int) ($data['quantity'] ?? 0);
    $item = db_fetch("SELECT * FROM canteen_items WHERE id = ?", [$itemId]);
    if (!$item || !$item['is_available']) {
        json_response(['error' => 'Item not available'], 400);
    }
    if ((int) $item['available_qty'] < $qty) {
        json_response(['error' => 'Not enough stock'], 400);
    }

    $table = canteen_sales_table();
    $amountColumn = canteen_sales_amount_column($table);
    $buyerColumn = canteen_sales_buyer_column($table);
    $sellerColumn = canteen_sales_seller_column($table);
    $statusColumn = db_column_exists($table, 'status') ? 'status' : null;
    $total = (float) $item['price'] * $qty;

    try {
        db_beginTransaction();

        if (canteen_sales_use_line_items($table)) {
            $salePayload = [
                $amountColumn => $total,
            ];
            if (db_column_exists($table, 'payment_mode')) {
                $salePayload['payment_mode'] = 'cash';
            }
            if ($buyerColumn) {
                $salePayload[$buyerColumn] = get_current_user_id();
            }
            if ($sellerColumn) {
                $salePayload[$sellerColumn] = get_current_user_id();
            }
            $saleId = insert_canteen_sale_row($table, $salePayload);
            db_query(
                "INSERT INTO canteen_sale_items (sale_id, item_id, quantity, price) VALUES (?, ?, ?, ?)",
                [$saleId, $itemId, $qty, (float) $item['price']]
            );
        } else {
            $salePayload = [
                'item_id' => $itemId,
                'quantity' => $qty,
                $amountColumn => $total,
            ];
            if ($buyerColumn) {
                $salePayload[$buyerColumn] = get_current_user_id();
            }
            if ($sellerColumn) {
                $salePayload[$sellerColumn] = get_current_user_id();
            }
            if ($statusColumn) {
                $salePayload[$statusColumn] = 'delivered';
            }
            $saleId = insert_canteen_sale_row($table, $salePayload);
        }

        db_query("UPDATE canteen_items SET available_qty = available_qty - ? WHERE id = ?", [$qty, $itemId]);
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        json_response(['error' => 'Unable to record canteen sale'], 500);
    }

    json_response(['success' => true, 'order_id' => $saleId, 'total' => $total]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("DELETE FROM canteen_items WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);

function canteen_sales_table()
{
    if (db_table_exists('canteen_sales')) {
        return 'canteen_sales';
    }

    return 'canteen_orders';
}

function canteen_sales_date_column($table)
{
    foreach (['sale_date', 'order_date', 'created_at'] as $column) {
        if (db_column_exists($table, $column)) {
            return $column;
        }
    }

    return 'created_at';
}

function canteen_sales_amount_column($table)
{
    if (db_column_exists($table, 'total_price')) {
        return 'total_price';
    }

    if (db_column_exists($table, 'total_amount')) {
        return 'total_amount';
    }

    return 'total_price';
}

function canteen_sales_buyer_column($table)
{
    foreach (['ordered_by', 'sold_to'] as $column) {
        if (db_column_exists($table, $column)) {
            return $column;
        }
    }

    return null;
}

function canteen_sales_seller_column($table)
{
    return db_column_exists($table, 'sold_by') ? 'sold_by' : null;
}

function canteen_sales_use_line_items($table)
{
    return !db_column_exists($table, 'item_id') && db_table_exists('canteen_sale_items');
}

function insert_canteen_sale_row($table, array $payload)
{
    $payload = db_filter_data_for_table($table, $payload);
    $columns = array_keys($payload);
    $placeholders = array_fill(0, count($columns), '?');
    $params = array_values($payload);

    if (db_column_exists($table, 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }

    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    return db_insert($sql, $params);
}

function ensure_canteen_schema()
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    if (!db_table_exists('canteen_sales') && !db_table_exists('canteen_orders')) {
        db_query(
            "CREATE TABLE IF NOT EXISTS canteen_sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT DEFAULT NULL,
                quantity INT DEFAULT 1,
                total_price DECIMAL(10,2) DEFAULT 0,
                ordered_by INT DEFAULT NULL,
                sold_to INT DEFAULT NULL,
                sold_by INT DEFAULT NULL,
                status VARCHAR(50) DEFAULT 'delivered',
                payment_mode VARCHAR(50) DEFAULT 'cash',
                order_date DATETIME DEFAULT NOW(),
                sale_date DATETIME DEFAULT NOW(),
                created_at DATETIME DEFAULT NOW()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    if (!db_table_exists('canteen_sale_items')) {
        db_query(
            "CREATE TABLE IF NOT EXISTS canteen_sale_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NOT NULL,
                item_id INT DEFAULT NULL,
                quantity INT DEFAULT 1,
                price DECIMAL(10,2) DEFAULT 0,
                created_at DATETIME DEFAULT NOW()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    $ensured = true;
}
