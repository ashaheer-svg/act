<?php
$db = new PDO('sqlite:data/sales_bi.db');
$r = $db->query("SELECT COUNT(DISTINCT invoice_number) as distinct_invoices, COUNT(*) as rows_count, COUNT(DISTINCT customer_name) as customers_count, SUM(total_amount) as total_amount FROM sales WHERE invoice_type = 'Invoice' AND (paid_date IS NULL OR paid_date = '') AND total_amount > 0 AND invoice_date > '2021-12-31'")->fetch(PDO::FETCH_ASSOC);
print_r($r);
