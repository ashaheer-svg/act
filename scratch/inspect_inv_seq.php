<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== INVOICE NUMBER PREFIXES & RANGES IN DB ===\n";
$rows = $db->fetchAll("
    SELECT substr(invoice_number, 1, 3) as pfx, 
           MIN(invoice_number) as min_inv, 
           MAX(invoice_number) as max_inv, 
           COUNT(DISTINCT invoice_number) as cnt 
    FROM sales 
    GROUP BY pfx
");
print_r($rows);

echo "=== CHECKING RECENT INVOICES (AS000... vs ASN000...) ===\n";
$sample = $db->fetchAll("
    SELECT DISTINCT invoice_number, invoice_date 
    FROM sales 
    WHERE invoice_number IN ('AS000001', 'AS000102', 'AS010020', 'AS010021', 'AS011260', 'AS008212', 'AS008155', 'AS006561')
    ORDER BY invoice_number ASC 
");
print_r($sample);
