<?php
require 'config.php';
require 'classes/Database.php';
require 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

foreach (['AS000064', 'AS000072'] as $inv) {
    echo "=== AJAX INVOICE DETAILS FOR $inv ===\n";
    $details = $reports->getInvoiceDetails($inv);
    echo "Header:\n";
    echo "  Base: " . $details['header']['total_base_value'] . "\n";
    echo "  VAT:  " . $details['header']['total_vat'] . "\n";
    echo "  Gross:" . $details['header']['total_gross_amount'] . "\n";
    echo "  Treatment: " . $details['header']['vat_treatment'] . "\n";
    echo "Customer: " . $details['customer']['customer_name'] . "\n";
    echo "  VAT No: " . ($details['customer']['vat_number'] ?: 'None') . "\n";
    echo "  TIN No: " . ($details['customer']['tin_number'] ?: 'None') . "\n";
    echo "  Is VAT Reg: " . $details['customer']['is_vat_registered'] . "\n";
    echo "Items Count: " . count($details['items']) . "\n";
    foreach ($details['items'] as $it) {
        echo "  - {$it['clean_product_name']} | Base: {$it['base_value']} | VAT: {$it['vat_component']} | Gross: {$it['total_amount']} | Treat: {$it['vat_treatment']}\n";
    }
    echo "Assets Count: " . count($details['assets']) . "\n";
    foreach ($details['assets'] as $a) {
        echo "  - SN: {$a['serial_number']} | Product: {$a['product_name']} | Expiry: {$a['warranty_expiry_date']}\n";
    }
    echo "Payments Count: " . count($details['payments']) . "\n";
    foreach ($details['payments'] as $pm) {
        echo "  - Date: {$pm['payment_date']} | Ref: {$pm['reference_num']} | Amount: {$pm['amount']}\n";
    }
    echo "Reconciliation: Status = {$details['reconciliation']['status']}, Total Paid = {$details['reconciliation']['total_paid']}, Balance Due = {$details['reconciliation']['balance_due']}\n\n";
}
