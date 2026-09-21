<?php
/**
 * Report Controller: Unpaid Invoices Ledger (Receivables by Customer)
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getUnpaidInvoicesByCustomerReport([
        'search' => $_GET['search'] ?? '',
        'rep_code' => $rep_code,
        'customer_type' => $customer_type,
        'aging_bracket' => $_GET['aging_bracket'] ?? 'all',
        'sort' => $_GET['sort'] ?? 'customer_asc'
    ], 1, 5000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=unpaid_invoices_by_customer_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Customer Name', 'Channel', 'Sales Rep', 'Invoice Number', 'Invoice Date', 'Aging Days', 'Aging Bracket', 'PO Number', 'Line Items Count', 'Base Net (LKR)', '18% VAT (LKR)', 'Gross Balance Due (LKR)']);
    foreach ($res['customers'] as $cust) {
        foreach ($cust['invoices'] as $inv) {
            fputcsv($out, [
                $cust['customer_name'],
                $cust['customer_type'],
                $cust['rep_name'] ?: $cust['sales_rep_code'],
                $inv['invoice_number'],
                $inv['invoice_date'],
                $inv['aging_days'],
                $inv['aging_label'],
                $inv['po_number'] ?? '',
                $inv['line_count'],
                $inv['base_value'],
                $inv['vat_component'],
                $inv['total_amount']
            ]);
        }
    }
    fclose($out);
    exit;
}

// Data Preparation
$search = $_GET['search'] ?? '';
$agingBracket = $_GET['aging_bracket'] ?? 'all';
$sort = $_GET['sort'] ?? 'customer_asc';
$unpaidSearch = $search;
$unpaidAging = $agingBracket;
$unpaidSort = $sort;

list($p, $limit, $isAll) = getReportPaginationParams(50);
$unpaidFilters = [
    'search' => $search,
    'aging_bracket' => $agingBracket,
    'rep_code' => $rep_code,
    'customer_type' => $customer_type,
    'sort' => $sort
];
$unpaidResult = $reports->getUnpaidInvoicesByCustomerReport($unpaidFilters, $p, $limit);
$unpaidCustomers = $unpaidResult['customers'] ?? [];
$unpaidData = $unpaidCustomers;
$unpaidTotal = $unpaidResult['total'] ?? 0;
$unpaidPages = $unpaidResult['pages'] ?? 1;
$unpaidSummary = $unpaidResult['summary'] ?? [];
$reportTitle = 'Unpaid Invoices Ledger (Sorted by Customer)';

