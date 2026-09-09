<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "Testing getInvoiceSummaryReport()...\n";
$res = $reports->getInvoiceSummaryReport(['year' => 'all'], 1, 5);
echo "Total Invoices: " . $res['total'] . "\n";
echo "Total Pages: " . $res['pages'] . "\n";
echo "Summary Gross: " . $res['summary']['grand_gross_revenue'] . "\n";
echo "First 2 Invoices:\n";
foreach (array_slice($res['invoices'], 0, 2) as $inv) {
    echo "  Inv: {$inv['invoice_number']} | Date: {$inv['invoice_date']} | Cust: {$inv['customer_name']} | Lines: {$inv['line_count']} | Gross: {$inv['total_gross_amount']} | Serials: {$inv['has_serials']}\n";
}

echo "\nTesting getInvoiceDetails('AS009317')...\n";
$details = $reports->getInvoiceDetails('AS009317');
echo "Status: " . $details['reconciliation']['status'] . "\n";
echo "Gross: " . $details['reconciliation']['total_gross'] . "\n";
echo "Lines count: " . count($details['lines']) . "\n";
echo "First line: " . substr($details['lines'][0]['item_description'], 0, 60) . "...\n";
echo "Payments count: " . count($details['payments']) . "\n";
