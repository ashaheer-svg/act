<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);
$metrics = $reports->getWarrantySummaryMetrics();
echo "Metrics:\n";
print_r($metrics);

$results = $reports->lookupWarrantySerial('20C0SKRCXAQ44');
echo "\nLookup '20C0SKRCXAQ44' (" . count($results) . " found):\n";
foreach ($results as $r) {
    echo "S/N: " . $r['serial_number'] . " | Status: " . $r['status_label'] . " | Invoices: " . count($r['invoices']) . " | MA: " . count($r['maintenance_contracts']) . "\n";
}

$results2 = $reports->lookupWarrantySerial('2110RXRC');
echo "\nLookup '2110RXRC' (" . count($results2) . " found):\n";
foreach ($results2 as $r) {
    echo "S/N: " . $r['serial_number'] . " | Status: " . $r['status_label'] . " | Invoices: " . count($r['invoices']) . " | MA: " . count($r['maintenance_contracts']) . "\n";
}
