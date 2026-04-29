<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_role(['superadmin', 'admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['fees'])) {
        $fees = db_fetchAll(
            "SELECT hfs.*, hrt.name AS room_type_name
             FROM hostel_fee_structures hfs
             LEFT JOIN hostel_room_types hrt ON hfs.room_type_id = hrt.id
             ORDER BY hfs.academic_year DESC, hrt.name ASC"
        );
        json_response(['fees' => $fees]);
    }
    $types = db_fetchAll("SELECT * FROM hostel_room_types ORDER BY name ASC");
    json_response(['room_types' => $types]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = get_post_json();
    $action = $data['action'] ?? '';

    if ($action === 'create_room_type') {
        $name = sanitize($data['name'] ?? '');
        if (!$name) json_response(['error' => 'Name is required'], 400);
        $id = db_insert(
            "INSERT INTO hostel_room_types (name, occupancy, gender_policy, default_fee, amenities) VALUES (?,?,?,?,?)",
            [
                $name,
                (int) ($data['occupancy'] ?? 2),
                sanitize($data['gender_policy'] ?? 'separate'),
                (float) ($data['default_fee'] ?? 0),
                sanitize($data['amenities'] ?? '[]'),
            ]
        );
        json_response(['success' => true, 'id' => $id], 201);
    }

    if ($action === 'create_fee_structure') {
        $roomTypeId = (int) ($data['room_type_id'] ?? 0);
        $amount     = (float) ($data['amount'] ?? 0);
        if (!$roomTypeId || $amount <= 0) json_response(['error' => 'room_type_id and amount required'], 400);
        $id = db_insert(
            "INSERT INTO hostel_fee_structures (room_type_id, academic_year, billing_cycle, amount, mess_charge, caution_deposit) VALUES (?,?,?,?,?,?)",
            [
                $roomTypeId,
                sanitize($data['academic_year'] ?? date('Y') . '-' . (date('Y') + 1)),
                sanitize($data['billing_cycle'] ?? 'Monthly'),
                $amount,
                (float) ($data['mess_charge'] ?? 0),
                (float) ($data['caution_deposit'] ?? 0),
            ]
        );
        json_response(['success' => true, 'id' => $id], 201);
    }

    json_response(['error' => 'Unknown action'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $type = sanitize($_GET['type'] ?? '');
    $id   = (int) ($_GET['id'] ?? 0);
    if (!$id) json_response(['error' => 'ID required'], 400);

    if ($type === 'room_type') {
        db_query("DELETE FROM hostel_fee_structures WHERE room_type_id = ?", [$id]);
        db_query("DELETE FROM hostel_room_types WHERE id = ?", [$id]);
    } elseif ($type === 'fee_structure') {
        db_query("DELETE FROM hostel_fee_structures WHERE id = ?", [$id]);
    } else {
        json_response(['error' => 'Unknown type'], 400);
    }
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
