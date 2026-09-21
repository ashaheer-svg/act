<?php
/**
 * Report Controller: Brand & Product Category Performance & Growth Trajectory
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$viewMode = $_GET['view_mode'] ?? 'brand';
if (!in_array($viewMode, ['brand', 'category', 'matrix'])) $viewMode = 'brand';
$catFilter = $_GET['category'] ?? null;
$brandFilter = $_GET['brand'] ?? null;
$bgFilters = [
    'brand' => $brandFilter,
    'category' => $catFilter,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null
];

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    $out = fopen('php://output', 'w');

    if ($viewMode === 'category') {
        $res = $reports->getCategoryPerformanceReport($bgFilters);
        header('Content-Disposition: attachment; filename=category_performance_' . date('Ymd_His') . '.csv');
        fputcsv($out, ['Category Name', 'Invoices', 'Client Reach', 'Units Sold', 'Lifetime Base (LKR)', 'Lifetime VAT (LKR)', 'Lifetime Gross (LKR)', 'Historical Pre-2023 (LKR)', 'Modern Post-2023 (LKR)', 'Avg Yield (LKR)', 'Revenue Share %', 'Growth Trajectory']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['category_name'], $r['total_invoices'], $r['client_reach'], $r['total_units_sold'], $r['lifetime_base_revenue'], $r['lifetime_vat_revenue'], $r['lifetime_gross_revenue'], $r['historical_pre_2023'], $r['modern_post_2023'], $r['avg_line_yield'], $r['revenue_share_pct'], $r['growth_trajectory']]);
        }
    } elseif ($viewMode === 'matrix') {
        $matrix = $reports->getBrandCategoryMatrixReport($bgFilters);
        header('Content-Disposition: attachment; filename=brand_category_matrix_' . date('Ymd_His') . '.csv');
        fputcsv($out, ['Brand', 'Category', 'Invoices', 'Units Sold', 'Gross Revenue (LKR)']);
        foreach ($matrix as $m) {
            fputcsv($out, [$m['brand_name'], $m['category_name'], $m['invoice_count'], $m['units_sold'], $m['gross_revenue']]);
        }
    } else {
        $res = $reports->getBrandGrowthReport($bgFilters);
        header('Content-Disposition: attachment; filename=brand_performance_' . date('Ymd_His') . '.csv');
        fputcsv($out, ['Brand Name', 'Invoices', 'Client Reach', 'Units Sold', 'Lifetime Base (LKR)', 'Lifetime VAT (LKR)', 'Lifetime Gross (LKR)', 'Historical Pre-2023 (LKR)', 'Modern Post-2023 (LKR)', 'Avg Order Yield (LKR)', 'Revenue Share %', 'Growth Trajectory']);
        foreach ($res['rows'] as $r) {
            fputcsv($out, [$r['brand_name'], $r['total_invoices'], $r['client_reach'], $r['total_units_sold'], $r['lifetime_base_revenue'], $r['lifetime_vat_revenue'], $r['lifetime_gross_revenue'], $r['historical_pre_2023'], $r['modern_post_2023'], $r['avg_line_yield'], $r['revenue_share_pct'], $r['growth_trajectory']]);
        }
    }
    fclose($out);
    exit;
}

// Data Preparation
$brandGrowthResult = $reports->getBrandGrowthReport($bgFilters);
$brandGrowthData = $brandGrowthResult['rows'];
$brandGrowthSummary = $brandGrowthResult['summary'];

$categoryPerfResult = $reports->getCategoryPerformanceReport($bgFilters);
$categoryPerfData = $categoryPerfResult['rows'];
$categoryPerfSummary = $categoryPerfResult['summary'];

$matrixData = ($viewMode === 'matrix') ? $reports->getBrandCategoryMatrixReport($bgFilters) : [];

$masterBrandsList = $db->getBrands(true);
$masterCategoriesList = $db->getCategories(true);

if ($viewMode === 'category') {
    $reportTitle = 'Product Category Performance & Growth Trajectory (2009–2026)';
} elseif ($viewMode === 'matrix') {
    $reportTitle = 'Brand × Category Portfolio Cross-Matrix';
} else {
    $reportTitle = 'Brand Performance & Growth Trajectory (2009–2026)';
}
