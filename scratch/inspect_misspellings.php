<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== CHECKING MISSPELLINGS IN DATABASE ===\n";

$tables = ['sales', 'hardware_assets', 'software_subscriptions', 'product_mappings'];

foreach (['Sinology' => 'Synology', 'BECOME' => 'BDCOM', 'Macronis' => 'Acronis'] as $bad => $good) {
    echo "\n--- Searching for '$bad' (should be '$good') ---\n";
    
    // In sales
    $sRows = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE ? OR product_category LIKE ? LIMIT 5", ["%$bad%", "%$bad%"]);
    $sCount = $db->fetch("SELECT COUNT(*) as c FROM sales WHERE item_description LIKE ? OR product_category LIKE ?", ["%$bad%", "%$bad%"])['c'];
    echo "sales table: $sCount matching rows\n";
    foreach ($sRows as $r) {
        echo "  [{$r['invoice_number']}] {$r['invoice_date']}: {$r['item_description']}\n";
    }

    // In hardware_assets
    $hCount = $db->fetch("SELECT COUNT(*) as c FROM hardware_assets WHERE product_name LIKE ? OR brand LIKE ? OR model_sku LIKE ?", ["%$bad%", "%$bad%", "%$bad%"])['c'];
    echo "hardware_assets table: $hCount matching rows\n";

    // In software_subscriptions
    $subCount = $db->fetch("SELECT COUNT(*) as c FROM software_subscriptions WHERE software_name LIKE ? OR edition_tier LIKE ?", ["%$bad%", "%$bad%"])['c'];
    echo "software_subscriptions table: $subCount matching rows\n";

    // In product_mappings
    $pmCount = $db->fetch("SELECT COUNT(*) as c FROM product_mappings WHERE pattern LIKE ? OR canonical_name LIKE ? OR brand LIKE ?", ["%$bad%", "%$bad%", "%$bad%"])['c'];
    echo "product_mappings table: $pmCount matching rows\n";
}
