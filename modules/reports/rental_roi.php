<?php
/**
 * Report Controller: Rental Fleet Utilization & Commercial Yield
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $reports->getRentalROIReport(['search' => $_GET['search'] ?? '', 'status' => $_GET['status'] ?? 'all'], 1, 5000);
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rental_fleet_roi_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice #', 'Invoice Date', 'Customer Name', 'Product Description', 'Brand', 'Qty', 'Unit Price', 'Base Net (LKR)', '18% VAT (LKR)', 'Gross (LKR)', 'Days Since Billed', 'Rental Status', 'Serials']);
    foreach ($res['rows'] as $r) {
        fputcsv($out, [$r['invoice_number'], $r['invoice_date'], $r['customer_name'], $r['clean_product_name'], $r['brand_category'], $r['quantity'], $r['unit_price'], $r['base_value'], $r['vat_component'], $r['total_amount'], $r['days_since_billed'], $r['rental_status'], $r['serial_numbers']]);
    }
    fclose($out);
    exit;
}

// Data Preparation
list($p, $limit, $isAll) = getReportPaginationParams(25);
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$rentalFilters = [
    'search' => $search,
    'status' => $status
];
$rentalResult = $reports->getRentalROIReport($rentalFilters, $p, $limit);
$rentalData = $rentalResult['rows'];
$rentalTotal = $rentalResult['total'];
$rentalPages = $rentalResult['pages'];
$rentalSummary = $rentalResult['summary'];
$reportTitle = 'Rental Fleet Utilization & Commercial Yield';
