<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');

echo "=== Testing Report 1: LTV ===\n";
$ltv = $db->fetchAll("
    SELECT 
        s.customer_name,
        cp.customer_type,
        cp.sales_rep,
        MIN(s.invoice_date) as first_invoice,
        MAX(s.invoice_date) as last_invoice,
        ROUND((julianday(MAX(s.invoice_date)) - julianday(MIN(s.invoice_date))) / 365.25, 1) as tenure_years,
        COUNT(DISTINCT s.invoice_number) as total_invoices,
        ROUND(SUM(s.base_value), 2) as lifetime_base,
        ROUND(SUM(s.vat_component), 2) as lifetime_vat,
        ROUND(SUM(s.total_amount), 2) as lifetime_gross,
        ROUND(SUM(s.total_amount) / NULLIF(COUNT(DISTINCT s.invoice_number), 0), 2) as avg_order_value
    FROM sales s
    LEFT JOIN customer_profiles cp ON s.customer_name = cp.customer_name
    WHERE s.total_amount > 0
    GROUP BY s.customer_name
    ORDER BY lifetime_gross DESC
    LIMIT 5
");
print_r($ltv);

echo "\n=== Testing Report 2: Churn & Reactivation ===\n";
$churn = $db->fetchAll("
    SELECT 
        s.customer_name,
        cp.sales_rep,
        MAX(s.invoice_date) as last_purchase_date,
        CAST((julianday('now') - julianday(MAX(s.invoice_date))) as INTEGER) as days_inactive,
        COUNT(DISTINCT s.invoice_number) as historical_invoices,
        ROUND(SUM(s.total_amount), 2) as historical_spend
    FROM sales s
    LEFT JOIN customer_profiles cp ON s.customer_name = cp.customer_name
    GROUP BY s.customer_name
    HAVING days_inactive > 180
    ORDER BY historical_spend DESC
    LIMIT 5
");
print_r($churn);

echo "\n=== Testing Report 3: Hardware EOL ===\n";
$eol = $db->fetchAll("
    SELECT 
        serial_number,
        model_sku,
        product_name,
        brand,
        customer_name,
        invoice_number,
        invoice_date,
        warranty_months,
        warranty_expiry_date,
        CASE 
            WHEN warranty_expiry_date < date('now') THEN 'EXPIRED'
            WHEN warranty_expiry_date BETWEEN date('now') AND date('now', '+90 days') THEN 'EXPIRING_SOON'
            ELSE 'UNDER_WARRANTY'
        END as fleet_status
    FROM hardware_assets
    WHERE serial_number IS NOT NULL AND serial_number != ''
    ORDER BY warranty_expiry_date ASC
    LIMIT 5
");
echo "EOL count: " . count($eol) . "\n";
print_r($eol);

echo "\n=== Testing Report 4: Maintenance Renewals ===\n";
$renewals = $db->fetchAll("
    SELECT 
        contract_reference,
        customer_name,
        service_type,
        start_date,
        end_date,
        CAST((julianday(end_date) - julianday('now')) as INTEGER) as days_to_renewal,
        contract_value,
        CASE 
            WHEN end_date < date('now') THEN 'EXPIRED'
            WHEN end_date BETWEEN date('now') AND date('now', '+60 days') THEN 'UPCOMING'
            ELSE 'ACTIVE'
        END as renewal_status
    FROM contract_periods
    ORDER BY end_date ASC
    LIMIT 5
");
echo "Renewals count: " . count($renewals) . "\n";
print_r($renewals);

echo "\n=== Testing Report 5: Brand & Category Migration ===\n";
$brandMigration = $db->fetchAll("
    SELECT 
        COALESCE(NULLIF(product_category, ''), 'Unassigned') as brand_category,
        COUNT(DISTINCT invoice_number) as total_invoices,
        SUM(quantity) as total_units,
        ROUND(SUM(total_amount), 2) as lifetime_revenue,
        ROUND(SUM(CASE WHEN invoice_date < '2023-01-01' THEN total_amount ELSE 0 END), 2) as rev_pre_2023,
        ROUND(SUM(CASE WHEN invoice_date >= '2023-01-01' THEN total_amount ELSE 0 END), 2) as rev_post_2023
    FROM sales
    WHERE total_amount > 0
    GROUP BY brand_category
    ORDER BY lifetime_revenue DESC
    LIMIT 8
");
print_r($brandMigration);
