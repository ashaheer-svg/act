<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$pdo = $db->getConnection();

echo "=======================================================\n";
echo "DATABASE CONSISTENCY & HEALTH AUDIT (2009 - 2026)\n";
echo "=======================================================\n\n";

// 1. Overview
$overview = $db->fetch("
    SELECT 
        COUNT(*) as total_lines,
        COUNT(DISTINCT invoice_number) as total_invoices,
        COUNT(DISTINCT customer_name) as distinct_customers,
        MIN(invoice_date) as earliest_invoice,
        MAX(invoice_date) as latest_invoice,
        SUM(qb_amount) as total_qb_amount,
        SUM(base_value) as total_base_value,
        SUM(vat_component) as total_vat,
        SUM(total_amount) as total_amount
    FROM sales
");
print_r($overview);

// 2. Year-by-Year breakdown in sales
echo "\n--- YEAR-BY-YEAR DISTRIBUTION IN SALES ---\n";
$byYear = $db->fetchAll("
    SELECT 
        strftime('%Y', invoice_date) as year,
        COUNT(*) as line_count,
        COUNT(DISTINCT invoice_number) as inv_count,
        COUNT(DISTINCT customer_name) as customer_count,
        ROUND(SUM(base_value), 2) as base_revenue,
        ROUND(SUM(vat_component), 2) as total_vat,
        ROUND(SUM(total_amount), 2) as gross_revenue,
        ROUND(SUM(CASE WHEN vat_component > 0 THEN total_amount ELSE 0 END), 2) as vat_eligible_rev,
        ROUND(SUM(CASE WHEN vat_component = 0 THEN total_amount ELSE 0 END), 2) as zero_vat_rev
    FROM sales
    GROUP BY strftime('%Y', invoice_date)
    ORDER BY year ASC
");

echo sprintf("%-6s | %-8s | %-8s | %-10s | %-16s | %-14s | %-16s\n", "Year", "Invoices", "Lines", "Custs", "Base Rev (LKR)", "VAT (LKR)", "Gross Rev (LKR)");
echo str_repeat("-", 90) . "\n";
foreach ($byYear as $y) {
    echo sprintf(
        "%-6s | %-8d | %-8d | %-10d | %-16s | %-14s | %-16s\n",
        $y['year'] ?? 'NULL',
        $y['inv_count'],
        $y['line_count'],
        $y['customer_count'],
        number_format($y['base_revenue'], 2),
        number_format($y['total_vat'], 2),
        number_format($y['gross_revenue'], 2)
    );
}

// 3. Mathematical Consistency Check: Does base_value + vat_component == total_amount?
echo "\n--- MATHEMATICAL CONSISTENCY CHECK ---\n";
$mathMismatches = $db->fetchAll("
    SELECT id, invoice_number, invoice_date, customer_name, qb_amount, base_value, vat_component, total_amount,
           ROUND(base_value + vat_component, 2) as expected_total
    FROM sales
    WHERE abs(ROUND(base_value + vat_component, 2) - total_amount) > 0.05
    LIMIT 20
");
echo "Lines where (base_value + vat_component != total_amount): " . count($mathMismatches) . "\n";
if (!empty($mathMismatches)) {
    print_r(array_slice($mathMismatches, 0, 5));
}

// 4. VAT Treatment Classification Breakdown
echo "\n--- VAT TREATMENT DISTRIBUTION ---\n";
$vatTreatments = $db->fetchAll("
    SELECT vat_treatment, COUNT(*) as line_count, COUNT(DISTINCT invoice_number) as inv_count, ROUND(SUM(total_amount), 2) as total_revenue
    FROM sales
    GROUP BY vat_treatment
");
print_r($vatTreatments);

// 5. Zero-Amount Lines Analysis
echo "\n--- ZERO-AMOUNT LINES AUDIT ---\n";
$zeroLines = $db->fetch("
    SELECT 
        COUNT(*) as zero_count,
        COUNT(DISTINCT invoice_number) as zero_invoices
    FROM sales
    WHERE total_amount = 0
");
echo "Total zero-amount lines: {$zeroLines['zero_count']} across {$zeroLines['zero_invoices']} invoices.\n";

// Sample zero items: are they warranties / serials / contracts?
$zeroSamples = $db->fetchAll("
    SELECT item_description, COUNT(*) as cnt
    FROM sales
    WHERE total_amount = 0
    GROUP BY item_description
    ORDER BY cnt DESC
    LIMIT 10
");
echo "Top Zero-Amount Description Types:\n";
foreach ($zeroSamples as $zs) {
    echo "  [{$zs['cnt']}x] {$zs['item_description']}\n";
}

// 6. Customer Reconciliation (Sales vs Customer Profiles)
echo "\n--- CUSTOMER CONSISTENCY ---\n";
$unmatchedCustomers = $db->fetchAll("
    SELECT DISTINCT s.customer_name
    FROM sales s
    LEFT JOIN customer_profiles cp ON s.customer_name = cp.customer_name
    WHERE cp.customer_name IS NULL
");
echo "Sales customer names NOT in customer_profiles: " . count($unmatchedCustomers) . "\n";
if (!empty($unmatchedCustomers)) {
    print_r(array_slice($unmatchedCustomers, 0, 10));
}

// 7. Payments Consistency
echo "\n--- PAYMENTS CONSISTENCY ---\n";
$payOverview = $db->fetch("
    SELECT 
        COUNT(*) as total_payments,
        COUNT(DISTINCT invoice_num) as distinct_invoices_paid,
        COUNT(DISTINCT customer_name) as customers_paying,
        MIN(payment_date) as earliest_payment,
        MAX(payment_date) as latest_payment,
        SUM(amount) as total_paid
    FROM payments
");
print_r($payOverview);

$unmatchedPayments = $db->fetch("
    SELECT COUNT(*) as unlinked_count
    FROM payments p
    LEFT JOIN sales s ON p.invoice_num = s.invoice_number AND p.customer_name = s.customer_name
    WHERE p.invoice_num != '' AND s.id IS NULL
");
echo "Payments referencing an invoice_number not found in sales: {$unmatchedPayments['unlinked_count']}\n";

// 8. Checking if any 2009-2020 files exist in docs/ or imports
echo "\n--- FILES IN WORKSPACE (DOCS / EXPORTS) ---\n";
$files = glob(__DIR__ . '/../docs/*.*');
foreach ($files as $f) {
    echo "  " . basename($f) . " (" . round(filesize($f)/1024, 1) . " KB)\n";
}
$expFiles = glob(__DIR__ . '/../app/exports/*.*');
foreach ($expFiles as $f) {
    echo "  [app/exports] " . basename($f) . " (" . round(filesize($f)/1024, 1) . " KB)\n";
}
