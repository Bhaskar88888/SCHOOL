<?php
require_once __DIR__ . '/includes/db.php';

$sql = file_get_contents(__DIR__ . '/schema/departments.sql');
try {
    $pdo = get_db_connection();
    $pdo->exec($sql);
    echo "Departments schema applied successfully.\n";
} catch (Exception $e) {
    echo "Error applying schema: " . $e->getMessage() . "\n";
}
