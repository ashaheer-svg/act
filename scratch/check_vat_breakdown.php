<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

$explicit = $db->fetchAll("
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
");
echo "Invoices with explicit VAT line in sales: " . count($explicit) . "\n";

$rates = $db->fetchAll("SELECT applied_tax_rate, vat_treatment, COUNT(*) as cnt, ROUND(SUM(total_amount), 2) as sum_total, ROUND(SUM(base_value), 2) as sum_base, ROUND(SUM(vat_component), 2) as sum_vat FROM sales GROUP BY applied_tax_rate, vat_treatment");
echo "\n--- Sales breakdown by applied_tax_rate and vat_treatment ---\n";
foreach ($rates as $r) {
    echo "Rate: {$r['applied_tax_rate']} | Treat: {$r['vat_treatment']} | Rows: {$r['cnt']} | Base: {$r['sum_base']} | VAT: {$r['sum_vat']} | Total: {$r['sum_total']}\n";
}

// Check sample where base + vat != total
$diff = $db->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, qb_amount, base_value, vat_component, total_amount, applied_tax_rate FROM sales WHERE ABS((base_value + vat_component) - total_amount) > 0.05 LIMIT 10");
echo "\nRows where base + vat != total: " . count($diff) . "\n";
foreach ($diff as $d) {
    echo "  [{$d['invoice_number']}] {$d['invoice_date']}: qb={$d['qb_amount']}, base={$d['base_value']}, vat={$d['vat_component']}, total={$d['total_amount']}\n";
}

// Check rows where total_amount != qb_amount
$inflated = $db->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, qb_amount, base_value, vat_component, total_amount, applied_tax_rate FROM sales WHERE qb_amount != 0 AND ABS(total_amount - qb_amount) > 0.05 LIMIT 10");
echo "\nRows where total_amount != qb_amount (inflated invoices): " . count($inflated) . "\n";
foreach ($inflated as $inf) {
    echo "  [{$inf['invoice_number']}] {$inf['invoice_date']}: qb={$inf['qb_amount']}, base={$inf['base_value']}, vat={$inf['vat_component']}, total={$inf['total_amount']}\n";
}
