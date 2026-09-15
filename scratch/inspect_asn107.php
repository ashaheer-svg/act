<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$rows = $pdo->query("SELECT invoice_number, customer_name, invoice_date, base_value, vat_component, total_amount, vat_treatment, applied_tax_rate, qb_amount FROM sales WHERE invoice_number='ASN000107'")->fetchAll(PDO::FETCH_ASSOC);
echo "Sales rows for ASN000107:\n";
print_r($rows);

$items = $pdo->query("SELECT * FROM invoice_items WHERE invoice_number='ASN000107'")->fetchAll(PDO::FETCH_ASSOC);
echo "invoice_items rows for ASN000107:\n";
print_r($items);

$cust = $pdo->query("SELECT customer_name, is_vat_registered, vat_number, tin_number, customer_type FROM customer_profiles WHERE customer_name='{$rows[0]['customer_name']}'")->fetch(PDO::FETCH_ASSOC);
echo "Customer profile:\n";
print_r($cust);
