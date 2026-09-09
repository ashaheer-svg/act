<?php
require 'classes/Database.php';
$db = new Database('data/sales_bi.db');
$row = $db->fetch("
    SELECT s.invoice_number, s.customer_name, s.total_amount, s.base_value, s.vat_component, s.vat_treatment, cp.is_vat_registered, cp.tin_number, cp.vat_number
    FROM sales s
    JOIN customer_profiles cp ON s.customer_name = cp.customer_name
    WHERE cp.is_vat_registered = 0 AND s.total_amount > 10000 AND s.tax_code != 'Non' AND s.tax_code != 'Zero'
    LIMIT 1
");
echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
