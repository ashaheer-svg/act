<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$keyAccounts = [
    'Barclays Computers (Pvt) Ltd',
    'Network Information Technologies (Pvt) Lt',
    'Ceylinco General Insurance Limited',
    'GTS Technology Solutions (pvt) Ltd',
    'TechCERT',
    'ABSOLUTE BUSINESS SOLUTIONS ( PVT) LTD'
];

foreach ($keyAccounts as $accName) {
    echo "================================================================================\n";
    echo "ACCOUNT: $accName\n";
    echo "================================================================================\n";
    
    // Invoices
    $invs = $db->query("
        SELECT DISTINCT invoice_number, invoice_date, total_amount
        FROM sales
        WHERE customer_name LIKE " . $db->quote($accName . '%') . "
        ORDER BY invoice_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "INVOICES (" . count($invs) . "):\n";
    foreach ($invs as $inv) {
        echo "  • Inv #{$inv['invoice_number']} on {$inv['invoice_date']} | Total: LKR " . number_format($inv['total_amount'], 2) . "\n";
    }
    
    // Hardware Assets
    $hw = $db->query("
        SELECT invoice_number, brand, model_sku, serial_number, warranty_months, warranty_expiry_date, warranty_status
        FROM hardware_assets
        WHERE customer_name LIKE " . $db->quote($accName . '%') . "
        ORDER BY invoice_number DESC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nHARDWARE ASSETS (" . count($hw) . "):\n";
    foreach (array_slice($hw, 0, 10) as $h) {
        echo "  • [{$h['invoice_number']}] {$h['brand']} {$h['model_sku']} | S/N: {$h['serial_number']} | Exp: {$h['warranty_expiry_date']} ({$h['warranty_months']}M) [{$h['warranty_status']}]\n";
    }
    if (count($hw) > 10) {
        echo "  ... and " . (count($hw) - 10) . " more hardware assets.\n";
    }
    
    // Subscriptions
    $subs = $db->query("
        SELECT invoice_number, software_name, license_seats, period_start_date, period_end_date, renewal_opportunity_value, renewal_status
        FROM software_subscriptions
        WHERE customer_name LIKE " . $db->quote($accName . '%') . "
        ORDER BY invoice_number DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($subs)) {
        echo "\nSUBSCRIPTIONS / MAINTENANCE AGREEMENTS (" . count($subs) . "):\n";
        foreach ($subs as $s) {
            echo "  • [{$s['invoice_number']}] {$s['software_name']} | Seats: {$s['license_seats']} | {$s['period_start_date']} to {$s['period_end_date']} | OppVal: LKR " . number_format($s['renewal_opportunity_value'], 2) . " [{$s['renewal_status']}]\n";
        }
    }
    echo "\n";
}
