<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== DISTINCT TAX CODES in 2022-2023 ===\n";
$taxCodes = $db->fetchAll("SELECT tax_code, count(*) as c FROM sales WHERE invoice_date BETWEEN '2022-01-01' AND '2023-12-31' GROUP BY tax_code");
print_r($taxCodes);

echo "=== DISTINCT TAX CODES in 2024+ ===\n";
$taxCodes24 = $db->fetchAll("SELECT tax_code, count(*) as c FROM sales WHERE invoice_date >= '2024-01-01' GROUP BY tax_code");
print_r($taxCodes24);

echo "=== INVOICES WITH 'VAT' or 'Tax' in Item or Account in 2022-2023 ===\n";
$vatRows = $db->fetchAll("SELECT invoice_number, invoice_date, item_description, tax_code, total_amount FROM sales WHERE invoice_date BETWEEN '2022-01-01' AND '2023-12-31' AND (item_description LIKE '%VAT%' OR item_description LIKE '%Tax%') LIMIT 10");
print_r($vatRows);

echo "=== FIRST 10 INVOICES in 2022-09 to 2023-12 ===\n";
$first22 = $db->fetchAll("SELECT invoice_number, invoice_date, item_description, tax_code, total_amount FROM sales WHERE invoice_date BETWEEN '2022-09-01' AND '2022-10-01' LIMIT 15");
print_r($first22);
