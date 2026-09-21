<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = new Database(DATABASE_PATH);

// 1. Check brand spelling errors
$spellingErrors = 0;
$words = ['Sinology', 'Snology', 'sinology', 'BECOME', 'Become', 'become', 'Acronic', 'acronic', 'Macronis', 'macronis'];
foreach ($words as $w) {
    $c1 = (int)$db->fetch("SELECT count(*) as c FROM sales WHERE item_description LIKE ?", ["%$w%"])['c'];
    $c2 = (int)$db->fetch("SELECT count(*) as c FROM hardware_assets WHERE product_name LIKE ? OR brand LIKE ?", ["%$w%", "%$w%"])['c'];
    $c3 = (int)$db->fetch("SELECT count(*) as c FROM invoice_items WHERE clean_product_name LIKE ? OR brand_category LIKE ?", ["%$w%", "%$w%"])['c'];
    $spellingErrors += ($c1 + $c2 + $c3);
}

// 2. Inflated rows without tax footer
$inflated = (int)$db->fetch("SELECT count(*) as c FROM sales WHERE qb_amount != 0 AND ABS(total_amount - qb_amount) > 0.05 AND (sales_tax_total IS NULL OR sales_tax_total = 0)")['c'];

// 3. Mathematical diffs
$diffs = (int)$db->fetch("SELECT count(*) as c FROM sales WHERE ABS((base_value + vat_component) - total_amount) > 0.05")['c'];

// 4. Credited invoice AS008867
$as8867 = $db->fetch("SELECT invoice_number, total_amount, applied_amount, balance_remaining, is_paid FROM sales WHERE invoice_number = 'AS008867' LIMIT 1");

// 5. BDCOM hardware assets
$bdcomHw = (int)$db->fetch("SELECT count(*) as c FROM hardware_assets WHERE brand = 'BDCOM'")['c'];
$otherBdcom = (int)$db->fetch("SELECT count(*) as c FROM hardware_assets WHERE brand = 'Other' AND (product_name LIKE '%BDCOM%' OR model_sku LIKE '%S1500%')")['c'];

// 6. Manual VAT override column exists
$hasManualCol = false;
$cols = $db->fetchAll("PRAGMA table_info(sales)");
foreach ($cols as $col) {
    if ($col['name'] === 'manual_vat_override') {
        $hasManualCol = true;
        break;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'spelling_errors' => $spellingErrors,
    'inflated_rows_without_tax_footer' => $inflated,
    'math_diffs' => $diffs,
    'as008867' => $as8867,
    'bdcom_hardware_assets' => $bdcomHw,
    'other_bdcom_remaining' => $otherBdcom,
    'has_manual_vat_override_col' => $hasManualCol
], JSON_PRETTY_PRINT);

@unlink(__FILE__);
