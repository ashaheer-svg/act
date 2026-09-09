<?php
require 'classes/Database.php';
$db = new Database('data/sales_bi.db');
$rows = $db->fetchAll("SELECT id, invoice_number, customer_name, item_description, quantity, unit_price, qb_amount, total_amount, base_value, vat_component, tax_code, vat_treatment FROM sales WHERE invoice_number IN ('AS000064', 'AS000072')");
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
