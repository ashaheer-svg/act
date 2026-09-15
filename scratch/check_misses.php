<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/sales_bi.db');
$stmt = $pdo->query("SELECT invoice_number, item_description FROM sales WHERE item_description LIKE '%End Customer%'");
$misses = 0;
$matches = 0;
$regex = '/\(?\s*End\s+Customer\s*[:\-\s]\s*(.*?)\)?$/i';

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $desc = trim($r['item_description']);
    if (preg_match($regex, $desc, $m) && !empty(trim($m[1]))) {
        $matches++;
    } else {
        echo "MISS: {$r['invoice_number']} | '$desc'\n";
        $misses++;
    }
}
echo "\nTotal matched: $matches, Total misses: $misses\n";
