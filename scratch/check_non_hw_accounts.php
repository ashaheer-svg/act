<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$zeroHwAccounts = ['MUSLIM AID', 'AL - ARAF HOTELS & RESORT (PVT) LTD'];

foreach ($zeroHwAccounts as $acc) {
    echo "=== ACCOUNT: $acc ===\n";
    $items = $db->query("
        SELECT ii.invoice_number, ii.clean_product_name, ii.product_type, ii.brand_category, ii.quantity, ii.unit_price, ii.total_amount, ii.vat_treatment
        FROM invoice_items ii
        WHERE ii.customer_name LIKE " . $db->quote($acc . '%') . "
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $it) {
        echo "  Inv #{$it['invoice_number']} | Type: {$it['product_type']} | Brand: {$it['brand_category']} | Qty: {$it['quantity']} | Total: LKR " . number_format($it['total_amount'], 2) . " [{$it['vat_treatment']}]\n";
        echo "    Product: {$it['clean_product_name']}\n";
    }
}
