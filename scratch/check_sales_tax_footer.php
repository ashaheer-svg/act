<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$c = $db->fetch("SELECT COUNT(*) as cnt FROM sales WHERE sales_tax_total > 0");
echo "Rows with sales_tax_total > 0: " . $c['cnt'] . "\n";

$c2 = $db->fetch("SELECT COUNT(DISTINCT invoice_number) as cnt FROM sales WHERE sales_tax_total > 0");
echo "Unique invoices with sales_tax_total > 0: " . $c2['cnt'] . "\n";

$c3 = $db->fetch("SELECT COUNT(DISTINCT invoice_number) as cnt FROM sales WHERE sales_tax_item = 'VAT'");
echo "Unique invoices with sales_tax_item = 'VAT': " . $c3['cnt'] . "\n";

$sample = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, subtotal, sales_tax_total, sales_tax_rate, sales_tax_item, qb_amount, base_value, vat_component, total_amount, vat_treatment FROM sales WHERE sales_tax_total > 0 LIMIT 5");
foreach ($sample as $s) {
    echo "  [{$s['invoice_number']}] Subtotal: {$s['subtotal']}, Tax: {$s['sales_tax_total']}, Rate: {$s['sales_tax_rate']}, Item: {$s['sales_tax_item']}, QB_Amt: {$s['qb_amount']}, Base: {$s['base_value']}, VAT: {$s['vat_component']}, Total: {$s['total_amount']}, Treatment: {$s['vat_treatment']}\n";
}
