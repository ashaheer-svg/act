<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

$allPositiveVatLines = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, item_description, total_amount, qb_amount
    FROM sales 
    WHERE (
        item_description LIKE '%VAT%' 
        OR item_description LIKE '%Value Added Tax%'
    ) AND qb_amount > 0
");

echo "Total positive lines in sales containing 'VAT': " . count($allPositiveVatLines) . "\n";
foreach ($allPositiveVatLines as $l) {
    echo "  - [{$l['invoice_number']}] {$l['invoice_date']} | {$l['customer_name']} | Amt: {$l['qb_amount']} | Desc: {$l['item_description']}\n";
}
