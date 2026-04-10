<?php
/**
 * Transport Enhanced API - Student assignment, attendance history
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms_service.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Transport attendance history for student
if (preg_match('/\/student\/(\d+)\/history$/', $_SERVER['REQUEST_URI'], $matches)) {
    $studentId = $matches[1];
    $sql = "SELECT ta.*, v.bus_number, v.vehicle_no
            FROM transport_attendance ta 
            LEFT JOIN transport_vehicles v ON ta.bus_id = v.id 
            WHERE ta.student_id = ? 
            ORDER BY ta.date DESC 
            LIMIT 100";
    $history = db_fetchAll($sql, [$studentId]);
    json_response(['history' => $history]);
}

// GET - Transport attendance by bus
if (preg_match('/\/(\d+)\/attendance$/', $_SERVER['REQUEST_URI'], $matches)) {
    $busId = $matches[1];
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $attendance = db_fetchAll(
        "SELECT ta.*, s.name as student_name, s.admission_no 
         FROM transport_attendance ta 
         LEFT JOIN students s ON ta.student_id = s.id 
         WHERE ta.bus_id = ? AND ta.date = ? 
         ORDER BY s.name",
        [$busId, $date]
    );
    
    json_response(['attendance' => $attendance, 'date' => $date]);
}

// PUT - Assign students to bus
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'assign-students') {
    require_role(['admin', 'superadmin']);
    $data = get_post_json();
    $busId = $data['bus_id'] ?? null;
    $studentIds = $data['student_ids'] ?? [];
    
    if (!$busId || empty($studentIds)) {
        json_response(['error' => 'bus_id and student_ids required'], 400);
    }
    
    $updated = 0;
    foreach ($studentIds as $studentId) {
        db_query("UPDATE students SET transport_required = 1, user_id = (SELECT id FROM transport_vehicles WHERE id = ? LIMIT 1) WHERE id = ?", [$busId, $studentId]);
        $updated++;
    }
    
    json_response(['success' => true, 'updated' => $updated]);
}

// POST - Transport attendance with SMS
if ($method === 'POST' && !isset($_GET['action'])) {
    $data = get_post_json();
    $busId = $data['bus_id'] ?? null;
    $records = $data['records'] ?? [];
    $date = $data['date'] ?? date('Y-m-d');
    $sendSMS = $data['send_sms'] ?? true;
    
    if (empty($records)) {
        json_response(['error' => 'Records required'], 400);
    }
    
    $saved = 0;
    $smsCount = 0;
    $smsService = SMSService::getInstance();
    
    foreach ($records as $rec) {
        $studentId = (int)$rec['student_id'];
        $status = in_array($rec['status'], ['boarded', 'not_boarded', 'dropped', 'not_dropped']) ? $rec['status'] : 'boarded';
        $markedBy = get_current_user_id();
        
        db_query("INSERT INTO transport_attendance (bus_id, student_id, date, status, marked_by_id) 
                  VALUES (?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by_id = VALUES(marked_by_id)",
            [$busId, $studentId, $date, $status, $markedBy]);
        
        // Send SMS when student boards
        if ($status === 'boarded' && $sendSMS) {
            $student = db_fetch("SELECT name, parent_phone FROM students WHERE id = ? AND parent_phone IS NOT NULL", [$studentId]);
            if ($student) {
                $vehicle = db_fetch("SELECT vehicle_no FROM transport_vehicles WHERE id = ?", [$busId]);
                $result = $smsService->transportBoarding($student['name'], $student['parent_phone'], $vehicle['vehicle_no'] ?? 'N/A');
                if ($result['success']) $smsCount++;
            }
        }
        
        $saved++;
    }
    
    json_response(['success' => true, 'saved' => $saved, 'sms_sent' => $smsCount]);
}

json_response(['message' => 'Transport enhanced API']);
