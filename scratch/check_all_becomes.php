<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$rows = $db->fetchAll("SELECT id, invoice_number, invoice_date, item_description FROM sales WHERE item_description LIKE '%become%' OR item_description LIKE '%BECOME%'");
echo "Total rows matching 'become'/'BECOME': " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [{$r['invoice_number']}] {$r['item_description']}\n";
}
