<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Sample Software Subscriptions / Maintenance Agreements ===\n";
$mas = $pdo->query("
    SELECT * 
    FROM software_subscriptions 
    WHERE software_name LIKE '%Maintenance%' OR edition_tier LIKE '%Maintenance%'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
print_r($mas);

echo "\n=== Sample Sales with Maintenance/SLA in description ===\n";
$salesMa = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description, total_amount 
    FROM sales 
    WHERE item_description LIKE '%Maintenance Agreement%' 
       OR item_description LIKE '%Maintenance Contract%' 
       OR item_description LIKE '%Annual Maintenance%'
       OR item_description LIKE '% SLA%'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
print_r($salesMa);

echo "\n=== Check if any serial numbers appear in multiple invoices ===\n";
$dupes = $pdo->query("
    SELECT serial_number, count(DISTINCT invoice_number) as inv_count, group_concat(DISTINCT invoice_number) as invs
    FROM hardware_assets 
    WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'
    GROUP BY serial_number
    HAVING inv_count > 1
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
print_r($dupes);
