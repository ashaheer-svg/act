<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$totalRows = $pdo->query("SELECT count(*) FROM sales WHERE invoice_type = 'Invoice'")->fetchColumn();

$viableCondition = "
    (
        total_amount != 0 
        OR (
            quantity > 0 
            AND TRIM(COALESCE(item_description, '')) != 'Item' 
            AND TRIM(COALESCE(item_description, '')) != 'Opening balance' 
            AND (
                item_description LIKE '%S/N%' 
                OR item_description LIKE '%SN:%' 
                OR item_description LIKE '%Serial%'
                OR (product_category IS NOT NULL AND TRIM(product_category) != '' AND TRIM(product_category) != 'Uncategorized')
            )
        )
    )
    AND TRIM(COALESCE(item_description, '')) != 'Item'
    AND TRIM(COALESCE(item_description, '')) != 'Opening balance'
";

$retainedRows = $pdo->query("SELECT count(*) FROM sales WHERE invoice_type = 'Invoice' AND $viableCondition")->fetchColumn();
$skippedRows = $totalRows - $retainedRows;

echo "Total Invoice Lines: $totalRows\n";
echo "Retained Viable Lines: $retainedRows\n";
echo "Skipped Zero/Placeholder Lines: $skippedRows\n\n";

echo "=== Sample of Retained Viable Zero-Amount Lines ===\n";
$stmt = $pdo->query("
    SELECT invoice_number, item_description, quantity, total_amount, product_category
    FROM sales
    WHERE invoice_type = 'Invoice' AND total_amount = 0 AND $viableCondition
    LIMIT 10
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Inv: {$r['invoice_number']} | Qty: {$r['quantity']} | Cat: {$r['product_category']} | Desc: " . substr($r['item_description'], 0, 50) . "\n";
}

echo "\n=== Sample of Skipped Zero-Amount Lines ===\n";
$stmt = $pdo->query("
    SELECT invoice_number, item_description, quantity, total_amount, product_category
    FROM sales
    WHERE invoice_type = 'Invoice' AND NOT ($viableCondition)
    LIMIT 15
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Inv: {$r['invoice_number']} | Qty: {$r['quantity']} | Cat: {$r['product_category']} | Desc: " . substr($r['item_description'], 0, 50) . "\n";
}
