<?php
require_once __DIR__ . '/includes/db.php';

$tables = [
    'classes', 'students', 'users', 'notices', 'library_books', 
    'complaints', 'fees', 'fee_structures', 'transport_vehicles', 
    'bus_routes', 'hostel_rooms', 'canteen_items', 'exams'
];

echo "DB Check:\n";
foreach ($tables as $table) {
    if (!db_table_exists($table)) {
        echo "$table: MISSING\n";
        continue;
    }
    
    $cols = db_fetchAll("SHOW COLUMNS FROM $table");
    $names = array_column($cols, 'Field');
    $hasIsActive = in_array('is_active', $names);
    echo "$table: " . ($hasIsActive ? 'OK' : 'MISSING is_active') . "\n";
}
