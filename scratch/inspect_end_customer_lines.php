<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/sales_bi.db');
$count = $pdo->query("SELECT count(*) FROM sales WHERE item_description LIKE '%End Customer%'")->fetchColumn();
echo "Total rows in sales with '%End Customer%': $count\n\n";

$stmt = $pdo->query("SELECT invoice_number, invoice_date, customer_name, item_description, qb_amount, total_amount FROM sales WHERE item_description LIKE '%End Customer%' ORDER BY invoice_date DESC LIMIT 15");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['invoice_date']} | {$r['invoice_number']} | Partner: '{$r['customer_name']}' | Amount: {$r['total_amount']}\n";
    echo "  Desc: " . str_replace("\n", " ", $r['item_description']) . "\n";
}
