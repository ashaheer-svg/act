<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$tables = ['sales', 'hardware_assets', 'invoice_items', 'software_subscriptions', 'product_mappings', 'customer_profiles'];
$words = [
    'Sinology', 'Snology', 'sinology',
    'BECOME', 'Become',
    'Macronis', 'macronis', 'Acronic', 'acronic'
];

echo "=== THOROUGH SEARCH ACROSS ALL TABLES ===\n";
foreach ($tables as $t) {
    echo "\nTable: $t\n";
    $cols = $db->fetchAll("PRAGMA table_info($t)");
    $textCols = [];
    foreach ($cols as $col) {
        if (stripos($col['type'], 'TEXT') !== false || stripos($col['type'], 'VARCHAR') !== false || empty($col['type'])) {
            $textCols[] = $col['name'];
        }
    }
    
    foreach ($words as $w) {
        $conditions = [];
        $params = [];
        foreach ($textCols as $col) {
            $conditions[] = "\"$col\" LIKE ?";
            $params[] = "%$w%";
        }
        if (empty($conditions)) continue;
        $sql = "SELECT COUNT(*) as c FROM $t WHERE " . implode(' OR ', $conditions);
        $res = $db->fetch($sql, $params)['c'];
        if ($res > 0) {
            echo "  Matched '$w': $res rows\n";
        }
    }
}
