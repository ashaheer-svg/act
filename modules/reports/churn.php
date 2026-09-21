<?php
/**
 * Report Controller: Account Churn & Reactivation Pipeline
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getAccountChurnReport(['search' => $_GET['search'] ?? '', 'rep_code' => $rep_code, 'risk' => $_GET['risk'] ?? 'all'], 1, 5000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=account_churn_pipeline_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Customer Name', 'Channel', 'Sales Rep', 'Last Order Date', 'Days Inactive', 'Invoices', 'Base Spend (LKR)', 'VAT Paid (LKR)', 'Historical Gross (LKR)', 'Churn Risk Tier']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [$r['customer_name'], $r['customer_type'] ?? '', $r['sales_rep'] ?? '', $r['last_order_date'], $r['days_inactive'], $r['historical_invoices'], $r['historical_base'], $r['historical_vat'], $r['historical_gross'], $r['churn_risk']]);
    }
    fclose($out);
    exit;
}

// Data Preparation
list($p, $limit, $isAll) = getReportPaginationParams(25);
$search = $_GET['search'] ?? '';
$risk = $_GET['risk'] ?? 'all';
$churnFilters = [
    'search' => $search,
    'rep_code' => $rep_code,
    'risk' => $risk
];
$churnResult = $reports->getAccountChurnReport($churnFilters, $p, $limit);
$churnData = $churnResult['rows'];
$churnTotal = $churnResult['total'];
$churnPages = $churnResult['pages'];
$churnSummary = $churnResult['summary'];
$reportTitle = 'Account Churn & Reactivation Pipeline';
