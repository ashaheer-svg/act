<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$totalHw = $pdo->query("SELECT count(id) FROM hardware_assets")->fetchColumn();
$withSerial = $pdo->query("SELECT count(id) FROM hardware_assets WHERE serial_number != '' AND serial_number IS NOT NULL AND serial_number != 'UNASSIGNED'")->fetchColumn();
$uniqueSerials = $pdo->query("SELECT count(DISTINCT serial_number) FROM hardware_assets WHERE serial_number != '' AND serial_number IS NOT NULL AND serial_number != 'UNASSIGNED'")->fetchColumn();

echo "Total hardware_assets: $totalHw\n";
echo "Hardware assets with serial number: $withSerial\n";
echo "Distinct serial numbers in hardware_assets: $uniqueSerials\n";

$statusDist = $pdo->query("SELECT warranty_status, count(*) as count FROM hardware_assets GROUP BY warranty_status")->fetchAll(PDO::FETCH_ASSOC);
echo "\nWarranty Status Distribution in hardware_assets:\n";
print_r($statusDist);

$salesWithSerial = $pdo->query("
    SELECT count(id) FROM sales 
    WHERE item_description LIKE '%S/N%' OR item_description LIKE '%SN:%' OR item_description LIKE '%Serial%'
")->fetchColumn();
echo "\nSales lines mentioning S/N or Serial: $salesWithSerial\n";
