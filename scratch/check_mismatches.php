<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/DataSorter.php';

$db = new Database(DATABASE_PATH);
$sorter = new DataSorter($db);

$invoices = $db->fetchAll("SELECT DISTINCT invoice_number FROM sales WHERE total_amount > 0 ORDER BY invoice_date DESC LIMIT 100");

echo "Checking financial consistency on 100 invoices:\n";
$mismatches = 0;

foreach ($invoices as $invRow) {
    $inv = $invRow['invoice_number'];
    $sorted = $sorter->sortInvoice($inv);

    $itemsSum = 0.0;
    foreach ($sorted['products'] as $p) {
        $itemsSum += floatval($p['total_amount']);
    }
    $gross = floatval($sorted['total_gross']);
    $diff = abs($gross - $itemsSum);

    if ($diff > 0.05) {
        $mismatches++;
        echo "=========================================================\n";
        echo "Mismatch on Invoice #$inv: Sales Gross = $gross, Items Sum = $itemsSum (Diff: $diff)\n";
        $lines = $db->fetchAll("SELECT quantity, total_amount, item_description FROM sales WHERE invoice_number = ? ORDER BY id ASC", [$inv]);
        foreach ($lines as $idx => $l) {
            echo "  [$idx] Amt: {$l['total_amount']} | Desc: " . trim($l['item_description']) . "\n";
        }
        echo "  Extracted Products:\n";
        foreach ($sorted['products'] as $p) {
            echo "    - {$p['product_name']} [{$p['product_type']}] Total: {$p['total_amount']}\n";
        }
    }
}

echo "\nTotal Mismatches found: $mismatches\n";
