<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$total = $db->fetch("SELECT count(*) as c FROM payments")['c'];
$unique = $db->fetch("SELECT count(*) as c FROM (SELECT DISTINCT customer_name, payment_date, reference_num, amount, invoice_num FROM payments)")['c'];

echo "Total payments: $total\n";
echo "Distinct payments: $unique\n";
echo "Excess duplicate rows: " . ($total - $unique) . "\n";
