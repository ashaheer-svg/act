<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Invoices with SUM(total_amount) = 0 ===\n";
$stmt = $pdo->query("
    SELECT invoice_number, customer_name, COUNT(*) as line_count, SUM(quantity) as total_qty, 
           GROUP_CONCAT(item_description, ' | ') as descriptions
    FROM sales
    WHERE invoice_type = 'Invoice'
    GROUP BY invoice_number
    HAVING SUM(total_amount) = 0
    LIMIT 10
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Inv: {$r['invoice_number']} | Cust: {$r['customer_name']} | Lines: {$r['line_count']} | Qty: {$r['total_qty']}\n";
    echo "  Desc: " . substr($r['descriptions'], 0, 120) . "...\n\n";
}

echo "=== Total Invoices with total_amount = 0 ===\n";
$countZeroInvs = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT invoice_number
        FROM sales
        WHERE invoice_type = 'Invoice'
        GROUP BY invoice_number
        HAVING SUM(total_amount) = 0
    )
")->fetchColumn();
echo "Total zero-amount invoices: $countZeroInvs\n";

echo "=== Total line items with total_amount = 0 vs > 0 ===\n";
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN total_amount > 0 THEN 'Amount > 0'
            WHEN total_amount < 0 THEN 'Credit/Return (< 0)'
            ELSE 'Zero Amount (0)'
        END as amt_cat,
        COUNT(*) as cnt
    FROM sales
    GROUP BY amt_cat
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['amt_cat']}: {$r['cnt']}\n";
}
