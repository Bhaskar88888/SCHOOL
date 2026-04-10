<?php
/**
 * Hostel Enhanced API - Dashboard, Room Types, Fee Structures, Allocations
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Hostel endpoints
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // Dashboard
    if ($action === 'dashboard') {
        $stats = db_fetch("SELECT 
            (SELECT COUNT(*) FROM students WHERE hostel_required = 1 AND is_active = 1) as hostel_students,
            (SELECT COUNT(*) FROM hostel_room_types) as room_types,
            (SELECT COUNT(*) FROM hostel_rooms WHERE status = 'available') as available_rooms,
            (SELECT COUNT(*) FROM hostel_allocations WHERE status = 'ACTIVE') as active_allocations,
            (SELECT COUNT(*) FROM hostel_fee_structures) as fee_structures");
        json_response($stats);
    }
    
    // Room types
    if ($action === 'room-types') {
        $roomTypes = db_fetchAll("SELECT * FROM hostel_room_types ORDER BY name");
        json_response(['roomTypes' => $roomTypes]);
    }
    
    // Fee structures
    if ($action === 'fee-structures') {
        $feeStructures = db_fetchAll("SELECT hfs.*, hrt.name as room_type_name 
                                      FROM hostel_fee_structures hfs 
                                      LEFT JOIN hostel_room_types hrt ON hfs.room_type_id = hrt.id 
                                      ORDER BY hfs.academic_year, hrt.name");
        json_response(['feeStructures' => $feeStructures]);
    }
    
    // Allocations
    if ($action === 'allocations') {
        $allocations = db_fetchAll("SELECT ha.*, s.name as student_name, s.admission_no, 
                                           hr.room_number, hr.block, hrt.name as room_type_name
                                    FROM hostel_allocations ha 
                                    LEFT JOIN students s ON ha.student_id = s.id 
                                    LEFT JOIN hostel_rooms hr ON ha.room_id = hr.id 
                                    LEFT JOIN hostel_room_types hrt ON ha.room_type_id = hrt.id 
                                    WHERE ha.status = 'ACTIVE' 
                                    ORDER BY ha.allotment_date DESC");
        json_response(['allocations' => $allocations]);
    }
    
    json_response(['message' => 'Use ?action=dashboard|room-types|fee-structures|allocations']);
}

// POST - Create hostel entities
if ($method === 'POST') {
    require_role(['admin', 'superadmin']);
    $data = get_post_json();
    $action = $data['action'] ?? '';
    
    // Create room type
    if ($action === 'create_room_type') {
        Validator::required($data, ['name', 'occupancy']);
        if (Validator::hasErrors()) {
            json_response(['errors' => Validator::errors()], 422);
        }
        
        $sql = "INSERT INTO hostel_room_types (name, occupancy, gender_policy, default_fee, amenities) VALUES (?, ?, ?, ?, ?)";
        $id = db_insert($sql, [$data['name'], $data['occupancy'], $data['gender_policy'] ?? 'co-ed', $data['default_fee'] ?? 0, json_encode($data['amenities'] ?? [])]);
        
        audit_log('CREATE', 'hostel_room_type', $id, null, $data);
        json_response(['message' => 'Room type created', 'id' => $id], 201);
    }
    
    // Create fee structure
    if ($action === 'create_fee_structure') {
        Validator::required($data, ['room_type_id', 'amount']);
        if (Validator::hasErrors()) {
            json_response(['errors' => Validator::errors()], 422);
        }
        
        $sql = "INSERT INTO hostel_fee_structures (room_type_id, academic_year, term, billing_cycle, amount, caution_deposit, mess_charge) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $id = db_insert($sql, [$data['room_type_id'], $data['academic_year'] ?? '2025-2026', $data['term'] ?? 'Annual', $data['billing_cycle'] ?? 'Monthly', $data['amount'], $data['caution_deposit'] ?? 0, $data['mess_charge'] ?? 0]);
        
        audit_log('CREATE', 'hostel_fee_structure', $id, null, $data);
        json_response(['message' => 'Fee structure created', 'id' => $id], 201);
    }
    
    // Allocate student
    if ($action === 'allocate') {
        Validator::required($data, ['student_id', 'room_type_id', 'room_id']);
        if (Validator::hasErrors()) {
            json_response(['errors' => Validator::errors()], 422);
        }
        
        // Check room capacity
        $room = db_fetch("SELECT capacity, occupied_beds FROM hostel_rooms WHERE id = ?", [$data['room_id']]);
        if ($room && $room['occupied_beds'] >= $room['capacity']) {
            json_response(['error' => 'Room is full'], 400);
        }
        
        $sql = "INSERT INTO hostel_allocations (student_id, room_type_id, room_id, fee_structure_id, academic_year, bed_label, allotment_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'ACTIVE')";
        $id = db_insert($sql, [$data['student_id'], $data['room_type_id'], $data['room_id'], $data['fee_structure_id'] ?? null, $data['academic_year'] ?? '2025-2026', $data['bed_label'] ?? 'A']);
        
        // Update room occupancy
        db_query("UPDATE hostel_rooms SET occupied_beds = occupied_beds + 1, status = CASE WHEN occupied_beds + 1 >= capacity THEN 'occupied' ELSE status END WHERE id = ?", [$data['room_id']]);
        
        // Update student
        db_query("UPDATE students SET hostel_required = 1 WHERE id = ?", [$data['student_id']]);
        
        audit_log('CREATE', 'hostel_allocation', $id, null, $data);
        json_response(['message' => 'Student allocated to room', 'id' => $id], 201);
    }
    
    json_response(['error' => 'Invalid action'], 400);
}

// PATCH - Vacate student
if ($method === 'PATCH' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'vacate')) {
    require_role(['admin', 'superadmin']);
    $data = get_post_json();
    $id = $data['allocation_id'] ?? null;
    
    if (!$id) {
        json_response(['error' => 'allocation_id required'], 400);
    }
    
    $allocation = db_fetch("SELECT * FROM hostel_allocations WHERE id = ? AND status = 'ACTIVE'", [$id]);
    if (!$allocation) {
        json_response(['error' => 'Allocation not found'], 404);
    }
    
    db_query("UPDATE hostel_allocations SET status = 'VACATED', vacated_on = NOW() WHERE id = ?", [$id]);
    
    // Update room occupancy
    db_query("UPDATE hostel_rooms SET occupied_beds = GREATEST(occupied_beds - 1, 0), status = 'available' WHERE id = ? AND occupied_beds > 0", [$allocation['room_id']]);
    
    audit_log('VACATE', 'hostel_allocation', $id, $allocation, null);
    json_response(['message' => 'Student vacated successfully']);
}
