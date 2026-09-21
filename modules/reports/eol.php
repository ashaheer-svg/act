<?php
/**
 * Report Controller: Hardware End-of-Life (EOL) & Refresh Forecast
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getHardwareEOLReport(['search' => $_GET['search'] ?? '', 'brand' => $brand, 'status' => $_GET['status'] ?? 'all'], 1, 5000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hardware_eol_forecast_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Serial Number', 'Product Model / SKU', 'Product Name', 'Brand', 'Customer Name', 'Invoice #', 'Warranty Months', 'Start Date', 'Expiry Date', 'Days Remaining', 'Fleet Status']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [$r['serial_number'], $r['model_sku'], $r['product_name'], $r['brand'], $r['customer_name'], $r['invoice_number'], $r['warranty_months'], $r['warranty_start_date'], $r['warranty_expiry_date'], $r['days_remaining'], $r['eol_status']]);
    }
    fclose($out);
    exit;
}

// Data Preparation
list($p, $limit, $isAll) = getReportPaginationParams(25);
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$eolFilters = [
    'search' => $search,
    'brand' => $brand,
    'status' => $status
];
$eolResult = $reports->getHardwareEOLReport($eolFilters, $p, $limit);
$eolData = $eolResult['rows'];
$eolTotal = $eolResult['total'];
$eolPages = $eolResult['pages'];
$eolSummary = $eolResult['summary'];
$reportTitle = 'Hardware End-of-Life (EOL) & Refresh Forecast';
