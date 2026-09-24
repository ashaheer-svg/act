<?php
/**
 * Report Controller: Unlinked Payments Audit Ledger
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getUnlinkedPaymentsReport([
        'search' => $_GET['search'] ?? '',
        'year' => $_GET['year'] ?? 'all'
    ], 1, 10000);

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=unlinked_payments_audit_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Receipt ID', 'Payment Date', 'Customer Name', 'Cheque / Ref Number', 'Payment Method', 'Deposit Account', 'Amount (LKR)', 'Memo / Notes']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [
            $r['id'],
            $r['payment_date'],
            $r['customer_name'],
            $r['reference_num'] ?? '',
            $r['payment_method'] ?? '',
            $r['deposit_account'] ?? '',
            $r['amount'],
            $r['memo'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Data Preparation
$search = $_GET['search'] ?? '';
$filterYear = $_GET['year'] ?? 'all';

list($p, $limit, $isAll) = getReportPaginationParams(50);
$unlinkedResult = $reports->getUnlinkedPaymentsReport([
    'search' => $search,
    'year' => $filterYear
], $p, $limit);

$unlinkedData = $unlinkedResult['rows'] ?? [];
$unlinkedTotal = $unlinkedResult['total'] ?? 0;
$unlinkedPages = $unlinkedResult['pages'] ?? 1;
$unlinkedSummary = $unlinkedResult['summary'] ?? [];
$unlinkedYears = $unlinkedResult['available_years'] ?? [];
$reportTitle = 'Unlinked Payments Audit Ledger';
