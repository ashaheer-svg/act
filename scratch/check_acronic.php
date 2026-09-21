<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$rows = $db->fetchAll("SELECT id, invoice_number, invoice_date, item_description FROM sales WHERE item_description LIKE '%acronic%' OR item_description LIKE '%Acronic%' LIMIT 10");
echo "Total rows matching 'Acronic': " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [{$r['invoice_number']}] {$r['item_description']}\n";
}
