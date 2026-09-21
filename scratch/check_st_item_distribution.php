<?php
require 'config.php';
require 'classes/Database.php';

$db = new Database(DATABASE_PATH);

$res = $db->fetchAll("
    SELECT 
        substr(invoice_date, 1, 4) as yr,
        sales_tax_item,
        vat_treatment,
        COUNT(DISTINCT invoice_number) as inv_count,
        SUM(total_amount) as total_gross,
        SUM(base_value) as total_base,
        SUM(vat_component) as total_vat
    FROM sales
    WHERE invoice_date >= '2024-01-01' AND total_amount > 0
    GROUP BY yr, sales_tax_item, vat_treatment
    ORDER BY yr, sales_tax_item
");

echo "=== 2024-2026 SALES BY sales_tax_item & vat_treatment ===\n";
foreach ($res as $r) {
    echo sprintf(
        "Year: %s | ST_Item: %-6s | Treat: %-14s | Invoices: %-4d | Gross: %-14s | Base: %-14s | VAT: %-12s\n",
        $r['yr'],
        $r['sales_tax_item'] ?? 'EMPTY',
        $r['vat_treatment'],
        $r['inv_count'],
        number_format($r['total_gross'], 2),
        number_format($r['total_base'], 2),
        number_format($r['total_vat'], 2)
    );
}
