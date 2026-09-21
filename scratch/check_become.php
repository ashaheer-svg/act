<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$rows = $db->fetchAll("SELECT invoice_number, product_name, brand, model_sku, serial_number FROM hardware_assets WHERE product_name LIKE '%BECOME%' OR brand LIKE '%BECOME%' LIMIT 10");
foreach ($rows as $r) {
    echo "{$r['invoice_number']} | Brand: {$r['brand']} | Product: {$r['product_name']} | SKU: {$r['model_sku']} | S/N: {$r['serial_number']}\n";
}
