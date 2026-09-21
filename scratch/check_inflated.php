<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$rows = $db->fetchAll("SELECT invoice_number, invoice_date, base_value, vat_component, total_amount, qb_amount, sales_tax_total, vat_treatment FROM sales WHERE qb_amount != 0 AND ABS(total_amount - qb_amount) > 0.05 AND (sales_tax_total IS NULL OR sales_tax_total = 0) LIMIT 10");
echo "Sample inflated rows count: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "Inv {$r['invoice_number']} ({$r['invoice_date']}): base={$r['base_value']}, vat={$r['vat_component']}, total={$r['total_amount']}, qb={$r['qb_amount']}, st_total={$r['sales_tax_total']}, treatment={$r['vat_treatment']}\n";
}

$summary = $db->fetchAll("SELECT vat_treatment, count(*) as cnt FROM sales WHERE qb_amount != 0 AND ABS(total_amount - qb_amount) > 0.05 AND (sales_tax_total IS NULL OR sales_tax_total = 0) GROUP BY vat_treatment");
print_r($summary);
