<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$w = $reports->getWarrantyReport([], 1, 10);
echo "Warranty Report:\n";
echo "  Total Assets: " . $w['total'] . "\n";
echo "  KPIs: " . json_encode($w['kpis']) . "\n";
foreach (array_slice($w['assets'], 0, 5) as $item) {
    echo "  - {$item['product_name']} | S/N: {$item['serial_number']} | Exp: {$item['warranty_expiry_date']} [{$item['current_status']}] ({$item['days_remaining']} days)\n";
}

$s = $reports->getRenewalsReport([], 1, 10);
echo "\nSubscription & MA Renewals Report:\n";
echo "  Total Contracts: " . $s['total'] . "\n";
echo "  KPIs: " . json_encode($s['kpis']) . "\n";
foreach (array_slice($s['subscriptions'], 0, 5) as $item) {
    echo "  - {$item['software_name']} | Cust: {$item['customer_name']} | Seats: {$item['license_seats']} | Exp: {$item['period_end_date']} [{$item['dynamic_status']}]\n";
}
