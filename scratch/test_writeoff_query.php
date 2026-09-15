<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$before = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as inv_c,
        COUNT(*) as line_c,
        ROUND(SUM(total_amount), 2) as gross,
        ROUND(SUM(base_value), 2) as base,
        ROUND(SUM(vat_component), 2) as vat
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND (customer_name = 'EITS' OR invoice_date < '2026-01-01')
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);

echo "Invoices to be written off / settled:\n";
print_r($before);

$remaining = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as inv_c,
        COUNT(*) as line_c,
        ROUND(SUM(total_amount), 2) as gross,
        MIN(invoice_date) as min_d,
        MAX(invoice_date) as max_d
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND NOT (customer_name = 'EITS' OR invoice_date < '2026-01-01')
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);

echo "\nRemaining Unpaid Invoices after write-off:\n";
print_r($remaining);
