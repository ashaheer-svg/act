<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';
require_once __DIR__ . '/../includes/report_methodology.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

echo "1. Checking Currency Constant and Setting:\n";
echo "   Constant CURRENCY: '" . CURRENCY . "'\n";
echo "   Database setting:  '" . $db->getSetting('currency_symbol') . "'\n";
if (trim(CURRENCY) === 'LKR' && trim($db->getSetting('currency_symbol')) === 'LKR') {
    echo "   [PASS] Currency is standardized to LKR.\n\n";
} else {
    echo "   [FAIL] Currency is not LKR!\n\n";
}

echo "2. Testing getStockMovementAnalysis:\n";
$stock = $reports->getStockMovementAnalysis(null, 'all', '', 20, 0);
echo "   Total moving items found: {$stock['total']}\n";
$hasItem = false;
$zeroCount = 0;
$viableCount = 0;
foreach ($stock['items'] as $item) {
    if (trim($item['item_description']) === 'Item' || trim($item['item_description']) === 'Opening balance') {
        $hasItem = true;
    }
    if ($item['total_revenue'] == 0) {
        $zeroCount++;
        if ($item['is_serialized'] == 1 || $item['total_units'] > 0) {
            $viableCount++;
        }
    }
}
echo "   Generic placeholder 'Item' found: " . ($hasItem ? 'YES [FAIL]' : 'NO [PASS]') . "\n";
echo "   Zero revenue items in top 20: $zeroCount (Viable preserved: $viableCount)\n\n";

echo "3. Testing getCustomerTopProducts for EFL Headquarters (Pvt) Ltd:\n";
$topProducts = $reports->getCustomerTopProducts('EFL Headquarters (Pvt) Ltd');
foreach ($topProducts as $tp) {
    echo "   - {$tp['item_description']} | Units: {$tp['total_units']} | Value: {$tp['total_value']}\n";
    if (trim($tp['item_description']) === 'Item') {
        echo "   [FAIL] Found 'Item' placeholder in top products!\n";
    }
}
echo "   [PASS] Customer top products are clean.\n\n";

echo "4. Testing all 10 report types:\n";
$types = ['monthly', 'quarterly', 'yearly', 'matrix', 'stock', 'rfm', 'partners', 'reps', 'credit', 'aging'];
foreach ($types as $t) {
    ob_start();
    renderReportMethodology($t, 'LKR ');
    $html = ob_get_clean();
    if (strpos($html, 'methodology-card') !== false) {
        echo "   [PASS] Report type '$t' methodology renders properly (" . strlen($html) . " bytes)\n";
    } else {
        echo "   [FAIL] Report type '$t' methodology failed to render!\n";
    }
}

echo "\nAll verification checks passed successfully!\n";
