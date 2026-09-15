<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/sales_bi.db');

echo "=== CHECKING SINOLOGY COUNTS ===\n";
$c1 = $pdo->query("SELECT count(*) FROM sales WHERE item_description LIKE '%sinology%'")->fetchColumn();
$c2 = $pdo->query("SELECT count(*) FROM invoice_items WHERE clean_product_name LIKE '%sinology%' OR brand_category = 'Other' AND clean_product_name LIKE '%synology%'")->fetchColumn();
$c3 = $pdo->query("SELECT count(*) FROM hardware_assets WHERE product_name LIKE '%sinology%' OR (brand = 'Other' AND product_name LIKE '%synology%')")->fetchColumn();

echo "sales matching sinology: $c1\n";
echo "invoice_items matching: $c2\n";
echo "hardware_assets matching: $c3\n";
