<?php
require 'classes/Database.php';

$db = new Database('data/sales_bi.db');

// Sample diverse normalized items with their original sales description
$types = ['HARDWARE', 'SOFTWARE', 'MAINTENANCE', 'SERVICE', 'ACCESSORY'];
$items = [];
foreach ($types as $t) {
    $rows = $db->fetchAll("
        SELECT 
            ii.invoice_number,
            ii.clean_product_name,
            ii.brand_category,
            ii.product_type,
            ii.raw_line_ids,
            ii.quantity,
            ii.unit_price,
            ii.total_amount
        FROM invoice_items ii
        WHERE ii.product_type = ? AND ii.clean_product_name != ''
        GROUP BY ii.clean_product_name
        ORDER BY ii.id DESC
        LIMIT 4
    ", [$t]);
    $items = array_merge($items, $rows);
}

$results = [];
foreach ($items as $it) {
    $rawIds = json_decode($it['raw_line_ids'], true) ?: [];
    $rawTexts = [];
    if (!empty($rawIds)) {
        $placeholders = implode(',', array_fill(0, count($rawIds), '?'));
        $rawLines = $db->fetchAll("SELECT item_description FROM sales WHERE id IN ($placeholders) ORDER BY id ASC", $rawIds);
        foreach ($rawLines as $rl) {
            $desc = trim($rl['item_description'] ?? '');
            if (!empty($desc) && strcasecmp($desc, 'Item') !== 0) {
                $rawTexts[] = $desc;
            }
        }
    }
    
    $results[] = [
        'invoice' => $it['invoice_number'],
        'type' => $it['product_type'],
        'brand' => $it['brand_category'],
        'original' => implode(" \n+ [Child]: ", $rawTexts),
        'normalized' => $it['clean_product_name'],
        'qty' => $it['quantity'],
        'amount' => $it['total_amount']
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
