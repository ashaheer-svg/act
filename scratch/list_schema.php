<?php
require_once __DIR__ . '/../config.php';
$pdo = new PDO('sqlite:' . DATABASE_PATH);

echo "=== Columns in sales ===\n";
foreach ($pdo->query("PRAGMA table_info(sales)") as $col) {
    echo $col['name'] . " (" . $col['type'] . ")\n";
}

echo "\n=== Columns in tax_rules ===\n";
foreach ($pdo->query("PRAGMA table_info(tax_rules)") as $col) {
    echo $col['name'] . " (" . $col['type'] . ")\n";
}

echo "\n=== tax_rules content ===\n";
foreach ($pdo->query("SELECT * FROM tax_rules") as $r) {
    print_r($r);
}
