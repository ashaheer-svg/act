<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== Searching sales table for 64 and 72 ===\n";
$invoices = $db->fetchAll("SELECT DISTINCT invoice_number FROM sales WHERE invoice_number LIKE '%64%' OR invoice_number LIKE '%72%' LIMIT 30");
foreach ($invoices as $inv) {
    echo "Found invoice_number: {$inv['invoice_number']}\n";
}

echo "\n=== Let's check exact matches for AS0000064 and AS0000072 or variations ===\n";
$targets = ['AS0000064', 'AS0000072', 'AS000064', 'AS000072', 'AS00064', 'AS00072', '64', '72'];
foreach ($targets as $t) {
    $rows = $db->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, item_description, quantity, base_value, vat_component, applied_tax_rate, total_amount, memo FROM sales WHERE invoice_number = ?", [$t]);
    if (!empty($rows)) {
        echo "\n>>> MATCH for '$t' (count: " . count($rows) . "):\n";
        foreach ($rows as $r) {
            echo "  ID: {$r['id']} | Date: {$r['invoice_date']} | Cust: {$r['customer_name']}\n";
            echo "  Item: {$r['item_description']}\n";
            echo "  Qty: {$r['quantity']} | Base: {$r['base_value']} | VAT: {$r['vat_component']} | Rate: {$r['applied_tax_rate']} | Total: {$r['total_amount']}\n";
            echo "  Memo: {$r['memo']}\n";
        }
    }
}

echo "\n=== Let's check invoice_items table for these ===\n";
foreach ($targets as $t) {
    $items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_number = ?", [$t]);
    if (!empty($items)) {
        echo "\n>>> invoice_items MATCH for '$t' (count: " . count($items) . "):\n";
        foreach ($items as $it) {
            echo "  Item: {$it['clean_product_name']} | Base: {$it['base_value']} | VAT: {$it['vat_component']} | Total: {$it['total_amount']} | Treatment: {$it['vat_treatment']} | Rate: {$it['applied_tax_rate']}\n";
            echo "  Raw desc: {$it['item_description']}\n";
        }
    }
}
