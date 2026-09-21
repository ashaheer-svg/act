<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== 1. TAX RULES CONFIGURED IN DATABASE ===\n";
$rules = $db->fetchAll("SELECT * FROM tax_rules ORDER BY id ASC");
foreach ($rules as $r) {
    echo sprintf(
        "ID: %-2d | %-28s | Rate: %-4s | From: %-10s | To: %-10s | Seq: %-10s -> %-10s | Inc: %d | Notes: %s\n",
        $r['id'],
        $r['tax_name'],
        ($r['tax_rate'] * 100) . '%',
        $r['effective_from'] ?? 'NULL',
        $r['effective_to'] ?? 'NULL',
        $r['invoice_range_start'] ?? 'NULL',
        $r['invoice_range_end'] ?? 'NULL',
        $r['is_inclusive_default'],
        $r['notes'] ?? ''
    );
}

echo "\n=== 2. SALES BREAKDOWN POST-2021 (By Year & VAT Treatment) ===\n";
$summary = $db->fetchAll("
    SELECT 
        substr(invoice_date, 1, 4) as inv_year,
        vat_treatment,
        applied_tax_rate,
        COUNT(DISTINCT invoice_number) as invoice_count,
        COUNT(*) as row_count,
        ROUND(SUM(base_value), 2) as total_base,
        ROUND(SUM(vat_component), 2) as total_vat,
        ROUND(SUM(total_amount), 2) as total_gross
    FROM sales
    WHERE invoice_date >= '2021-01-01'
    GROUP BY inv_year, vat_treatment, applied_tax_rate
    ORDER BY inv_year ASC, vat_treatment ASC
");
foreach ($summary as $s) {
    echo sprintf(
        "Year: %s | Treatment: %-15s | Rate: %-5s | Invoices: %-5d | Base: %-14s | VAT: %-12s | Gross: %-14s\n",
        $s['inv_year'],
        $s['vat_treatment'] ?? 'NULL',
        ($s['applied_tax_rate'] * 100) . '%',
        $s['invoice_count'],
        number_format($s['total_base'], 2),
        number_format($s['total_vat'], 2),
        number_format($s['total_gross'], 2)
    );
}

echo "\n=== 3. INVOICE PREFIXES / SEQUENCES POST-2021 ===\n";
$prefixes = $db->fetchAll("
    SELECT 
        substr(invoice_date, 1, 4) as inv_year,
        CASE 
            WHEN invoice_number LIKE 'ASN%' THEN 'ASN'
            WHEN invoice_number LIKE 'AS%' THEN 'AS'
            ELSE 'OTHER'
        END as prefix,
        MIN(invoice_number) as min_inv,
        MAX(invoice_number) as max_inv,
        COUNT(DISTINCT invoice_number) as inv_count,
        MIN(invoice_date) as min_date,
        MAX(invoice_date) as max_date
    FROM sales
    WHERE invoice_date >= '2021-01-01'
    GROUP BY inv_year, prefix
    ORDER BY inv_year ASC, prefix ASC
");
foreach ($prefixes as $p) {
    echo sprintf(
        "Year: %s | Prefix: %-5s | Range: %-10s .. %-10s | Invoices: %-5d | Dates: %s to %s\n",
        $p['inv_year'],
        $p['prefix'],
        $p['min_inv'],
        $p['max_inv'],
        $p['inv_count'],
        $p['min_date'],
        $p['max_date']
    );
}

echo "\n=== 4. SAMPLE INVOICES FROM 2021-2023 AND 2024-2026 ===\n";
$samples = $db->fetchAll("
    SELECT id, invoice_number, invoice_date, customer_name, qb_amount, base_value, vat_component, total_amount, vat_treatment, sales_tax_total, sales_tax_rate, sales_tax_item, substr(item_description, 1, 40) as item_desc
    FROM sales
    WHERE invoice_number IN ('AS009100', 'AS010025', 'ASN000108', 'ASN000111')
    ORDER BY invoice_date ASC, id ASC
");
foreach ($samples as $sm) {
    echo sprintf(
        "Inv: %-10s | Date: %s | QB_Amt: %-10s | Base: %-10s | VAT: %-10s | Gross: %-10s | Treat: %-13s | ST_Item: %-4s | Desc: %s\n",
        $sm['invoice_number'],
        $sm['invoice_date'],
        number_format($sm['qb_amount'], 2),
        number_format($sm['base_value'], 2),
        number_format($sm['vat_component'], 2),
        number_format($sm['total_amount'], 2),
        $sm['vat_treatment'],
        $sm['sales_tax_item'],
        $sm['item_desc']
    );
}

