<?php
$db = new PDO('sqlite:data/sales_bi.db');
$stmt = $db->query("SELECT id, pattern, canonical_name, brand, product_category, commercial_type FROM product_mappings LIMIT 15");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($r) . "\n";
}
