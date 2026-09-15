<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$res = $reports->getInvoiceSummaryReport([], 1, 15);

echo sprintf("%-12s | %-12s | %-12s | %-18s\n", "Invoice", "Base Net", "VAT", "invoice_vat_treat");
echo str_repeat("-", 60) . "\n";

foreach ($res['invoices'] as $r) {
    echo sprintf(
        "%-12s | %-12s | %-12s | %-18s\n",
        $r['invoice_number'],
        number_format($r['total_base_value'], 0),
        number_format($r['total_vat_component'], 0),
        $r['invoice_vat_treatment'] ?? 'NULL'
    );
}
