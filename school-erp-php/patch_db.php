<?php
require_once __DIR__ . '/includes/db.php';

function add_column_if_missing($table, $column, $definition) {
    if (!db_table_exists($table)) {
        echo "Table $table does not exist. Skipping.\n";
        return;
    }
    
    // Manual check for column to avoid syntax errors with IF NOT EXISTS on older DBs
    $cols = db_fetchAll("SHOW COLUMNS FROM $table");
    $names = array_column($cols, 'Field');
    
    if (!in_array($column, $names)) {
        try {
            db_query("ALTER TABLE $table ADD COLUMN $column $definition");
            echo "Added $column to $table.\n";
        } catch (Exception $e) {
            echo "Error adding $column to $table: " . $e->getMessage() . "\n";
        }
    } else {
        echo "$column already exists in $table.\n";
    }
}

echo "<pre>";
add_column_if_missing('classes', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('students', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('users', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('notices', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('library_books', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('complaints', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('fees', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('fee_structures', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('transport_vehicles', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('bus_routes', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('hostel_rooms', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('canteen_items', 'is_active', 'tinyint(1) DEFAULT 1');
add_column_if_missing('exams', 'is_active', 'tinyint(1) DEFAULT 1');

add_column_if_missing('classes', 'section', "varchar(20) DEFAULT ''");

echo "All patches completed.\n";
echo "</pre>";
