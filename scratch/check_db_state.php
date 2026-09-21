<?php
require 'config.php';
require 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== TABLES IN LOCAL DATABASE ===\n";
$tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
foreach ($tables as $t) {
    $count = $db->fetch("SELECT COUNT(*) as c FROM `{$t['name']}`")['c'] ?? 0;
    echo sprintf("Table: %-25s | Rows: %d\n", $t['name'], $count);
}

echo "\n=== PAYMENTS TABLE SCHEMA ===\n";
$cols = $db->fetchAll("PRAGMA table_info(payments)");
foreach ($cols as $c) {
    echo "  {$c['cid']}: {$c['name']} ({$c['type']})\n";
}

echo "\n=== CHECK ANY 2026 'AS' INVOICES IN SALES ===\n";
$as2026 = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, total_amount, paid_date
    FROM sales
    WHERE invoice_date >= '2026-01-01' AND invoice_number LIKE 'AS%'
    ORDER BY invoice_number ASC
");
echo "Count of 2026 AS invoices: " . count($as2026) . "\n";
foreach (array_slice($as2026, 0, 10) as $a) {
    echo "  {$a['invoice_number']} | Date: {$a['invoice_date']} | Cust: {$a['customer_name']} | Amt: {$a['total_amount']} | Paid: {$a['paid_date']}\n";
}

echo "\n=== CHECK PAYMENTS REFERENCING AS000001 -> AS000102 ===\n";
$payAs = $db->fetchAll("
    SELECT * FROM payments 
    WHERE (invoice_num BETWEEN 'AS000001' AND 'AS000102') 
       OR (reference_num BETWEEN 'AS000001' AND 'AS000102')
");
echo "Count of payments with AS000001-AS000102: " . count($payAs) . "\n";
foreach ($payAs as $p) {
    print_r($p);
}

echo "\n=== CHECK ASN INVOICES IN SALES ===\n";
$asn2026 = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, total_amount, paid_date, days_to_pay
    FROM sales
    WHERE invoice_number LIKE 'ASN%'
    GROUP BY invoice_number
    ORDER BY invoice_number ASC
");
echo "Count of distinct ASN invoices: " . count($asn2026) . "\n";
$unpaidAsn = 0;
$paidAsn = 0;
foreach ($asn2026 as $asn) {
    if (!empty($asn['paid_date'])) $paidAsn++;
    else $unpaidAsn++;
}
echo "Paid ASN: $paidAsn, Unpaid ASN: $unpaidAsn\n";
echo "Sample first 5 ASN invoices:\n";
foreach (array_slice($asn2026, 0, 5) as $asn) {
    echo "  {$asn['invoice_number']} | Date: {$asn['invoice_date']} | Cust: {$asn['customer_name']} | Amt: {$asn['total_amount']} | Paid: {$asn['paid_date']}\n";
}
