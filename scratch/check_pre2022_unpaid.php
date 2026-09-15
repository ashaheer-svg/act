<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$pre2022 = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as inv_c,
        COUNT(DISTINCT customer_name) as cust_c,
        SUM(total_amount) as total_amount
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date <= '2021-12-31'
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);

echo "Invoices <= 2021-12-31 currently unpaid:\n";
print_r($pre2022);

$post2021 = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as inv_c,
        COUNT(DISTINCT customer_name) as cust_c,
        SUM(total_amount) as total_amount
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date > '2021-12-31'
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);

echo "\nInvoices > 2021-12-31 currently unpaid:\n";
print_r($post2021);
