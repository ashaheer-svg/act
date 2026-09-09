<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

$rows = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, SUM(total_amount) as tot, memo FROM sales WHERE invoice_number LIKE 'AS0000%' GROUP BY invoice_number ORDER BY invoice_date DESC, invoice_number DESC LIMIT 30");
echo "Recent AS0000xx invoices:\n";
foreach ($rows as $r) {
    echo "  {$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | Total: {$r['tot']} | Memo: {$r['memo']}\n";
}

$oldest = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, SUM(total_amount) as tot, memo FROM sales WHERE invoice_number LIKE 'AS0000%' GROUP BY invoice_number ORDER BY invoice_date ASC, invoice_number ASC LIMIT 10");
echo "\nOldest AS0000xx invoices:\n";
foreach ($oldest as $r) {
    echo "  {$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | Total: {$r['tot']} | Memo: {$r['memo']}\n";
}
