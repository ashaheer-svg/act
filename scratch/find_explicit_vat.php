<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== INVOICES WITH SEPARATE VAT LINE (amount > 0) ===\n";
$vatLines = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, item_description, tax_code, total_amount
    FROM sales
    WHERE (item_description LIKE '%VAT%' OR item_description LIKE '%Tax%')
      AND total_amount > 0
    ORDER BY invoice_date DESC
    LIMIT 20
");
print_r($vatLines);
