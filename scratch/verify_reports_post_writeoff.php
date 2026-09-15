<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

// 1. Unpaid Invoices Report
$unpaidReport = $reports->getUnpaidInvoicesByCustomerReport();
echo "=== UNPAID INVOICES REPORT CHECK ===\n";
echo "Total Debtors: " . count($unpaidReport['customers']) . "\n";
print_r($unpaidReport['summary']);

// Check if EITS appears anywhere in the debtors list
$foundEits = false;
foreach ($unpaidReport['customers'] as $d) {
    if ($d['customer_name'] === 'EITS') {
        $foundEits = true;
        break;
    }
}
echo "EITS in Debtors List: " . ($foundEits ? 'YES (ERROR)' : 'NO (Cleaned!)') . "\n";

// 2. Invoices List KPI Ribbon
$invList = $reports->getInvoiceSummaryReport(['type' => 'Invoice'], 1, 10);
echo "\n=== COMMERCIAL INVOICES KPI RIBBON CHECK ===\n";
echo "Total Invoices: " . $invList['summary']['total_invoices'] . "\n";
echo "Settled Invoices Count: " . $invList['summary']['settled_invoices_count'] . "\n";
echo "Settled Amount: LKR " . number_format($invList['summary']['settled_amount'], 2) . "\n";
echo "Unpaid Invoices Count: " . $invList['summary']['unpaid_invoices_count'] . "\n";
echo "Unpaid Amount: LKR " . number_format($invList['summary']['unpaid_amount'], 2) . "\n";
