<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

// Get Canteen Items
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['stats'])) {
        $today = date('Y-m-d');
        $rev = db_fetch("SELECT SUM(total_price) as today_revenue FROM canteen_orders WHERE DATE(order_date) = ?", [$today]);
        json_response(['today_revenue' => (float) ($rev['today_revenue'] ?? 0)]);
    }
    if (isset($_GET['sales'])) {
        $date = sanitize($_GET['date'] ?? date('Y-m-d'));
        $orders = db_fetchAll("SELECT o.*, i.name as item_name, u.name as buyer_name 
                               FROM canteen_orders o 
                               LEFT JOIN canteen_items i ON o.item_id = i.id 
                               LEFT JOIN users u ON o.ordered_by = u.id 
                               WHERE DATE(o.order_date) = ? 
                               ORDER BY o.order_date DESC", [$date]);
        json_response($orders);
    }

    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $items = db_fetchAll("SELECT * FROM canteen_items WHERE name LIKE ? ORDER BY name", [$search]);
    json_response($items);
}

// Add/Edit Item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $id = db_insert(
        "INSERT INTO canteen_items (name, category, price, is_available, available_qty) VALUES (?,?,?, 1, ?) ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price), available_qty=VALUES(available_qty)",
        [sanitize($data['name']), sanitize($data['category'] ?? 'General'), (float) $data['price'], (int) ($data['available_qty'] ?? 100)]
    );
    json_response(['success' => true, 'id' => $id]);
}

// Record Order or Update Item
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update item if action specified
    if (isset($_GET['action']) && $_GET['action'] === 'update') {
        require_role(['superadmin', 'admin']);
        $data = get_post_json();
        $id = (int) $_GET['id'];
        $updates = [];
        $params = [];
        foreach (['name', 'category', 'price', 'is_available', 'available_qty'] as $f) {
            if (isset($data[$f])) {
                $updates[] = "$f = ?";
                $params[] = is_string($data[$f]) ? sanitize($data[$f]) : $data[$f];
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
    $itemId = (int) $data['item_id'];
    $qty = (int) $data['quantity'];
    $item = db_fetch("SELECT * FROM canteen_items WHERE id = ?", [$itemId]);
    if (!$item || !$item['is_available']) {
        json_response(['error' => 'Item not available'], 400);
    }
    if ((int) $item['available_qty'] < $qty) {
        json_response(['error' => 'Not enough stock'], 400);
    }

    $total = $item['price'] * $qty;
    $orderId = db_insert(
        "INSERT INTO canteen_orders (item_id, quantity, total_price, ordered_by, status) VALUES (?,?,?,?, 'delivered')",
        [$itemId, $qty, $total, get_current_user_id()]
    );

    // Decrease stock
    db_query("UPDATE canteen_items SET available_qty = available_qty - ? WHERE id = ?", [$qty, $itemId]);

    json_response(['success' => true, 'order_id' => $orderId, 'total' => $total]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("DELETE FROM canteen_items WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
