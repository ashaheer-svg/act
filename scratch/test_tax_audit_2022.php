<?php
require 'config.php';
require 'classes/Database.php';
require 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$res2022 = $reports->getTaxAuditReport('2022', 'all', 1, 3);
echo "=== 2022 TAX AUDIT REPORT ===\n";
print_r($res2022['summary']);
print_r($res2022['rows']);

$res2024 = $reports->getTaxAuditReport('2024', 'all', 1, 3);
echo "\n=== 2024 TAX AUDIT REPORT ===\n";
print_r($res2024['summary']);
print_r($res2024['rows']);

echo "\n=== DETAILS FOR AS010418 (sales table) ===\n";
print_r($db->fetchAll("SELECT invoice_number, invoice_date, customer_name, qb_amount, base_value, vat_component, total_amount, vat_treatment, sales_tax_total, sales_tax_rate, sales_tax_item, item_description FROM sales WHERE invoice_number = 'AS010418'"));

echo "\n=== DETAILS FOR AS010418 (invoice_items table) ===\n";
print_r($db->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, total_amount, base_value, vat_component, vat_treatment FROM invoice_items WHERE invoice_number = 'AS010418'"));


