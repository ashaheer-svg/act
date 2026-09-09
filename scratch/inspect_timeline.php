<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== Taxable Sales with total_amount > 0 by month ===\n";
$rows = $db->fetchAll("
    SELECT strftime('%Y-%m', invoice_date) as ym, 
           tax_code,
           COUNT(*) as line_count,
           SUM(total_amount) as total_sum
    FROM sales
    WHERE total_amount > 0
    GROUP BY ym, tax_code
    ORDER BY ym ASC, tax_code ASC
");
foreach ($rows as $r) {
    echo sprintf("%-8s | %-15s | %5d lines | %14.2f\n", $r['ym'], $r['tax_code'], $r['line_count'], $r['total_sum']);
}
