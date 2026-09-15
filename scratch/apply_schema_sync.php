<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$res = $db->syncSchema();
echo "Schema sync result:\n";
print_r($res);

$pdo = $db->getConnection();
$colsSales = array_column($pdo->query("PRAGMA table_info(sales)")->fetchAll(PDO::FETCH_ASSOC), 'name');
$colsHw = array_column($pdo->query("PRAGMA table_info(hardware_assets)")->fetchAll(PDO::FETCH_ASSOC), 'name');
$colsIi = array_column($pdo->query("PRAGMA table_info(invoice_items)")->fetchAll(PDO::FETCH_ASSOC), 'name');

echo "\nsales end_customer: " . (in_array('end_customer', $colsSales) ? 'YES' : 'NO') . "\n";
echo "hardware_assets end_customer: " . (in_array('end_customer', $colsHw) ? 'YES' : 'NO') . "\n";
echo "invoice_items end_customer: " . (in_array('end_customer', $colsIi) ? 'YES' : 'NO') . "\n";
