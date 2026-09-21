<?php
/**
 * Report Controller: Time-Based Expiring Contracts & Invoices
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportFilters = [
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? 'all',
        'range' => $_GET['range'] ?? 'pm_90d',
        'category' => $_GET['category'] ?? 'all',
        'sort' => $_GET['sort'] ?? 'expiry_asc',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? ''
    ];
    $res = $reports->getContractsRenewalReport($exportFilters, 1, 10000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expiring_contracts_renewals_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice Number', 'Customer Name', 'End Customer', 'Service / Software Offering', 'Category', 'Term (Months)', 'Period Start Date', 'Expiring Date', 'Days Remaining', 'Opportunity Value (LKR)', 'Status']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [
            $r['invoice_number'],
            $r['customer_name'],
            $r['end_customer'] ?? '',
            $r['software_name'],
            $r['category_label'],
            $r['term_months'] ?? '',
            $r['period_start_date'],
            $r['period_end_date'],
            $r['days_remaining'],
            round($r['renewal_opportunity_value'], 2),
            $r['dynamic_status']
        ]);
    }
    fclose($out);
    exit;
}

// Data Preparation
list($p, $limit, $isAll) = getReportPaginationParams(25);
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$range = $_GET['range'] ?? 'pm_90d';
$category = $_GET['category'] ?? 'all';
$sort = $_GET['sort'] ?? 'expiry_asc';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$contractsFilters = [
    'search' => $search,
    'status' => $status,
    'range' => $range,
    'category' => $category,
    'sort' => $sort,
    'date_from' => $date_from,
    'date_to' => $date_to
];
$contractsResult = $reports->getContractsRenewalReport($contractsFilters, $p, $limit);
$contractsData = $contractsResult['rows'];
$contractsTotal = $contractsResult['total'];
$contractsPages = $contractsResult['pages'];
$contractsSummary = $contractsResult['summary'];
$reportTitle = 'Time-Based Expiring Contracts & Invoices';
