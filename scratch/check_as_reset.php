<?php
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(__DIR__ . '/../data/sales_bi.db');

$r = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, qb_amount FROM sales WHERE invoice_number IN ('AS000001', 'AS000002', 'AS000003', 'AS000010')");
print_r($r);
