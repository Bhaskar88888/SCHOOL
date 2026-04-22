<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function list_everything($dir, $depth = 0) {
    if ($depth > 2) return;
    $items = scandir($dir);
    echo "<ul>";
    foreach ($items as $item) {
        if ($item == "." || $item == "..") continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $type = is_dir($path) ? "<strong>[DIR]</strong>" : "[FILE]";
        echo "<li>$type $item</li>";
        if (is_dir($path)) {
            list_everything($path, $depth + 1);
        }
    }
    echo "</ul>";
}

echo "<h1>Recursive File Scan</h1>";
echo "Current Path: " . __DIR__ . "<br>";
list_everything(__DIR__);
?>
