<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== hardware_assets count & sample ===\n";
$hwCount = $pdo->query("SELECT count(*) FROM hardware_assets")->fetchColumn();
echo "Total hardware_assets: $hwCount\n";
$hwSerials = $pdo->query("SELECT count(*) FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'")->fetchColumn();
echo "Hardware assets with serial numbers: $hwSerials\n";
$hwSample = $pdo->query("SELECT * FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($hwSample);

echo "\n=== Serial numbers in sales table ===\n";
$salesWithSN = $pdo->query("
    SELECT count(*) 
    FROM sales 
    WHERE item_description LIKE '%S/N%' OR item_description LIKE '%SN:%' OR item_description LIKE '%Serial%'
")->fetchColumn();
echo "Sales rows mentioning S/N or Serial: $salesWithSN\n";

$salesSNSamples = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description 
    FROM sales 
    WHERE item_description LIKE '%S/N%' OR item_description LIKE '%SN:%' OR item_description LIKE '%Serial%'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
print_r($salesSNSamples);

echo "\n=== Warranty mentions in sales table ===\n";
$warrantySales = $pdo->query("
    SELECT count(*) 
    FROM sales 
    WHERE item_description LIKE '%Warranty%'
")->fetchColumn();
echo "Sales rows mentioning Warranty: $warrantySales\n";

$warrantySamples = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description 
    FROM sales 
    WHERE item_description LIKE '%Warranty%'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
print_r($warrantySamples);

echo "\n=== software_subscriptions count & sample ===\n";
$subsCount = $pdo->query("SELECT count(*) FROM software_subscriptions")->fetchColumn();
echo "Total software_subscriptions: $subsCount\n";
$subsSample = $pdo->query("SELECT * FROM software_subscriptions LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($subsSample);

echo "\n=== Maintenance Agreement (MA) in sales table ===\n";
$maSales = $pdo->query("
    SELECT count(*) 
    FROM sales 
    WHERE item_description LIKE '%MA%' OR item_description LIKE '%Maintenance%' OR item_description LIKE '%Agreement%' OR item_description LIKE '%SLA%'
")->fetchColumn();
echo "Sales rows mentioning MA / Maintenance / SLA: $maSales\n";

$maSamples = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, item_description 
    FROM sales 
    WHERE item_description LIKE '%Maintenance%' OR item_description LIKE '%Agreement%' OR item_description LIKE '%SLA%'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
print_r($maSamples);
