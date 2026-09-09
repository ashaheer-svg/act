<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$invoices = $db->query("
    SELECT invoice_number
    FROM sales
    WHERE item_description LIKE '%S/N%'
    GROUP BY invoice_number
    LIMIT 10
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($invoices as $inv) {
    echo "==================================================\n";
    echo "INVOICE: $inv\n";
    $lines = $db->prepare("SELECT quantity, total_amount, item_description FROM sales WHERE invoice_number = ? ORDER BY id ASC");
    $lines->execute([$inv]);
    foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $i => $l) {
        $desc = trim($l['item_description']);
        echo "  [$i] Qty: {$l['quantity']} | Val: {$l['total_amount']} | Desc: " . substr(str_replace("\n", " / ", $desc), 0, 90) . "\n";
    }
}
