<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/sales_bi.db');

$stmt = $pdo->query("
    SELECT invoice_number, customer_name, item_description 
    FROM sales 
    WHERE item_description LIKE '%End Customer%' 
    GROUP BY invoice_number
");

$samples = [];
$regex = '/\(?\s*End\s+Customer\s*[:\-]\s*(.*?)\)?$/i';

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $desc = trim($r['item_description']);
    if (preg_match($regex, $desc, $m)) {
        $endCust = trim($m[1], " \t\n\r\0\x0B()\"'");
        $samples[] = [
            'inv' => $r['invoice_number'],
            'partner' => $r['customer_name'],
            'raw' => $desc,
            'extracted' => $endCust
        ];
    } else {
        $samples[] = [
            'inv' => $r['invoice_number'],
            'partner' => $r['customer_name'],
            'raw' => $desc,
            'extracted' => '[NO REGEX MATCH]'
        ];
    }
}

echo "Total invoices with End Customer line: " . count($samples) . "\n\n";
echo "First 20 samples:\n";
foreach (array_slice($samples, 0, 20) as $s) {
    echo "Inv: {$s['inv']} | Partner: {$s['partner']}\n";
    echo "   Raw: '{$s['raw']}'\n";
    echo "   -> Extracted End Customer: '{$s['extracted']}'\n\n";
}
