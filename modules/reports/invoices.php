<?php
/**
 * Report Controller: Commercial Sales Invoices Ledger
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $invoiceFilters = [
        'year' => $year,
        'month' => $month,
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? 'all',
        'brand' => $brand,
        'customer_type' => $customer_type,
        'rep_code' => $rep_code,
        'sort' => $_GET['sort'] ?? 'invoice_date_desc'
    ];
    $exportResult = $reports->getInvoiceSummaryReport($invoiceFilters, 1, 10000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=invoices_summary_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice Number', 'Type', 'Date', 'Customer Name', 'Customer Type', 'Sales Rep', 'PO Number', 'Line Items', 'Quantity Units', 'Base Net (LKR)', '18% VAT (LKR)', 'Gross Total (LKR)', 'Settlement Status', 'Paid Date', 'Days to Pay']);
    foreach ($exportResult['invoices'] as $r) {
        $statusLabel = (!empty($r['paid_date'])) ? 'Settled' : 'Unpaid';
        fputcsv($out, [
            $r['invoice_number'],
            $r['invoice_type'],
            $r['invoice_date'],
            $r['customer_name'],
            $r['customer_type'] ?? '',
            $r['rep_name'] ?? $r['sales_rep_code'],
            $r['po_number'] ?? '',
            $r['line_count'],
            $r['total_quantity'],
            round($r['total_base_value'], 2),
            round($r['total_vat_component'], 2),
            round($r['total_gross_amount'], 2),
            $statusLabel,
            $r['paid_date'] ?? '',
            $r['days_to_pay'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Data Preparation
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'invoice_date_desc';
list($p, $limit, $isAll) = getReportPaginationParams(25);
$invoiceFilters = [
    'year' => $year,
    'month' => $month,
    'search' => $search,
    'status' => $status,
    'brand' => $brand,
    'customer_type' => $customer_type,
    'rep_code' => $rep_code,
    'sort' => $sort
];
$invoiceResult = $reports->getInvoiceSummaryReport($invoiceFilters, $p, $limit);
$invoiceData = $invoiceResult['invoices'];
$invoiceTotal = $invoiceResult['total'];
$invoicePages = $invoiceResult['pages'];
$invoiceSummary = $invoiceResult['summary'];
$reportTitle = 'Commercial Sales Invoices Ledger';
