<?php
$db = new PDO('sqlite:data/sales_bi.db');
$row = $db->query("SELECT MIN(invoice_date) as min_date, MAX(invoice_date) as max_date, COUNT(*) as cnt FROM invoice_items")->fetch(PDO::FETCH_ASSOC);
echo "invoice_items: min={$row['min_date']}, max={$row['max_date']}, count={$row['cnt']}\n";

$row2 = $db->query("SELECT MIN(invoice_date) as min_date, MAX(invoice_date) as max_date, COUNT(*) as cnt FROM sales")->fetch(PDO::FETCH_ASSOC);
echo "sales: min={$row2['min_date']}, max={$row2['max_date']}, count={$row2['cnt']}\n";
