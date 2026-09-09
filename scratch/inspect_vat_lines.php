<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$stmt = $db->query("SELECT invoice_number, invoice_date, total_amount, item_description FROM sales WHERE item_description LIKE '% VAT%' OR item_description LIKE 'VAT%' OR item_description LIKE '%Tax%' LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
