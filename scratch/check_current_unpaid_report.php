<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$unpaidReport = $reports->getUnpaidInvoicesByCustomerReport([], 1, 100);

echo "=== UNPAID INVOICES REPORT SUMMARY ===\n";
echo "Total Debtors: " . $unpaidReport['kpis']['total_debtors'] . "\n";
echo "Total Unpaid Invoices: " . $unpaidReport['kpis']['total_unpaid_invoices'] . "\n";
echo "Total Outstanding Base: LKR " . number_format($unpaidReport['kpis']['total_base_due'], 2) . "\n";
echo "Total Outstanding VAT: LKR " . number_format($unpaidReport['kpis']['total_vat_due'], 2) . "\n";
echo "Total Outstanding Gross: LKR " . number_format($unpaidReport['kpis']['total_gross_due'], 2) . "\n";
echo "Average Aging Days: " . $unpaidReport['kpis']['avg_aging_days'] . " days\n";
echo "Aging Brackets:\n";
echo " - Current (<=30d): LKR " . number_format($unpaidReport['kpis']['aging_buckets']['current'], 2) . "\n";
echo " - 30-60d: LKR " . number_format($unpaidReport['kpis']['aging_buckets']['30_60'], 2) . "\n";
echo " - 60-90d: LKR " . number_format($unpaidReport['kpis']['aging_buckets']['60_90'], 2) . "\n";
echo " - Over 90d: LKR " . number_format($unpaidReport['kpis']['aging_buckets']['over_90'], 2) . "\n";

echo "\nFirst 10 Debtors:\n";
foreach (array_slice($unpaidReport['customers'], 0, 10) as $c) {
    echo sprintf(" - %-35s | Invs: %2d | Due: LKR %12s | Max Aging: %3d days\n", 
        $c['customer_name'], $c['unpaid_invoices_count'], number_format($c['total_gross_due'], 2), $c['max_aging_days']);
}
