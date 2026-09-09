<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "Database path: " . DATABASE_PATH . "\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "=== settings table ===\n";
$settings = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC);
print_r($settings);

echo "=== sales columns ===\n";
$cols = $pdo->query("PRAGMA table_info(sales)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['name']} ({$c['type']})\n";
}

echo "\n=== sales zero amounts ===\n";
$total = $pdo->query("SELECT count(*) FROM sales")->fetchColumn();
$zeroTotal = $pdo->query("SELECT count(*) FROM sales WHERE total_amount = 0 OR total_amount IS NULL")->fetchColumn();
$zeroBase = $pdo->query("SELECT count(*) FROM sales WHERE base_value = 0 OR base_value IS NULL")->fetchColumn();
echo "Total sales rows: $total\n";
echo "Rows with total_amount == 0: $zeroTotal\n";
echo "Rows with base_value == 0: $zeroBase\n";

$samples = $pdo->query("SELECT id, invoice_number, invoice_date, customer_name, item_description, quantity, qb_amount, base_value, total_amount, memo FROM sales WHERE total_amount = 0 LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
print_r($samples);
