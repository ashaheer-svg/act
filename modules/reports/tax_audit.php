<?php
/**
 * Report Controller: Statutory Tax & IRD Audit Ledger (18% VAT)
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getTaxAuditReport($year, $month, 1, 10000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=statutory_tax_ird_audit_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice Number', 'Type', 'Invoice Date', 'Customer Name', 'VAT Treatment', 'Taxable Base (LKR)', 'Statutory 18% VAT (LKR)', 'Gross Total (LKR)', 'Settlement Date']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [$r['invoice_number'], $r['invoice_type'], $r['invoice_date'], $r['customer_name'], $r['vat_treatment'], $r['taxable_base'], $r['vat_18_component'], $r['gross_total'], $r['paid_date'] ?? '']);
    }
    fclose($out);
    exit;
}

// Data Preparation
list($p, $limit, $isAll) = getReportPaginationParams(25);
$taxResult = $reports->getTaxAuditReport($year, $month, $p, $limit);
$taxData = $taxResult['rows'];
$taxTotal = $taxResult['total'];
$taxPages = $taxResult['pages'];
$taxSummary = $taxResult['summary'];
$reportTitle = 'Statutory Tax & IRD Audit Ledger (18% VAT)';
