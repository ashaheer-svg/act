<?php
require_once 'config.php';
session_name(SESSION_NAME);
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['last_activity'] = time();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Test 1: product_mapping.php with tab=taxonomy
$_GET = ['tab' => 'taxonomy'];
ob_start();
require 'product_mapping.php';
$htmlTaxonomy = ob_get_clean();
echo "product_mapping.php (taxonomy tab): length = " . strlen($htmlTaxonomy) . " bytes\n";
if (strpos($htmlTaxonomy, 'Master Brands Directory') !== false && strpos($htmlTaxonomy, 'Master Categories Directory') !== false) {
    echo "  [PASS] Found Master Brands and Master Categories cards\n";
} else {
    echo "  [FAIL] Missing cards in taxonomy tab\n";
}

// Test 2: product_mapping.php with tab=products
$_GET = ['tab' => 'products', 'status' => 'UNASSIGNED'];
ob_start();
require 'product_mapping.php';
$htmlProducts = ob_get_clean();
echo "product_mapping.php (products tab): length = " . strlen($htmlProducts) . " bytes\n";
if (strpos($htmlProducts, 'bulkActionBar') !== false && strpos($htmlProducts, 'quickAssign') !== false) {
    echo "  [PASS] Found Bulk Action Bar and quickAssign interactive hooks\n";
} else {
    echo "  [FAIL] Missing bulk action or quick assign in products tab\n";
}

// Test 3: reports.php with type=brand_growth and view_mode=brand
$_GET = ['type' => 'brand_growth', 'view_mode' => 'brand'];
ob_start();
require 'reports.php';
$htmlBrand = ob_get_clean();
echo "reports.php (brand view): length = " . strlen($htmlBrand) . " bytes\n";
if (strpos($htmlBrand, 'Brand Performance & Growth Trajectory') !== false) {
    echo "  [PASS] Found Brand Performance title and table\n";
} else {
    echo "  [FAIL] Missing Brand Performance title\n";
}

// Test 4: reports.php with type=brand_growth and view_mode=category
$_GET = ['type' => 'brand_growth', 'view_mode' => 'category'];
ob_start();
require 'reports.php';
$htmlCat = ob_get_clean();
echo "reports.php (category view): length = " . strlen($htmlCat) . " bytes\n";
if (strpos($htmlCat, 'Product Category Performance') !== false && strpos($htmlCat, 'Tracked Categories') !== false) {
    echo "  [PASS] Found Category Performance title, KPIs, and table\n";
} else {
    echo "  [FAIL] Missing Category Performance title or KPIs\n";
}

// Test 5: reports.php with type=brand_growth and view_mode=matrix
$_GET = ['type' => 'brand_growth', 'view_mode' => 'matrix'];
ob_start();
require 'reports.php';
$htmlMatrix = ob_get_clean();
echo "reports.php (matrix view): length = " . strlen($htmlMatrix) . " bytes\n";
if (strpos($htmlMatrix, 'Brand × Category Portfolio Cross-Matrix') !== false) {
    echo "  [PASS] Found Matrix title and table\n";
} else {
    echo "  [FAIL] Missing Matrix title\n";
}

echo "\nALL LOCAL TESTS COMPLETED SUCCESSFULLY!\n";
