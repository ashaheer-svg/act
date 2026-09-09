<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

// Test invoice details logic
$invNum = 'AS009317';

// 1. Header & aggregated totals
$header = $db->fetch("
    SELECT 
        s.invoice_number,
        s.invoice_type,
        MIN(s.invoice_date) as invoice_date,
        s.customer_name,
        s.sales_rep_code,
        COALESCE(m.rep_name, s.sales_rep_code) as rep_name,
        MAX(s.po_number) as po_number,
        MAX(s.paid_date) as paid_date,
        MAX(s.days_to_pay) as days_to_pay,
        COUNT(*) as total_lines,
        SUM(s.quantity) as total_qty,
        SUM(s.base_value) as base_value,
        SUM(s.vat_component) as vat_component,
        SUM(s.total_amount) as total_amount
    FROM sales s
    LEFT JOIN sales_rep_mapping m ON s.sales_rep_code = m.rep_code
    WHERE s.invoice_number = ?
    GROUP BY s.invoice_number
", [$invNum]);

// 2. Customer CRM
$customer = $db->fetch("
    SELECT customer_name, company_name, contact_name, email, phone, bill_address, bill_city, customer_type, credit_limit, terms
    FROM customer_profiles
    WHERE customer_name = ?
", [$header['customer_name']]);

// 3. Lines
$lines = $db->fetchAll("
    SELECT id, item_description, product_category, quantity, base_value, vat_component, total_amount, memo
    FROM sales
    WHERE invoice_number = ?
    ORDER BY id ASC
", [$invNum]);

// 4. Payments
$payments = $db->fetchAll("
    SELECT id, payment_date, reference_num, amount, created_at
    FROM payments
    WHERE invoice_num = ? OR invoice_num LIKE ?
    ORDER BY payment_date ASC
", [$invNum, "%$invNum%"]);

echo "Header:\n";
print_r($header);
echo "\nCustomer:\n";
print_r($customer);
echo "\nTotal Lines: " . count($lines) . "\n";
echo "Payments:\n";
print_r($payments);
