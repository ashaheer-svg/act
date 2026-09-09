<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

$rows = $db->fetchAll("
    SELECT 
        substr(invoice_date, 1, 4) as yr,
        COUNT(DISTINCT invoice_number) as total_invoices,
        COUNT(DISTINCT CASE WHEN item_description LIKE '%VAT%' OR item_description LIKE '%Value Added Tax%' THEN invoice_number END) as invoices_with_vat_word,
        COUNT(DISTINCT CASE WHEN tax_code = 'Tax' THEN invoice_number END) as invoices_with_tax_code_Tax,
        COUNT(DISTINCT CASE WHEN tax_code = 'Taxable Sales' THEN invoice_number END) as invoices_with_tax_code_TaxableSales,
        COUNT(DISTINCT CASE WHEN tax_code = 'Non' THEN invoice_number END) as invoices_with_tax_code_Non
    FROM sales
    GROUP BY substr(invoice_date, 1, 4)
    ORDER BY yr ASC
");

echo "Yearly breakdown of VAT and tax codes:\n";
foreach ($rows as $r) {
    echo "Year: {$r['yr']} | Total Invs: {$r['total_invoices']} | Invs with VAT item: {$r['invoices_with_vat_word']} | Tax Code 'Tax': {$r['invoices_with_tax_code_Tax']} | Tax Code 'Taxable Sales': {$r['invoices_with_tax_code_TaxableSales']} | Tax Code 'Non': {$r['invoices_with_tax_code_Non']}\n";
}
