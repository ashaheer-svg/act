<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$json = json_decode(file_get_contents($f), true);
$invoices = $json['invoices'] ?? [];

$dates = [];
foreach (array_slice($invoices, -20) as $inv) {
    echo "Num: " . ($inv['Num'] ?? $inv['invoice_number'] ?? '') . " | Date: " . ($inv['Date'] ?? $inv['invoice_date'] ?? '') . " | Amount: " . ($inv['Amount'] ?? $inv['amount'] ?? '') . " | Item: " . substr($inv['Description'] ?? $inv['Item'] ?? '', 0, 30) . "\n";
}
