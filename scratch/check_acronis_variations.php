<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== SEARCHING DATABASE FOR MISSPELLINGS ===\n";

$queries = [
    'Acronic' => "SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE '%Acronic%'",
    'Macron' => "SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE '%Macron%'",
    'Sinology' => "SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE '%Sinology%'",
    'Snology' => "SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE '%Snology%'",
    'BECOME' => "SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE '%BECOME%'",
    'SBDCOM' => "SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE '%SBDCOM%'"
];

foreach ($queries as $label => $sql) {
    $rows = $db->fetchAll($sql);
    echo "\n--- $label (Count: " . count($rows) . ") ---\n";
    foreach (array_slice($rows, 0, 5) as $r) {
        echo "  [{$r['invoice_number']}] {$r['invoice_date']}: {$r['item_description']}\n";
    }
}
