<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$summary = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as unpaid_invoices_count,
        COUNT(DISTINCT customer_name) as customers_count,
        SUM(total_amount) as total_unpaid_gross,
        SUM(base_value) as total_unpaid_base,
        SUM(vat_component) as total_unpaid_vat
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);

print_r($summary);

// Sample customers with unpaid invoices
$sample = $pdo->query("
    SELECT 
        customer_name,
        COUNT(DISTINCT invoice_number) as invoices_count,
        SUM(total_amount) as total_due,
        MIN(invoice_date) as oldest_invoice_date,
        MAX(invoice_date) as newest_invoice_date
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND total_amount > 0
    GROUP BY customer_name
    ORDER BY customer_name ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

print_r($sample);
