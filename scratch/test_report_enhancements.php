<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "=== TESTING getInvoiceSummaryReport (2026-09) ===\n";
$res = $reports->getInvoiceSummaryReport(['year' => '2026', 'month' => '09'], 1, 5);
echo "Total Invoices in Sep 2026: {$res['total']}\n";
foreach ($res['invoices'] as $inv) {
    echo "  • [{$inv['invoice_number']}] {$inv['invoice_date']} | Cust: " . substr($inv['customer_name'], 0, 25) . " | HW: {$inv['hardware_count']} (S/N: {$inv['serials_count']}) | Subs: {$inv['subscriptions_count']} | Gross: LKR " . number_format($inv['total_gross_amount'], 2) . " [{$inv['invoice_vat_treatment']}]\n";
}

echo "\n=== TESTING getInvoiceDetails ('AS000102') ===\n";
$det = $reports->getInvoiceDetails('AS000102');
echo "Header: {$det['header']['invoice_number']} - {$det['header']['customer_name']} (Gross: LKR {$det['header']['total_gross_amount']})\n";
echo "Normalized Items: " . count($det['items']) . "\n";
foreach ($det['items'] as $it) {
    echo "  - Item: {$it['clean_product_name']} [{$it['product_type']}] | Qty: {$it['quantity']} | Total: LKR {$it['total_amount']}\n";
}
echo "Hardware Assets: " . count($det['assets']) . "\n";
foreach ($det['assets'] as $a) {
    echo "  - S/N: {$a['serial_number']} | {$a['product_name']} | Exp: {$a['warranty_expiry_date']} [{$a['warranty_status']}]\n";
}
echo "Software Subscriptions: " . count($det['subscriptions']) . "\n";
echo "Raw Lines: " . count($det['lines']) . "\n";
