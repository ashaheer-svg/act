<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/DataSorter.php';

$db = new Database(DATABASE_PATH);
$sorter = new DataSorter($db);

$testInvoices = ['AS000001', 'AS000004', 'AS008836', 'AS008895', 'AS008899'];

foreach ($testInvoices as $inv) {
    echo "======================================================================\n";
    echo "Testing DataSorter on Invoice #$inv\n";
    echo "======================================================================\n";
    $result = $sorter->sortInvoice($inv);
    echo "Customer: {$result['customer_name']} | Date: {$result['invoice_date']} | Gross: {$result['total_gross']}\n";
    echo "Products Extracted: " . count($result['products']) . "\n";
    foreach ($result['products'] as $idx => $p) {
        echo "  [" . ($idx + 1) . "] Type: {$p['product_type']} | Brand: {$p['brand']} | Name: {$p['product_name']}\n";
        echo "      Qty: {$p['quantity']} | Unit: {$p['unit_price']} | Total: {$p['total_amount']}\n";
        echo "      Serials (" . count($p['serials']) . "): " . implode(', ', $p['serials']) . "\n";
        echo "      Warranty: {$p['warranty']['duration_months']} Months | Exp: {$p['warranty']['expiry_date']} | Notes: {$p['warranty']['notes']}\n";
        if ($p['subscription']) {
            echo "      Subscription/MA: {$p['subscription']['software_name']} | Seats: {$p['subscription']['license_seats']} | Period: {$p['subscription']['period_start_date']} to {$p['subscription']['period_end_date']} | OppVal: {$p['subscription']['renewal_opportunity_value']}\n";
        }
    }
    echo "\n";
}
