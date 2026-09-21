<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$invoices = $reports->getInvoiceSummaryReport([], 1, 20);

echo "First 15 invoices from getInvoiceSummaryReport():\n";
echo sprintf("%-12s | %-25s | %-12s | %-10s | %-12s | %-15s\n", "Inv #", "Customer", "Base", "VAT", "Gross", "VAT Treatment");
echo str_repeat("-", 85) . "\n";

foreach (array_slice($invoices['invoices'] ?? [], 0, 15) as $row) {
    echo sprintf(
        "%-12s | %-25s | %-12s | %-10s | %-12s | %-15s\n",
        $row['invoice_number'],
        substr($row['customer_name'], 0, 25),
        number_format($row['total_base_value'], 0),
        number_format($row['total_vat_component'], 0),
        number_format($row['total_gross_amount'], 0),
        $row['vat_treatment'] ?? 'NULL'
    );
}
