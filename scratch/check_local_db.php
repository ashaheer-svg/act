<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database('data/sales_bi.db');
$tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
echo "Tables in sales_bi.db:\n";
foreach ($tables as $t) {
    $name = $t['name'];
    $cnt = $db->fetch("SELECT count(*) as c FROM $name")['c'] ?? 0;
    echo "  - $name: $cnt rows\n";
}

echo "\nChecking customers in local sales table:\n";
$custs = $db->fetchAll("SELECT DISTINCT customer_name FROM sales LIMIT 30");
foreach ($custs as $c) {
    echo "  " . $c['customer_name'] . "\n";
}
