<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$pdo = $db->getConnection();

echo "=== PRE-NORMALIZATION STATUS ===\n";
$sPre = $pdo->query("SELECT count(*) FROM sales WHERE item_description LIKE '%sinology%'")->fetchColumn();
$iPre = $pdo->query("SELECT count(*) FROM invoice_items WHERE clean_product_name LIKE '%sinology%'")->fetchColumn();
$hPre = $pdo->query("SELECT count(*) FROM hardware_assets WHERE product_name LIKE '%sinology%'")->fetchColumn();
$hSynPre = $pdo->query("SELECT count(*) FROM hardware_assets WHERE brand = 'Synology'")->fetchColumn();
$hOthPre = $pdo->query("SELECT count(*) FROM hardware_assets WHERE brand = 'Other'")->fetchColumn();

echo "sales sinology: $sPre\n";
echo "invoice_items sinology: $iPre\n";
echo "hardware_assets sinology: $hPre\n";
echo "hardware_assets brand Synology: $hSynPre, Other: $hOthPre\n";

$pdo->beginTransaction();
try {
    // 1. Sales table
    $stmt1 = $pdo->prepare("UPDATE sales SET item_description = replace(item_description, 'Sinology', 'Synology') WHERE item_description LIKE '%Sinology%'");
    $stmt1->execute();
    $sRows1 = $stmt1->rowCount();

    $stmt1b = $pdo->prepare("UPDATE sales SET item_description = replace(item_description, 'sinology', 'Synology') WHERE item_description LIKE '%sinology%'");
    $stmt1b->execute();
    $sRows2 = $stmt1b->rowCount();

    // 2. Invoice Items
    $stmt2 = $pdo->prepare("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'Sinology', 'Synology'), brand_category = 'Synology' WHERE clean_product_name LIKE '%Sinology%' OR clean_product_name LIKE '%sinology%'");
    $stmt2->execute();
    $iRows = $stmt2->rowCount();

    // 3. Hardware Assets
    $stmt3 = $pdo->prepare("UPDATE hardware_assets SET product_name = replace(product_name, 'Sinology', 'Synology'), brand = 'Synology' WHERE product_name LIKE '%Sinology%' OR product_name LIKE '%sinology%'");
    $stmt3->execute();
    $hRows = $stmt3->rowCount();

    $pdo->commit();

    echo "\n=== MIGRATION APPLIED SUCCESSFULLY ===\n";
    echo "sales rows updated: " . ($sRows1 + $sRows2) . "\n";
    echo "invoice_items rows updated: $iRows\n";
    echo "hardware_assets rows updated: $hRows\n";

    $sPost = $pdo->query("SELECT count(*) FROM sales WHERE item_description LIKE '%sinology%'")->fetchColumn();
    $iPost = $pdo->query("SELECT count(*) FROM invoice_items WHERE clean_product_name LIKE '%sinology%'")->fetchColumn();
    $hPost = $pdo->query("SELECT count(*) FROM hardware_assets WHERE product_name LIKE '%sinology%'")->fetchColumn();
    $hSynPost = $pdo->query("SELECT count(*) FROM hardware_assets WHERE brand = 'Synology'")->fetchColumn();

    echo "POST sales sinology: $sPost\n";
    echo "POST invoice_items sinology: $iPost\n";
    echo "POST hardware_assets sinology: $hPost\n";
    echo "POST hardware_assets brand Synology: $hSynPost\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
