<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$terms = ['%warranty%', '%period%', '%license%', '%licence%', '%subscription%', '%annual%', '%cloud%', '%saas%', '%expiry%', '%renew%'];

foreach ($terms as $t) {
    $stmt = $db->prepare("SELECT invoice_number, invoice_date, total_amount, item_description FROM sales WHERE item_description LIKE ? LIMIT 5");
    $stmt->execute([$t]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "==================================================\n";
    echo "PATTERN: $t (Found " . count($results) . " sample matches)\n";
    echo "==================================================\n";
    foreach ($results as $r) {
        echo "Invoice: " . $r['invoice_number'] . " | Date: " . $r['invoice_date'] . " | Amount: " . $r['total_amount'] . "\n";
        echo "Raw Text: \n" . trim($r['item_description']) . "\n";
        echo "--------------------------------------------------\n";
    }
}
