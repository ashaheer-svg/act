<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/data/sales_bi.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();
try {
    $s1 = $pdo->exec("UPDATE sales SET item_description = replace(item_description, 'Sinology', 'Synology') WHERE item_description LIKE '%Sinology%'");
    $s2 = $pdo->exec("UPDATE sales SET item_description = replace(item_description, 'sinology', 'Synology') WHERE item_description LIKE '%sinology%'");

    $i1 = $pdo->exec("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'Sinology', 'Synology'), brand_category = 'Synology' WHERE clean_product_name LIKE '%Sinology%' OR clean_product_name LIKE '%sinology%'");

    $h1 = $pdo->exec("UPDATE hardware_assets SET product_name = replace(product_name, 'Sinology', 'Synology'), brand = 'Synology' WHERE product_name LIKE '%Sinology%' OR product_name LIKE '%sinology%'");

    $pdo->commit();

    $postSales = (int)$pdo->query("SELECT count(*) FROM sales WHERE item_description LIKE '%sinology%'")->fetchColumn();
    $postItems = (int)$pdo->query("SELECT count(*) FROM invoice_items WHERE clean_product_name LIKE '%sinology%'")->fetchColumn();
    $postHw = (int)$pdo->query("SELECT count(*) FROM hardware_assets WHERE product_name LIKE '%sinology%'")->fetchColumn();
    $postSynBrandHw = (int)$pdo->query("SELECT count(*) FROM hardware_assets WHERE brand = 'Synology'")->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'sales_updated' => ($s1 + $s2),
        'invoice_items_updated' => $i1,
        'hardware_assets_updated' => $h1,
        'post_sales_sinology_remaining' => $postSales,
        'post_invoice_items_sinology_remaining' => $postItems,
        'post_hardware_sinology_remaining' => $postHw,
        'post_synology_hardware_assets_count' => $postSynBrandHw
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json', true, 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

@unlink(__FILE__);
