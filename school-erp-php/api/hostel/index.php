<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/hostel_compat.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

// Hostel Rooms
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $roomTypeJoin = hostel_room_type_join('r', 'hrt');
    $allocationActive = hostel_allocation_active_condition('a');
    $roomActive = hostel_room_active_condition('r');
    $roomNumberExpr = hostel_room_number_expr('r');
    $roomBlockExpr = hostel_room_block_expr('r');
    $roomTypeExpr = hostel_room_type_expr('r', 'hrt');
    $roomFeeExpr = hostel_room_fee_expr('r');
    $checkInExpr = hostel_allocation_start_expr('a');

    if (isset($_GET['allocations'])) {
        $alloc = db_fetchAll(
            "SELECT a.*,
                    $roomNumberExpr AS room_number,
                    $roomBlockExpr AS block,
                    $checkInExpr AS check_in_date,
                    s.name AS student_name,
                    c.name AS class_name
             FROM hostel_allocations a
             LEFT JOIN hostel_rooms r ON a.room_id = r.id
             LEFT JOIN students s ON a.student_id = s.id
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE $allocationActive
             ORDER BY $checkInExpr DESC"
        );
        json_response($alloc);
    }

    $rooms = db_fetchAll(
        "SELECT r.*,
                $roomNumberExpr AS room_number,
                $roomBlockExpr AS block,
                $roomTypeExpr AS type,
                $roomFeeExpr AS monthly_fee,
                COUNT(a.id) AS occupants
         FROM hostel_rooms r
         $roomTypeJoin
         LEFT JOIN hostel_allocations a ON a.room_id = r.id AND $allocationActive
         WHERE $roomActive
         GROUP BY r.id
         ORDER BY room_number"
    );
    json_response($rooms);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }
    $action = $data['action'] ?? 'add_room';
    if ($action === 'add_room') {
        if (empty(trim((string) ($data['room_number'] ?? '')))) {
            json_response(['error' => 'Room number is required'], 422);
        }

        $id = hostel_insert_row('hostel_rooms', hostel_room_payload($data));
        json_response(['success' => true, 'id' => $id]);
    }
    if ($action === 'allocate') {
        try {
            db_beginTransaction();
            $roomWhere = hostel_room_active_condition('hostel_rooms');
            $room = db_fetch("SELECT * FROM hostel_rooms WHERE id = ? AND $roomWhere FOR UPDATE", [(int) $data['room_id']]);
            if (!$room) {
                db_rollback();
                json_response(['error' => 'Room not found'], 404);
            }

            $occupants = db_count(
                "SELECT COUNT(*) FROM hostel_allocations WHERE room_id = ? AND " . hostel_allocation_active_condition('hostel_allocations'),
                [(int) $data['room_id']]
            );
            if ($occupants >= (int) $room['capacity']) {
                db_rollback();
                json_response(['error' => 'Room is full. No available beds.'], 400);
            }

            $id = hostel_insert_row('hostel_allocations', hostel_allocation_payload($data, $room));
            if (db_column_exists('hostel_rooms', 'occupied_beds')) {
                if (db_column_exists('hostel_rooms', 'status')) {
                    db_query(
                        "UPDATE hostel_rooms
                         SET occupied_beds = occupied_beds + 1,
                             status = CASE WHEN occupied_beds + 1 >= capacity THEN 'occupied' ELSE 'available' END
                         WHERE id = ?",
                        [(int) $data['room_id']]
                    );
                } else {
                    db_query("UPDATE hostel_rooms SET occupied_beds = occupied_beds + 1 WHERE id = ?", [(int) $data['room_id']]);
                }
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
        hostel_update_row('hostel_allocations', hostel_vacate_payload(), 'id = ?', [(int) $data['allocation_id']]);
        if ($alloc && db_column_exists('hostel_rooms', 'occupied_beds')) {
            if (db_column_exists('hostel_rooms', 'status')) {
                db_query(
                    "UPDATE hostel_rooms
                     SET occupied_beds = GREATEST(0, occupied_beds - 1),
                         status = 'available'
                     WHERE id = ?",
                    [$alloc['room_id']]
                );
            } else {
                db_query("UPDATE hostel_rooms SET occupied_beds = GREATEST(0, occupied_beds - 1) WHERE id = ?", [$alloc['room_id']]);
            }
        }
        audit_log('DEALLOCATE', 'hostel', (int) $data['allocation_id']);
        json_response(['success' => true]);
    }
}
json_response(['error' => 'Method not allowed'], 405);
