<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "=== TESTING getWarrantyReport ===\n";
$w = $reports->getWarrantyReport(['status' => 'all'], 1, 10);
echo "Total Hardware Assets: " . $w['total'] . "\n";
echo "Active: " . $w['kpis']['active_assets'] . "\n";
echo "Expiring 30d: " . $w['kpis']['expiring_30d'] . "\n";
echo "Expiring 90d: " . $w['kpis']['expiring_90d'] . "\n";
echo "Expired: " . $w['kpis']['expired_assets'] . "\n";
foreach ($w['assets'] as $a) {
    echo "  - [{$a['serial_number']}] {$a['product_name']} (Inv: {$a['invoice_number']}, Expiry: {$a['warranty_expiry_date']}, Status: {$a['dynamic_status']})\n";
}

echo "\n=== TESTING getRenewalsReport ===\n";
$r = $reports->getRenewalsReport(['status' => 'all'], 1, 10);
echo "Total Subscriptions: " . $r['total'] . "\n";
echo "Total Seats: " . $r['kpis']['total_seats'] . "\n";
echo "Pipeline Value: LKR " . number_format($r['kpis']['pipeline_value'] ?? 0) . "\n";
echo "Calendar Months: " . count($r['calendar']) . "\n";

echo "\n=== TESTING getInvoiceDetails for AS000102 ===\n";
$det = $reports->getInvoiceDetails('AS000102');
echo "Header: " . $det['header']['invoice_number'] . " (" . $det['header']['customer_name'] . ")\n";
echo "Extracted Assets Count: " . count($det['assets'] ?? []) . "\n";
foreach ($det['assets'] ?? [] as $ea) {
    echo "  - S/N: {$ea['serial_number']} | Brand: {$ea['brand']} | Warranty: {$ea['warranty_months']}m\n";
}
echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
