<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');
// Hostel Rooms
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rooms = db_fetchAll("SELECT r.*, COUNT(a.id) as occupants FROM hostel_rooms r LEFT JOIN hostel_allocations a ON a.room_id = r.id AND a.is_active=1 WHERE r.is_active=1 GROUP BY r.id ORDER BY r.room_number");
    json_response($rooms);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin']);
    $data = get_post_json();
    $action = $data['action'] ?? 'add_room';
    if ($action === 'add_room') {
        $id = db_insert("INSERT INTO hostel_rooms (room_number, block, floor, capacity, type, monthly_fee) VALUES (?,?,?,?,?,?)",
            [sanitize($data['room_number'] ?? ''), sanitize($data['block'] ?? ''), (int)($data['floor'] ?? 1), (int)($data['capacity'] ?? 4), sanitize($data['type'] ?? 'double'), (float)($data['monthly_fee'] ?? 0)]);
        json_response(['success' => true, 'id' => $id]);
    }
    if ($action === 'allocate') {
        $id = db_insert("INSERT INTO hostel_allocations (room_id, student_id, check_in_date, is_active) VALUES (?,?,?,1)",
            [(int)$data['room_id'], (int)$data['student_id'], $data['check_in_date'] ?? date('Y-m-d')]);
        json_response(['success' => true, 'id' => $id]);
    }
    if ($action === 'deallocate') {
        db_query("UPDATE hostel_allocations SET is_active=0, check_out_date=? WHERE id=?", [date('Y-m-d'), (int)$data['allocation_id']]);
        json_response(['success' => true]);
    }
}
json_response(['error' => 'Method not allowed'], 405);
