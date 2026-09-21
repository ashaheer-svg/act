<?php
/**
 * Report Controller: Customer Lifetime Value (LTV)
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getCustomerLTVReport(['search' => $_GET['search'] ?? '', 'customer_type' => $customer_type, 'rep_code' => $rep_code, 'tier' => $_GET['tier'] ?? '', 'sort' => $_GET['sort'] ?? 'ltv_desc'], 1, 5000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customer_ltv_matrix_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Customer Name', 'Category', 'Sales Rep', 'First Invoice', 'Last Invoice', 'Tenure (Yrs)', 'Invoices', 'Base Net (LKR)', '18% VAT (LKR)', 'Lifetime Gross (LKR)', 'Avg Order Value (LKR)', 'Tier']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [$r['customer_name'], $r['customer_type'] ?? '', $r['sales_rep'] ?? '', $r['first_invoice'], $r['last_invoice'], $r['tenure_years'], $r['total_invoices'], $r['lifetime_base'], $r['lifetime_vat'], $r['lifetime_gross'], $r['avg_order_value'], $r['ltv_tier']]);
    }
    fclose($out);
    exit;
}

// Data Preparation
list($p, $limit, $isAll) = getReportPaginationParams(25);
$search = $_GET['search'] ?? '';
$tier = $_GET['tier'] ?? 'all';
$sort = $_GET['sort'] ?? 'ltv_desc';
$ltvFilters = [
    'search' => $search,
    'customer_type' => $customer_type,
    'rep_code' => $rep_code,
    'tier' => $tier,
    'sort' => $sort
];
$ltvResult = $reports->getCustomerLTVReport($ltvFilters, $p, $limit);
$ltvData = $ltvResult['rows'];
$ltvTotal = $ltvResult['total'];
$ltvPages = $ltvResult['pages'];
$ltvSummary = $ltvResult['summary'];
$reportTitle = 'Customer Lifetime Value (LTV) & Loyalty Matrix';
