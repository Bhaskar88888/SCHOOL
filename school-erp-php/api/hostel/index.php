<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

// Hostel Rooms
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['allocations'])) {
        $alloc = db_fetchAll("SELECT a.*, r.room_number, r.block, s.name as student_name, c.name as class_name FROM hostel_allocations a LEFT JOIN hostel_rooms r ON a.room_id = r.id LEFT JOIN students s ON a.student_id = s.id LEFT JOIN classes c ON s.class_id = c.id WHERE a.is_active=1 ORDER BY a.check_in_date DESC");
        json_response($alloc);
    }
    $rooms = db_fetchAll("SELECT r.*, COUNT(a.id) as occupants FROM hostel_rooms r LEFT JOIN hostel_allocations a ON a.room_id = r.id AND a.is_active=1 WHERE r.is_active=1 GROUP BY r.id ORDER BY r.room_number");
    json_response($rooms);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $action = $data['action'] ?? 'add_room';
    if ($action === 'add_room') {
        $id = db_insert(
            "INSERT INTO hostel_rooms (room_number, block, floor, capacity, type, monthly_fee) VALUES (?,?,?,?,?,?)",
            [sanitize($data['room_number'] ?? ''), sanitize($data['block'] ?? ''), (int) ($data['floor'] ?? 1), (int) ($data['capacity'] ?? 4), sanitize($data['type'] ?? 'double'), (float) ($data['monthly_fee'] ?? 0)]
        );
        json_response(['success' => true, 'id' => $id]);
    }
    if ($action === 'allocate') {
        try {
            db_beginTransaction();
            $room = db_fetch("SELECT * FROM hostel_rooms WHERE id=? AND is_active=1 FOR UPDATE", [(int) $data['room_id']]);
            if (!$room) {
                db_rollback();
                json_response(['error' => 'Room not found'], 404);
            }
            $occupants = db_count("SELECT COUNT(*) FROM hostel_allocations WHERE room_id=? AND is_active=1", [(int) $data['room_id']]);
            if ($occupants >= (int) $room['capacity']) {
                db_rollback();
                json_response(['error' => 'Room is full. No available beds.'], 400);
            }
            $id = db_insert(
                "INSERT INTO hostel_allocations (room_id, student_id, check_in_date, is_active) VALUES (?,?,?,1)",
                [(int) $data['room_id'], (int) $data['student_id'], $data['check_in_date'] ?? date('Y-m-d')]
            );
            if (db_column_exists('hostel_rooms', 'occupied_beds')) {
                db_query("UPDATE hostel_rooms SET occupied_beds=occupied_beds+1 WHERE id=?", [(int) $data['room_id']]);
            }
            db_commit();
            audit_log('ALLOCATE', 'hostel', $id, null, $data);
            json_response(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            db_rollback();
            json_response(['error' => 'Allocation failed: ' . $e->getMessage()], 500);
        }
    }
    if ($action === 'deallocate') {
        $alloc = db_fetch("SELECT * FROM hostel_allocations WHERE id=?", [(int) $data['allocation_id']]);
        db_query("UPDATE hostel_allocations SET is_active=0, check_out_date=? WHERE id=?", [date('Y-m-d'), (int) $data['allocation_id']]);
        if ($alloc && db_column_exists('hostel_rooms', 'occupied_beds')) {
            db_query("UPDATE hostel_rooms SET occupied_beds=GREATEST(0, occupied_beds-1) WHERE id=?", [$alloc['room_id']]);
        }
        audit_log('DEALLOCATE', 'hostel', (int) $data['allocation_id']);
        json_response(['success' => true]);
    }
}
json_response(['error' => 'Method not allowed'], 405);
