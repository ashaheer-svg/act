<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Sample PLUS_VAT invoices in 2026 ===\n";
$rows = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description, qb_amount, base_value, vat_component, total_amount, vat_treatment
    FROM sales 
    WHERE vat_treatment = 'PLUS_VAT' AND invoice_date >= '2026-01-01' AND total_amount > 0
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "=== Invoices with separate VAT lines in 2026 ===\n";
$rows2 = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description, qb_amount, base_value, vat_component, total_amount, vat_treatment
    FROM sales 
    WHERE (item_description LIKE '%VAT%' OR item_description LIKE '%Value Added%') AND invoice_date >= '2026-01-01' AND total_amount > 0
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows2);
