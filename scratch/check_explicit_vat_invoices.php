<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$rows = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, item_description, qb_amount, base_value, vat_component, total_amount, vat_treatment, applied_tax_rate
    FROM sales 
    WHERE invoice_number IN (
        SELECT DISTINCT invoice_number 
        FROM sales 
        WHERE (
            item_description LIKE 'VAT%' 
            OR item_description LIKE '%Value Added Tax%'
            OR item_description LIKE '%18% VAT%'
            OR item_description LIKE '%15% VAT%'
            OR item_description LIKE '%12% VAT%'
            OR item_description LIKE '%8% VAT%'
        )
    )
    ORDER BY invoice_number, id
");

foreach ($rows as $r) {
    echo "{$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | desc: " . substr($r['item_description'], 0, 40) . " | qb: {$r['qb_amount']} | base: {$r['base_value']} | vat: {$r['vat_component']} | tot: {$r['total_amount']}\n";
}
