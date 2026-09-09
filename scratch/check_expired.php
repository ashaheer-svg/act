<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$expired = $db->query("
    SELECT invoice_number, customer_name, brand, model_sku, serial_number, warranty_months, warranty_expiry_date, warranty_status
    FROM hardware_assets
    WHERE warranty_status = 'EXPIRED'
")->fetchAll(PDO::FETCH_ASSOC);

echo "=== EXPIRED HARDWARE ASSETS (" . count($expired) . ") ===\n";
foreach ($expired as $e) {
    echo "  • [{$e['invoice_number']}] {$e['customer_name']} | {$e['brand']} {$e['model_sku']} | S/N: {$e['serial_number']} | Exp: {$e['warranty_expiry_date']} ({$e['warranty_months']}M)\n";
}
