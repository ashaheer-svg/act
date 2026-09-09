<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$invoicesToInspect = ['AS008895', 'AS009036', 'AS009130', 'AS008836', 'AS008863', 'AS000102'];

foreach ($invoicesToInspect as $inv) {
    $stmt = $db->prepare("SELECT id, invoice_number, invoice_date, customer_name, quantity, total_amount, item_description FROM sales WHERE invoice_number = ? ORDER BY id ASC");
    $stmt->execute([$inv]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "======================================================================\n";
    echo "INVOICE: $inv | Customer: " . ($lines[0]['customer_name'] ?? 'N/A') . " | Date: " . ($lines[0]['invoice_date'] ?? 'N/A') . "\n";
    echo "======================================================================\n";
    foreach ($lines as $idx => $l) {
        echo "[" . ($idx + 1) . "] Qty: {$l['quantity']} | Total: {$l['total_amount']}\n";
        echo "    Desc:\n    " . str_replace("\n", "\n    ", trim($l['item_description'])) . "\n\n";
    }
}
