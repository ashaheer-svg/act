<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];
echo "Total invoices in JSON: " . count($invoices) . "\n";

$sepInvs = [];
$allVatLineInvs = [];
$sepVatLineInvs = [];

foreach ($invoices as $inv) {
    $num = trim($inv['Num'] ?? '');
    $date = trim($inv['Date'] ?? '');
    $desc = trim($inv['Description'] ?? $inv['Item'] ?? '');
    $amt = floatval($inv['Amount'] ?? 0);
    
    // Check if line is a separate VAT line
    if (preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $desc) && $amt > 0) {
        $allVatLineInvs[$num][] = ['date' => $date, 'desc' => $desc, 'amount' => $amt];
    }
    
    // Check September 2026
    if (strpos($date, '2026-09') === 0) {
        $sepInvs[$num][] = $inv;
        if (preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $desc) && $amt > 0) {
            $sepVatLineInvs[$num][] = ['date' => $date, 'desc' => $desc, 'amount' => $amt];
        }
    }
}

echo "Total unique invoices with explicit positive VAT line in export: " . count($allVatLineInvs) . "\n";
foreach ($allVatLineInvs as $num => $lines) {
    echo "  - $num: " . json_encode($lines) . "\n";
}

echo "\nSeptember 2026 in export:\n";
echo "Total unique invoices in Sep 2026: " . count($sepInvs) . "\n";
echo "Unique invoices with separate VAT line in Sep 2026: " . count($sepVatLineInvs) . "\n";
echo "Invoices in Sep 2026 that are VAT-inclusive: " . (count($sepInvs) - count($sepVatLineInvs)) . "\n";

echo "\nListing all Sep 2026 Invoices:\n";
foreach ($sepInvs as $num => $lines) {
    $cust = $lines[0]['Name'] ?? '';
    $dt = $lines[0]['Date'] ?? '';
    $tot = 0;
    $items = [];
    foreach ($lines as $l) {
        $tot += floatval($l['Amount'] ?? 0);
        $items[] = trim($l['Description'] ?? $l['Item'] ?? '');
    }
    echo "  Invoice [$num] | Date: $dt | Customer: $cust | Lines: " . count($lines) . " | Total: " . number_format($tot, 2) . " | Items: " . implode('; ', array_slice($items, 0, 2)) . "\n";
}
