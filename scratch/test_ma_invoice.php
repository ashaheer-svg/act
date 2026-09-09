<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$rep = new Reports($db);

$maRow = $db->fetch("SELECT invoice_number, software_name, renewal_opportunity_value FROM software_subscriptions WHERE software_name LIKE '%Maintenance%' LIMIT 1");
if ($maRow) {
    echo "Found MA invoice: {$maRow['invoice_number']} ({$maRow['software_name']})\n";
    $details = $rep->getInvoiceDetails($maRow['invoice_number']);
    echo "Items: " . count($details['items']) . "\n";
    foreach ($details['items'] as $it) {
        echo "  - {$it['clean_product_name']} [{$it['product_type']}] | Qty: {$it['quantity']} | Total: LKR {$it['total_amount']}\n";
    }
    echo "Subscriptions/MAs: " . count($details['subscriptions']) . "\n";
    foreach ($details['subscriptions'] as $s) {
        echo "  - {$s['software_name']} | Scope: {$s['edition_tier']} | Seats: {$s['license_seats']} | Period: {$s['period_start_date']} to {$s['period_end_date']} | Val: LKR {$s['renewal_opportunity_value']}\n";
    }
}
