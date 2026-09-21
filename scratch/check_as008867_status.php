<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$inv = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, total_amount, applied_amount, balance_remaining, is_paid FROM sales WHERE invoice_number = 'AS008867'");
echo "Invoice AS008867 in sales:\n";
print_r($inv);

$pmts = $db->fetchAll("SELECT * FROM payments WHERE invoice_num = 'AS008867'");
echo "Payments for AS008867:\n";
print_r($pmts);
