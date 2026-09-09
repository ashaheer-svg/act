<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

echo "=== HARDWARE ASSETS SUMMARY (Sample of 10) ===\n";
$stmt = $db->query("
    SELECT invoice_number, customer_name, product_name, brand, model_sku, serial_number, warranty_months, warranty_expiry_date, warranty_status
    FROM hardware_assets
    WHERE serial_number != 'UNASSIGNED'
    LIMIT 10
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Inv: {$r['invoice_number']} | {$r['brand']} {$r['model_sku']} | S/N: {$r['serial_number']} | Exp: {$r['warranty_expiry_date']} [{$r['warranty_status']}] | Cust: " . substr($r['customer_name'], 0, 25) . "\n";
}

echo "\n=== SOFTWARE & MAINTENANCE AGREEMENTS (MA) ===\n";
$stmt = $db->query("
    SELECT invoice_number, customer_name, software_name, edition_tier, license_seats, period_start_date, period_end_date, renewal_status, renewal_opportunity_value
    FROM software_subscriptions
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Inv: {$r['invoice_number']} | {$r['software_name']} | Seats: {$r['license_seats']} | Period: {$r['period_start_date']} to {$r['period_end_date']} [{$r['renewal_status']}] | OppVal: LKR {$r['renewal_opportunity_value']}\n";
}

echo "\n=== TOTAL COUNTS ===\n";
echo "Total Hardware Assets: " . $db->query("SELECT COUNT(*) FROM hardware_assets")->fetchColumn() . "\n";
echo "  Active Warranties:   " . $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'ACTIVE'")->fetchColumn() . "\n";
echo "  Expiring in 30 Days: " . $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRING_30D'")->fetchColumn() . "\n";
echo "  Expiring in 60 Days: " . $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRING_60D'")->fetchColumn() . "\n";
echo "  Expiring in 90 Days: " . $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRING_90D'")->fetchColumn() . "\n";
echo "  Expired:             " . $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRED'")->fetchColumn() . "\n";
echo "Total Subscriptions/MAs: " . $db->query("SELECT COUNT(*) FROM software_subscriptions")->fetchColumn() . "\n";
