<?php
/**
 * Report Controller: Hardware Warranty & Maintenance Contract Lookup
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->lookupWarrantySerial($_GET['search'] ?? '', $_GET['status'] ?? 'all', 10000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=warranty_serial_lookup_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Serial Number', 'Product Name', 'Brand', 'Model / SKU', 'Customer', 'Warranty Months', 'Start Date', 'Expiry Date', 'Status', 'Days Remaining / Expired', 'Invoices Count', 'Invoices', 'Maintenance Contracts Count']);
    foreach ($res as $r) {
        $invNumbers = implode('; ', array_column($r['invoices'], 'invoice_number'));
        fputcsv($out, [
            $r['serial_number'],
            $r['product_name'],
            $r['brand'],
            $r['model_sku'],
            $r['current_customer'],
            $r['warranty_months'],
            $r['latest_start_date'] ?: $r['initial_start_date'],
            $r['computed_expiry_date'],
            $r['computed_status'],
            $r['days_diff'],
            count($r['invoices']),
            $invNumbers,
            count($r['maintenance_contracts'])
        ]);
    }
    fclose($out);
    exit;
}

// Data Preparation
$warrantyStatus = $_GET['status'] ?? 'all';
$warrantySearch = $_GET['search'] ?? '';
$warrantyLimit = max(10, min(100, (int)($_GET['limit'] ?? 50)));
$warrantyKpis = $reports->getWarrantySummaryMetrics();
$warrantyResults = $reports->lookupWarrantySerial($warrantySearch, $warrantyStatus, $warrantyLimit);
$reportTitle = 'Hardware Warranty & Maintenance Contract Lookup';
