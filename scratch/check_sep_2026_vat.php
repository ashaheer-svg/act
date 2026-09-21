<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== 1. ALL TIME INVOICES WITH SEPARATE VAT LINE ITEM ===\n";
$allVatLineInvoices = $db->fetchAll("
    SELECT DISTINCT invoice_number, invoice_date, customer_name, item_description, qb_amount, total_amount
    FROM sales 
    WHERE (
        item_description LIKE 'VAT%' 
        OR item_description LIKE '%Value Added Tax%'
        OR item_description LIKE '%18% VAT%'
        OR item_description LIKE '%15% VAT%'
        OR item_description LIKE '%12% VAT%'
        OR item_description LIKE '%8% VAT%'
    ) AND invoice_type != 'Credit Memo'
");

echo "Total invoices with a separate VAT line across all history: " . count($allVatLineInvoices) . "\n";
foreach ($allVatLineInvoices as $row) {
    echo "  - Invoice: {$row['invoice_number']} | Date: {$row['invoice_date']} | Customer: {$row['customer_name']} | Desc: " . substr($row['item_description'], 0, 40) . " | Amount: {$row['total_amount']}\n";
}

echo "\n=== 2. SEPTEMBER 2026 ANALYSIS ===\n";
// Total invoices in September 2026 (2026-09-01 to 2026-09-30)
$sepInvoices = $db->fetchAll("
    SELECT DISTINCT invoice_number 
    FROM sales 
    WHERE invoice_date LIKE '2026-09%' 
      AND invoice_type != 'Credit Memo'
      AND invoice_number IS NOT NULL AND TRIM(invoice_number) != ''
");
$sepTotalInvoicesCount = count($sepInvoices);
echo "Total unique invoices in September 2026: $sepTotalInvoicesCount\n";

// Invoices in Sep 2026 that have a separate VAT line
$sepVatLineInvoices = $db->fetchAll("
    SELECT DISTINCT invoice_number, customer_name, item_description, total_amount
    FROM sales 
    WHERE invoice_date LIKE '2026-09%' 
      AND invoice_type != 'Credit Memo'
      AND (
          item_description LIKE 'VAT%' 
          OR item_description LIKE '%Value Added Tax%'
          OR item_description LIKE '%18% VAT%'
          OR item_description LIKE '%15% VAT%'
          OR item_description LIKE '%12% VAT%'
          OR item_description LIKE '%8% VAT%'
      )
");
$sepVatLineCount = count($sepVatLineInvoices);
echo "Invoices in September 2026 with separate VAT line item: $sepVatLineCount\n";
foreach ($sepVatLineInvoices as $r) {
    echo "  - Invoice: {$r['invoice_number']} | Customer: {$r['customer_name']} | Line: {$r['item_description']} | Amount: {$r['total_amount']}\n";
}

$sepVatInclusiveCount = $sepTotalInvoicesCount - $sepVatLineCount;
echo "Invoices in September 2026 that are VAT-inclusive: $sepVatInclusiveCount\n";

// Also check raw export file for September 2026 to see if any VAT fields or lines exist in QB raw export
$exportFile = 'app/binary/exports/qb_export_2026-09-16_143318.json';
if (file_exists($exportFile)) {
    echo "\n=== 3. VERIFICATION AGAINST RAW QUICKBOOKS EXPORT ($exportFile) ===\n";
    $json = json_decode(file_get_contents($exportFile), true);
    $invoices = $json['invoices'] ?? [];
    
    $rawSepInvoices = [];
    $rawSepVatLines = [];
    
    foreach ($invoices as $inv) {
        $d = $inv['Date'] ?? $inv['invoice_date'] ?? '';
        if (strpos($d, '2026-09') === 0 || strpos($d, '09/') === 0 || strpos($d, '/09/2026') !== false) {
            $num = $inv['Num'] ?? $inv['invoice_number'] ?? '';
            $rawSepInvoices[$num] = true;
            $desc = $inv['Description'] ?? $inv['Item'] ?? '';
            if (preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $desc)) {
                $rawSepVatLines[$num][] = $desc;
            }
        }
    }
    echo "Raw export unique Sep 2026 invoices: " . count($rawSepInvoices) . "\n";
    echo "Raw export Sep 2026 invoices with separate VAT line: " . count($rawSepVatLines) . "\n";
    echo "Raw export Sep 2026 invoices that are VAT-inclusive: " . (count($rawSepInvoices) - count($rawSepVatLines)) . "\n";
}
