<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "1. Sample Multi-line Invoices:\n";
$multiLines = $db->fetchAll("
    SELECT invoice_number, COUNT(*) as cnt, SUM(total_amount) as gross, customer_name, invoice_date, paid_date, po_number
    FROM sales
    WHERE invoice_number IS NOT NULL AND invoice_number != ''
    GROUP BY invoice_number
    HAVING cnt > 1
    ORDER BY cnt DESC
    LIMIT 5
");
print_r($multiLines);

if (!empty($multiLines)) {
    $invNum = $multiLines[0]['invoice_number'];
    echo "\n2. Line items for Invoice {$invNum}:\n";
    $lines = $db->fetchAll("
        SELECT id, invoice_number, item_description, product_category, quantity, base_value, vat_component, total_amount, memo
        FROM sales
        WHERE invoice_number = ?
    ", [$invNum]);
    print_r($lines);

    echo "\n3. Payments matching Invoice {$invNum}:\n";
    $payments = $db->fetchAll("
        SELECT * FROM payments WHERE invoice_num = ? OR invoice_num LIKE ?
    ", [$invNum, "%$invNum%"]);
    print_r($payments);
}

echo "\n4. Total Distinct Invoices:\n";
$totalInv = $db->fetch("SELECT COUNT(DISTINCT invoice_number) as c FROM sales WHERE invoice_number != ''");
echo "Count: " . $totalInv['c'] . "\n";
