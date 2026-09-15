<?php
require_once 'config.php';
$db = new PDO('sqlite:' . DATABASE_PATH);

$stmt = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='product_mappings'");
if ($stmt->fetchColumn() > 0) {
    $m = $db->query("SELECT * FROM product_mappings WHERE raw_description LIKE '%Sinology%' OR clean_name LIKE '%Sinology%'")->fetchAll(PDO::FETCH_ASSOC);
    echo "product_mappings matches: " . count($m) . "\n";
} else {
    echo "No product_mappings table\n";
}
