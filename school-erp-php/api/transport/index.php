<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $routes = db_fetchAll("SELECT r.*, v.vehicle_no, v.driver_name FROM bus_routes r LEFT JOIN transport_vehicles v ON r.vehicle_id = v.id WHERE r.is_active=1 ORDER BY r.route_name");
    $vehicles = db_fetchAll("SELECT * FROM transport_vehicles WHERE is_active=1");
    json_response(['routes' => $routes, 'vehicles' => $vehicles]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin']);
    $data = get_post_json();
    $type = $data['type'] ?? 'route';
    if ($type === 'vehicle') {
        $id = db_insert("INSERT INTO transport_vehicles (vehicle_no, type, capacity, driver_name, driver_phone) VALUES (?,?,?,?,?)",
            [sanitize($data['vehicle_no'] ?? ''), sanitize($data['vehicle_type'] ?? 'Bus'), (int)($data['capacity'] ?? 50), sanitize($data['driver_name'] ?? ''), sanitize($data['driver_phone'] ?? '')]);
        json_response(['success' => true, 'id' => $id]);
    }
    $id = db_insert("INSERT INTO bus_routes (route_name, vehicle_id, stops, monthly_fee) VALUES (?,?,?,?)",
        [sanitize($data['route_name'] ?? ''), (int)($data['vehicle_id'] ?? 0), sanitize($data['stops'] ?? ''), (float)($data['monthly_fee'] ?? 0)]);
    json_response(['success' => true, 'id' => $id]);
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin','admin']);
    $type = sanitize($_GET['type'] ?? 'route');
    if ($type === 'vehicle') db_query("UPDATE transport_vehicles SET is_active=0 WHERE id=?", [(int)$_GET['id']]);
    else db_query("UPDATE bus_routes SET is_active=0 WHERE id=?", [(int)$_GET['id']]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
