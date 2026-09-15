<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "=== Tax Audit Report for 2026 ===\n";
$taxRes = $reports->getTaxAuditReport(2026, 'all', 1, 10);
print_r($taxRes['summary']);

echo "\nFirst 5 rows for 2026:\n";
foreach (array_slice($taxRes['rows'], 0, 5) as $r) {
    echo sprintf(
        "Inv: %-10s | Date: %s | Cust: %-25s | Treat: %-8s | Base: %10.2f | VAT: %8.2f | Gross: %10.2f\n",
        $r['invoice_number'], $r['invoice_date'], substr($r['customer_name'], 0, 25),
        $r['vat_treatment'], $r['taxable_base'], $r['vat_18_component'], $r['gross_total']
    );
}

echo "\n=== Tax Audit Report for All Eras (all years) ===\n";
$taxAll = $reports->getTaxAuditReport('all', 'all', 1, 10);
print_r($taxAll['summary']);
