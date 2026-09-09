<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

echo "=== CATEGORY SAMPLES ===\n";
$types = ['HARDWARE', 'SERVICE', 'SOFTWARE', 'MAINTENANCE', 'ACCESSORY', 'TAX_LEVY', 'DISCOUNT'];
foreach ($types as $t) {
    $rows = $db->query("SELECT invoice_number, customer_name, clean_product_name, quantity, total_amount FROM invoice_items WHERE product_type = '$t' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTYPE: $t (" . count($rows) . " shown):\n";
    foreach ($rows as $r) {
        echo "  • [{$r['invoice_number']}] {$r['customer_name']} | {$r['clean_product_name']} | Qty: {$r['quantity']} | LKR " . number_format($r['total_amount'], 2) . "\n";
    }
}
