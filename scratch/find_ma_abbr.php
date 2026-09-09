<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$stmt = $db->query("
    SELECT invoice_number, invoice_date, customer_name, quantity, total_amount, item_description
    FROM sales
    WHERE item_description LIKE '% MA %'
       OR item_description LIKE '%MA -%'
       OR item_description LIKE '%MA-%'
       OR item_description LIKE '%(MA)%'
       OR item_description LIKE 'MA %'
    LIMIT 20
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " matching MA lines:\n";
foreach ($rows as $r) {
    echo "=========================================================\n";
    echo "Inv: {$r['invoice_number']} | Date: {$r['invoice_date']} | Cust: {$r['customer_name']} | Total: {$r['total_amount']}\n";
    echo "Desc: " . str_replace("\n", "\n      ", trim($r['item_description'])) . "\n";
}
