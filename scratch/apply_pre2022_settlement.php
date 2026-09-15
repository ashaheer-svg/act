<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "Before update:\n";
$before = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as inv_c,
        COUNT(DISTINCT customer_name) as cust_c,
        SUM(total_amount) as total_unpaid
    FROM sales 
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date <= '2021-12-31'
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);
print_r($before);

$pdo->exec("
    UPDATE sales 
    SET paid_date = invoice_date,
        days_to_pay = 30
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date <= '2021-12-31'
");

echo "\nAfter update (remaining unpaid <= 2021-12-31):\n";
$after = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as inv_c,
        COUNT(DISTINCT customer_name) as cust_c,
        SUM(total_amount) as total_unpaid
    FROM sales 
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date <= '2021-12-31'
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);
print_r($after);

echo "\nActive Unpaid Invoices (Post-2021):\n";
$active = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as inv_c,
        COUNT(DISTINCT customer_name) as cust_c,
        SUM(total_amount) as total_unpaid,
        SUM(base_value) as total_base,
        SUM(vat_component) as total_vat
    FROM sales 
    WHERE (paid_date IS NULL OR paid_date = '')
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);
print_r($active);
