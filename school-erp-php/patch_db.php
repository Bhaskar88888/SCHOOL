<?php
require_once __DIR__ . '/includes/db.php';
try {
    db_query("ALTER TABLE library_issues ADD COLUMN staff_id int DEFAULT NULL AFTER student_id");
    echo "Success";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Success (Already exists)";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
