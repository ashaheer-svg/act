<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

$res = $db->fetchAll("
    SELECT invoice_number, invoice_date 
    FROM sales 
    WHERE invoice_number NOT LIKE 'AS000%' 
      AND invoice_number NOT LIKE 'OB%' 
    ORDER BY invoice_number ASC 
    LIMIT 10
");
echo "=== LOWEST NON-AS000 INVOICES ===\n";
print_r($res);
