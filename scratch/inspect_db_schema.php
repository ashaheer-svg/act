<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
echo "=== PAYMENTS SCHEMA ===\n";
print_r($db->fetch("SELECT sql FROM sqlite_master WHERE type='table' AND name='payments'"));

echo "\n=== PAYMENTS INDEXES ===\n";
print_r($db->fetchAll("PRAGMA index_list(payments)"));

echo "\n=== TOTAL PAYMENTS ===\n";
print_r($db->fetch("SELECT COUNT(*) as c FROM payments"));

$dup = $db->fetchAll("SELECT customer_name, payment_date, reference_num, amount, invoice_num, count(*) as c FROM payments GROUP BY customer_name, payment_date, reference_num, amount, invoice_num HAVING count(*) > 1");
echo "Duplicate payment groups locally: " . count($dup) . "\n";

echo "\n=== SALES UNIQUE CONSTRAINTS ===\n";
print_r($db->fetch("SELECT sql FROM sqlite_master WHERE type='table' AND name='sales'"));
print_r($db->fetchAll("PRAGMA index_list(sales)"));
