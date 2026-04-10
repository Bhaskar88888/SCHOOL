<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

// Get Canteen Items
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $items = db_fetchAll("SELECT * FROM canteen_items WHERE name LIKE ? ORDER BY name", [$search]);
    json_response($items);
}

// Add/Edit Item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $id = db_insert(
        "INSERT INTO canteen_items (name, category, price, is_available) VALUES (?,?,?, 1) ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price)",
        [sanitize($data['name']), sanitize($data['category'] ?? 'General'), (float) $data['price']]
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
        foreach (['name', 'category', 'price', 'is_available'] as $f) {
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

    $total = $item['price'] * $qty;
    $orderId = db_insert(
        "INSERT INTO canteen_orders (item_id, quantity, total_price, ordered_by, status) VALUES (?,?,?,?, 'delivered')",
        [$itemId, $qty, $total, get_current_user_id()]
    );

    json_response(['success' => true, 'order_id' => $orderId, 'total' => $total]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    db_query("DELETE FROM canteen_items WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
