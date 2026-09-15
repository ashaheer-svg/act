<?php
require_once 'config.php';
$db = new PDO('sqlite:' . DATABASE_PATH);

$stmt = $db->query("SELECT invoice_number, product_name, brand, model_sku, serial_number FROM hardware_assets WHERE product_name LIKE '%Sinology%' OR brand LIKE '%Sinology%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total hardware_assets matching 'Sinology': " . count($rows) . "\n\n";
foreach (array_slice($rows, 0, 10) as $r) {
    echo "Invoice: {$r['invoice_number']} | Brand: {$r['brand']} | Product: {$r['product_name']} | S/N: {$r['serial_number']}\n";
}
