<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Sample VAT_INCLUSIVE rows ===\n";
$rows = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description, tax_code, qb_amount, base_value, vat_component, total_amount, vat_treatment
    FROM sales 
    WHERE vat_treatment = 'VAT_INCLUSIVE'
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
