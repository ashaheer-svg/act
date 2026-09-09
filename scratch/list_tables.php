<?php
require_once __DIR__ . '/../config.php';
echo "DATABASE_PATH: " . DATABASE_PATH . "\n";
echo "Exists: " . (file_exists(DATABASE_PATH) ? 'yes, size: ' . filesize(DATABASE_PATH) : 'no') . "\n";

$pdo = new PDO('sqlite:' . DATABASE_PATH);
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n";

// Search for other .db or .sqlite files
foreach (glob(__DIR__ . '/../**/*.db') as $f) {
    echo "Found DB: $f (size: " . filesize($f) . ")\n";
}
foreach (glob(__DIR__ . '/../data/*') as $f) {
    echo "In data/: $f (size: " . filesize($f) . ")\n";
}
