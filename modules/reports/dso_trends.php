<?php
/**
 * Report Controller: Days Sales Outstanding (DSO) & Working Capital Analytics
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getDSOTrendsReport($year, ['search' => $_GET['search'] ?? '']);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=dso_working_capital_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Customer Name', 'Channel', 'Sales Rep', 'Invoices', 'Gross Billed (LKR)', 'Collected (LKR)', 'Outstanding (LKR)', 'Collection Rate %', 'Avg DSO (Days)', 'Max Delay (Days)', 'Risk Status']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [$r['customer_name'], $r['customer_type'] ?? '', $r['sales_rep'] ?? '', $r['invoice_count'], $r['gross_billed'], $r['collected_amount'], $r['outstanding_amount'], $r['collection_rate_pct'], $r['avg_dso_days'], $r['max_dso_days'], $r['risk_badge']]);
    }
    fclose($out);
    exit;
}

// Data Preparation
$search = $_GET['search'] ?? '';
$dsoResult = $reports->getDSOTrendsReport($year, ['search' => $search]);
$dsoData = $dsoResult['rows'];
$dsoSummary = $dsoResult['summary'];
$reportTitle = "Days Sales Outstanding (DSO) & Working Capital Analytics — $year";
