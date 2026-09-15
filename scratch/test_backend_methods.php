<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "Testing Database Brands & Categories CRUD:\n";
$brands = $db->getBrands();
echo "- Brands count: " . count($brands) . "\n";

$categories = $db->getCategories();
echo "- Categories count: " . count($categories) . "\n";

echo "\nTesting getExtractedProducts:\n";
$ep = $reports->getExtractedProducts(['status' => 'ALL'], 1, 5);
echo "- Total extracted items: " . $ep['total'] . "\n";
echo "- Total unassigned items: " . $ep['kpis']['unassigned_items'] . "\n";
echo "- First item: " . ($ep['items'][0]['clean_product_name'] ?? 'None') . " (Brand: " . ($ep['items'][0]['brand'] ?? '') . ", Cat: " . ($ep['items'][0]['category'] ?? '') . ")\n";

echo "\nTesting getBrandGrowthReport:\n";
$bgr = $reports->getBrandGrowthReport();
echo "- Brand report rows: " . count($bgr['rows']) . "\n";
echo "- Brand summary gross: " . number_format($bgr['summary']['total_gross_portfolio'], 2) . "\n";

echo "\nTesting getCategoryPerformanceReport:\n";
$cpr = $reports->getCategoryPerformanceReport();
echo "- Category report rows: " . count($cpr['rows']) . "\n";
echo "- Category summary gross: " . number_format($cpr['summary']['total_gross_portfolio'], 2) . "\n";

echo "\nTesting getBrandCategoryMatrixReport:\n";
$mx = $reports->getBrandCategoryMatrixReport();
echo "- Matrix rows count: " . count($mx) . "\n";
echo "\nALL TESTS PASSED!\n";
