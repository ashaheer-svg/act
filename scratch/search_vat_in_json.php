<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];

$matches = [];
foreach ($invoices as $idx => $inv) {
    $desc = $inv['Description'] ?? '';
    $item = $inv['Item'] ?? '';
    if (stripos($desc, 'VAT') !== false || stripos($item, 'VAT') !== false) {
        $matches[] = [
            'num' => $inv['Num'] ?? '',
            'date' => $inv['Date'] ?? '',
            'item' => $item,
            'desc' => $desc,
            'amount' => $inv['Amount'] ?? ''
        ];
    }
}

echo "Total lines where Item or Description contains 'VAT': " . count($matches) . "\n";
foreach (array_slice($matches, 0, 30) as $m) {
    echo "  [{$m['num']}] {$m['date']} | Amt: {$m['amount']} | Item: {$m['item']} | Desc: {$m['desc']}\n";
}
