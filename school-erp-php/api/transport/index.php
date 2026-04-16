<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $routes = db_fetchAll("SELECT r.*, v.vehicle_no, v.driver_name FROM bus_routes r LEFT JOIN transport_vehicles v ON r.vehicle_id = v.id WHERE r.is_active=1 ORDER BY r.route_name");
    try {
        $stops = db_fetchAll("SELECT * FROM bus_stops ORDER BY sequence ASC");
        $stopsByRoute = [];
        foreach ($stops as $stop) {
            $stopsByRoute[$stop['route_id']][] = $stop;
        }
        foreach ($routes as &$route) {
            $route['bus_stops_list'] = $stopsByRoute[$route['id']] ?? [];
        }
        unset($route);
    } catch (Exception $e) {}
    $vehicles = db_fetchAll("SELECT * FROM transport_vehicles WHERE is_active=1");
    $allocations = [];
    try {
        $allocations = db_fetchAll("SELECT * FROM transport_allocations");
    } catch (Exception $e) {
    }
    json_response(['routes' => $routes, 'vehicles' => $vehicles, 'allocations' => $allocations]);
}
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $type = $data['type'] ?? 'route';
    if ($type === 'vehicle') {
        $id = (int) $data['id'];
        db_query(
            "UPDATE transport_vehicles SET vehicle_no=?, type=?, capacity=?, driver_name=?, driver_phone=? WHERE id=?",
            [sanitize($data['vehicle_no'] ?? ''), sanitize($data['vehicle_type'] ?? 'Bus'), (int) ($data['capacity'] ?? 50), sanitize($data['driver_name'] ?? ''), sanitize($data['driver_phone'] ?? ''), $id]
        );
    } elseif ($type === 'route') {
        $id = (int) $data['id'];
        db_query(
            "UPDATE bus_routes SET route_name=?, vehicle_id=?, stops=?, monthly_fee=? WHERE id=?",
            [sanitize($data['route_name'] ?? ''), (int) ($data['vehicle_id'] ?? 0), sanitize($data['stops'] ?? ''), (float) ($data['monthly_fee'] ?? 0), $id]
        );
    }
    json_response(['success' => true]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin', 'admin']);
    $data = get_post_json();
    $type = $data['type'] ?? 'route';
    if ($type === 'assign') {
        $student_id = (int) ($data['student_id'] ?? 0);
        if ($student_id === 0 && !empty($data['user_id'])) {
            $s = db_fetch("SELECT id FROM students WHERE user_id = ?", [(int)$data['user_id']]);
            if ($s) $student_id = (int)$s['id'];
        }
        $route_id = (int) ($data['route_id'] ?? 0);
        try {
            if ($route_id === 0) {
                db_query("DELETE FROM transport_allocations WHERE student_id = ?", [$student_id]);
            } else {
                db_query("REPLACE INTO transport_allocations (student_id, route_id) VALUES (?, ?)", [$student_id, $route_id]);
            }
            json_response(['success' => true]);
        } catch (Exception $e) {
            json_response(['error' => 'Database error. Make sure to run DB patch.'], 500);
        }
    }
    if ($type === 'vehicle') {
        $id = db_insert(
            "INSERT INTO transport_vehicles (vehicle_no, type, capacity, driver_name, driver_phone) VALUES (?,?,?,?,?)",
            [sanitize($data['vehicle_no'] ?? ''), sanitize($data['vehicle_type'] ?? 'Bus'), (int) ($data['capacity'] ?? 50), sanitize($data['driver_name'] ?? ''), sanitize($data['driver_phone'] ?? '')]
        );
        json_response(['success' => true, 'id' => $id]);
    }
    if ($type === 'route') {
        $id = db_insert(
            "INSERT INTO bus_routes (route_name, vehicle_id, stops, monthly_fee) VALUES (?,?,?,?)",
            [sanitize($data['route_name'] ?? ''), (int) ($data['vehicle_id'] ?? 0), sanitize($data['stops'] ?? ''), (float) ($data['monthly_fee'] ?? 0)]
        );
        json_response(['success' => true, 'id' => $id]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin', 'admin']);
    $type = sanitize($_GET['type'] ?? 'route');
    if ($type === 'vehicle')
        db_query("UPDATE transport_vehicles SET is_active=0 WHERE id=?", [(int) $_GET['id']]);
    else
        db_query("UPDATE bus_routes SET is_active=0 WHERE id=?", [(int) $_GET['id']]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
