<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

echo "1. Distinct tax codes in sales:\n";
$stmt = $db->query("SELECT DISTINCT tax_code, COUNT(*) as cnt FROM sales GROUP BY tax_code");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n2. Sample recent invoices (2024-2026) where total > 0:\n";
$stmt = $db->query("SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, tax_code, applied_tax_rate, item_description FROM sales WHERE invoice_date >= '2024-01-01' AND total_amount > 0 LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n3. Sample 2021-2022 invoices where total > 0:\n";
$stmt = $db->query("SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, tax_code, applied_tax_rate, item_description FROM sales WHERE invoice_date <= '2022-12-31' AND total_amount > 0 LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n4. Existing tax_rules table:\n";
$stmt = $db->query("SELECT * FROM tax_rules");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n5. Invoices with explicit VAT in item_description:\n";
$stmt = $db->query("SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, item_description FROM sales WHERE item_description LIKE '%VAT%' LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
