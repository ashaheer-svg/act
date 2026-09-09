<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$warranties = $reports->getHardwareWarranties(['search' => '', 'brand' => '', 'status' => ''], 10, 0);
echo "Hardware Warranties Report:\n";
echo "  Total Assets: {$warranties['total']}\n";
echo "  Returned in Page 1: " . count($warranties['items']) . "\n";
foreach (array_slice($warranties['items'], 0, 3) as $w) {
    echo "    - {$w['product_name']} | S/N: {$w['serial_number']} | Exp: {$w['warranty_expiry_date']} [{$w['warranty_status']}] | Days: {$w['days_remaining']}\n";
}

$renewals = $reports->getSoftwareRenewals(['search' => '', 'status' => ''], 10, 0);
echo "\nSoftware & MA Renewals Report:\n";
echo "  Total Contracts: {$renewals['total']}\n";
echo "  Returned in Page 1: " . count($renewals['items']) . "\n";
foreach ($renewals['items'] as $rn) {
    echo "    - {$rn['software_name']} | Seats: {$rn['license_seats']} | Exp: {$rn['period_end_date']} [{$rn['renewal_status']}] | Days: {$rn['days_remaining']} | Val: {$rn['renewal_opportunity_value']}\n";
}
