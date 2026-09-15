<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Inspecting ASN000108 and ASN000104 ===\n\n";

$rows = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description, tax_code, quantity, qb_amount, base_value, vat_component, total_amount, vat_treatment 
    FROM sales 
    WHERE invoice_number IN ('ASN000108', 'ASN000104')
")->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);
