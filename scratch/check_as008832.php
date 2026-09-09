<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');
$lines = $db->query("SELECT * FROM sales WHERE invoice_number = 'AS008832'")->fetchAll(PDO::FETCH_ASSOC);
echo "AS008832 has " . count($lines) . " lines\n";
foreach ($lines as $l) {
    echo "  Qty: {$l['quantity']} | Total: {$l['total_amount']} | Desc: " . substr(str_replace("\n", " / ", $l['item_description']), 0, 70) . "\n";
}
