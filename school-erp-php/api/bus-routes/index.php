<?php
/**
 * Bus Routes API - Detailed route management with stops
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// GET - List routes or single route
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        // Single route with stops
        $route = db_fetch("SELECT br.*, v.vehicle_no, v.driver_name, u1.name as driver_name, u2.name as conductor_name
                          FROM bus_routes br 
                          LEFT JOIN transport_vehicles v ON br.vehicle_id = v.id 
                          LEFT JOIN users u1 ON v.driver_id = u1.id 
                          LEFT JOIN users u2 ON v.conductor_id = u2.id 
                          WHERE br.id = ?", [$id]);
        
        if (!$route) {
            json_response(['error' => 'Route not found'], 404);
        }
        
        $stops = db_fetchAll("SELECT * FROM bus_stops WHERE route_id = ? ORDER BY `sequence`", [$id]);
        $route['stops'] = $stops;
        
        json_response(['route' => $route]);
    }
    
    // Stats summary
    if (isset($_GET['stats'])) {
        $stats = db_fetch("SELECT 
            COUNT(*) as total_routes,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_routes,
            SUM(total_distance) as total_distance,
            (SELECT COUNT(*) FROM bus_stops) as total_stops
            FROM bus_routes");
        json_response($stats);
    }
    
    // List routes
    $where = ['1=1'];
    $params = [];
    
    if (isset($_GET['active'])) {
        $where[] = 'br.is_active = ?';
        $params[] = (int)$_GET['active'];
    }
    
    $whereClause = implode(' AND ', $where);
    $sql = "SELECT br.*, v.vehicle_no, v.driver_name, u1.name as driver_name_full, u2.name as conductor_name
            FROM bus_routes br 
            LEFT JOIN transport_vehicles v ON br.vehicle_id = v.id 
            LEFT JOIN users u1 ON v.driver_id = u1.id 
            LEFT JOIN users u2 ON v.conductor_id = u2.id 
            WHERE $whereClause 
            ORDER BY br.route_name 
            LIMIT $limit OFFSET $offset";
    
    $countSql = "SELECT COUNT(*) FROM bus_routes br WHERE $whereClause";
    
    $routes = db_fetchAll($sql, $params);
    $total = db_count($countSql, $params);
    
    json_response([
        'routes' => $routes,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ]
    ]);
}

// POST - Create route
if ($method === 'POST') {
    require_role(['admin', 'superadmin']);
    
    $data = get_post_json();
    Validator::required($data, ['route_name', 'vehicle_id']);
    
    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }
    
    $sql = "INSERT INTO bus_routes (route_name, route_code, vehicle_id, driver_id, conductor_id, monthly_fee, total_distance, capacity, is_active, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $data['route_name'],
        $data['route_code'] ?? strtoupper(substr($data['route_name'], 0, 3)) . date('Y'),
        $data['vehicle_id'],
        $data['driver_id'] ?? null,
        $data['conductor_id'] ?? null,
        $data['monthly_fee'] ?? 0,
        $data['total_distance'] ?? 0,
        $data['capacity'] ?? 50,
        $data['is_active'] ?? 1,
        $data['description'] ?? null,
    ];
    
    $routeId = db_insert($sql, $params);
    
    // Add stops if provided
    if (!empty($data['stops']) && is_array($data['stops'])) {
        foreach ($data['stops'] as $seq => $stop) {
            db_query(
                "INSERT INTO bus_stops (route_id, stop_name, sequence, arrival_time, departure_time, latitude, longitude) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$routeId, $stop['stop_name'], $seq, $stop['arrival_time'] ?? null, $stop['departure_time'] ?? null, $stop['latitude'] ?? null, $stop['longitude'] ?? null]
            );
        }
    }
    
    audit_log('CREATE', 'bus_routes', $routeId, null, $data);
    json_response(['message' => 'Route created successfully', 'id' => $routeId], 201);
}

// PUT - Update route
if ($method === 'PUT') {
    require_role(['admin', 'superadmin']);
    
    $data = get_post_json();
    if (empty($data['id'])) {
        json_response(['error' => 'Route ID required'], 400);
    }
    
    $updates = [];
    $params = [];
    
    foreach (['route_name', 'route_code', 'vehicle_id', 'driver_id', 'conductor_id', 'monthly_fee', 'total_distance', 'capacity', 'is_active', 'description'] as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        json_response(['error' => 'No data to update'], 400);
    }
    
    $params[] = $data['id'];
    db_query("UPDATE bus_routes SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    
    audit_log('UPDATE', 'bus_routes', $data['id'], null, $data);
    json_response(['message' => 'Route updated successfully']);
}

// DELETE - Delete route
if ($method === 'DELETE') {
    require_role(['admin', 'superadmin']);
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        json_response(['error' => 'Route ID required'], 400);
    }
    
    db_query("DELETE FROM bus_stops WHERE route_id = ?", [$id]);
    db_query("DELETE FROM bus_routes WHERE id = ?", [$id]);
    
    audit_log('DELETE', 'bus_routes', $id);
    json_response(['message' => 'Route deleted successfully']);
}
