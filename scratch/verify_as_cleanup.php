<?php
$db = new PDO('sqlite:data/sales_bi.db');
$remaining = $db->query("SELECT invoice_number, invoice_date FROM sales WHERE invoice_number LIKE 'AS000%' AND strftime('%Y', invoice_date) = '2026'")->fetchAll(PDO::FETCH_ASSOC);
echo "Remaining 2026 AS000xxx invoices: " . count($remaining) . "\n";
print_r($remaining);

$asnCount = $db->query("SELECT COUNT(DISTINCT invoice_number) as cnt FROM sales WHERE invoice_number LIKE 'ASN%'")->fetch(PDO::FETCH_ASSOC);
echo "ASN invoices active in 2026: " . $asnCount['cnt'] . "\n";
