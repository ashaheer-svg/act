<?php
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(__DIR__ . '/../data/sales_bi.db');

$r = $db->fetch("SELECT MIN(invoice_number) as min_inv, MAX(invoice_number) as max_inv, COUNT(DISTINCT invoice_number) as cnt FROM sales WHERE invoice_date >= '2026-07-01'");
echo "July 2026+ Invoices:\n";
print_r($r);

$samples = $db->fetchAll("SELECT DISTINCT invoice_number, invoice_date, customer_name FROM sales WHERE invoice_date >= '2026-07-01' ORDER BY invoice_date ASC, invoice_number ASC LIMIT 10");
echo "Sample Recent Invoices:\n";
foreach ($samples as $s) {
    echo "  {$s['invoice_number']} | {$s['invoice_date']} | {$s['customer_name']}\n";
}
