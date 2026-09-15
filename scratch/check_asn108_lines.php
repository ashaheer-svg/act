<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "=== Reports::getInvoiceSummaryReport for ASN000108 ===\n";
$invRes = $reports->getInvoiceSummaryReport(['search' => 'ASN000108'], 1, 10);
foreach ($invRes['invoices'] as $r) {
    echo sprintf(
        "Inv: %s | Date: %s | Cust: %s | Base: %10.2f | VAT: %8.2f | Gross: %10.2f | Treat: %s\n",
        $r['invoice_number'], $r['invoice_date'], substr($r['customer_name'], 0, 25),
        $r['total_base_value'], $r['total_vat_component'], $r['total_gross_amount'], $r['invoice_vat_treatment']
    );
}

echo "\n=== Reports::getInvoiceSummaryReport for ASN000104 ===\n";
$invRes2 = $reports->getInvoiceSummaryReport(['search' => 'ASN000104'], 1, 10);
foreach ($invRes2['invoices'] as $r) {
    echo sprintf(
        "Inv: %s | Date: %s | Cust: %s | Base: %10.2f | VAT: %8.2f | Gross: %10.2f | Treat: %s\n",
        $r['invoice_number'], $r['invoice_date'], substr($r['customer_name'], 0, 25),
        $r['total_base_value'], $r['total_vat_component'], $r['total_gross_amount'], $r['invoice_vat_treatment']
    );
}

echo "\n=== Reports::getInvoiceDetails for ASN000108 ===\n";
$det = $reports->getInvoiceDetails('ASN000108');
print_r($det['header']);
