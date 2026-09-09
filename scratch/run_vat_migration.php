<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/DataSorter.php';

$dbPath = __DIR__ . '/../data/sales_bi.db';
$db = new Database($dbPath);
echo "--- 1. Running Schema Sync & Customer Tax Population ---\n";
$db->syncSchema();
$taxPopRes = $db->parseAndPopulateCustomerTaxNumbers();
echo "Tax Population Results: " . json_encode($taxPopRes, JSON_PRETTY_PRINT) . "\n\n";

echo "--- 2. Recalculating Historical Sales VAT ---\n";
$vatRecalcRes = $db->recalculateHistoricalVat();
echo "VAT Recalculation Results: " . json_encode($vatRecalcRes, JSON_PRETTY_PRINT) . "\n\n";

echo "--- 3. Re-sorting Invoices with DataSorter to update invoice_items ---\n";
$sorter = new DataSorter($db);

// We re-sort all invoices that currently have sorted items
$invoicesToResort = $db->fetchAll("SELECT DISTINCT invoice_number FROM invoice_items ORDER BY invoice_number ASC");
echo "Found " . count($invoicesToResort) . " invoices to re-sort...\n";

$resortedCount = 0;
foreach ($invoicesToResort as $row) {
    $invNum = $row['invoice_number'];
    try {
        $sortResult = $sorter->sortInvoice($invNum);
        if ($sortResult && !empty($sortResult['products'])) {
            $sorter->persistSortedData($sortResult);
            $resortedCount++;
        }
    } catch (Exception $e) {
        // Skip invoices with no sales rows
    }
}
echo "Successfully re-sorted $resortedCount invoices.\n\n";

echo "--- 4. Verification Check: AS000064 & AS000072 ---\n";
foreach (['AS000064', 'AS000072'] as $checkInv) {
    $salesRow = $db->fetch("SELECT invoice_number, customer_name, base_value, vat_component, total_amount, vat_treatment, applied_tax_rate FROM sales WHERE invoice_number = ? AND total_amount > 0 LIMIT 1", [$checkInv]);
    $itemRows = $db->fetchAll("SELECT invoice_number, customer_name, clean_product_name, base_value, vat_component, total_amount, vat_treatment FROM invoice_items WHERE invoice_number = ?", [$checkInv]);
    $custRow = $db->fetch("SELECT customer_name, vat_number, tin_number, is_vat_registered FROM customer_profiles WHERE customer_name = ?", [$salesRow['customer_name']]);
    
    echo ">> Invoice: $checkInv\n";
    echo "  Customer: " . ($salesRow['customer_name'] ?? 'N/A') . "\n";
    echo "  Cust Profile: VAT No=" . ($custRow['vat_number'] ?? 'None') . ", TIN=" . ($custRow['tin_number'] ?? 'None') . ", IsVatReg=" . ($custRow['is_vat_registered'] ?? 0) . "\n";
    echo "  Sales Table: Base=" . $salesRow['base_value'] . " | VAT=" . $salesRow['vat_component'] . " | Total=" . $salesRow['total_amount'] . " | Treat=" . $salesRow['vat_treatment'] . "\n";
    foreach ($itemRows as $it) {
        echo "  Item: {$it['clean_product_name']} | Base={$it['base_value']} | VAT={$it['vat_component']} | Total={$it['total_amount']} | Treat={$it['vat_treatment']}\n";
    }
    echo "\n";
}
