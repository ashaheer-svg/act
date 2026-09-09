<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

foreach (['AS008899', 'AS008900', 'AS008925', 'AS008838', 'AS008850'] as $inv) {
    echo "=========================================================\n";
    echo "INVOICE: $inv\n";
    $stmt = $db->prepare("SELECT quantity, total_amount, item_description FROM sales WHERE invoice_number = ? ORDER BY id ASC");
    $stmt->execute([$inv]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $i => $r) {
        echo "  [$i] Qty: {$r['quantity']} | Val: {$r['total_amount']}\n";
        echo "      Desc: " . str_replace("\n", "\n            ", trim($r['item_description'])) . "\n";
    }
}
