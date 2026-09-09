<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== Sample lines with tax_code = 'Tax' ===\n";
$rows = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, item_description, tax_code, total_amount, base_value, vat_component FROM sales WHERE tax_code = 'Tax' LIMIT 15");
foreach ($rows as $r) {
    echo "{$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | Code: {$r['tax_code']} | Amt: {$r['total_amount']} | Base: {$r['base_value']} | VAT: {$r['vat_component']}\n";
    echo "  Desc: " . substr($r['item_description'], 0, 60) . "\n";
}

echo "\n=== Check other invoices around AS000064, AS000072 ===\n";
$invs = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, tax_code, item_description, total_amount, base_value, vat_component FROM sales WHERE invoice_number IN ('AS000063', 'AS000064', 'AS000065', 'AS000071', 'AS000072', 'AS000073') AND total_amount > 0");
foreach ($invs as $r) {
    echo "{$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | Code: {$r['tax_code']} | Amt: {$r['total_amount']}\n";
    echo "  Desc: " . substr($r['item_description'], 0, 60) . "\n";
}
