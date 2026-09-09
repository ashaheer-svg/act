<?php
$csvPath = __DIR__ . '/../app/exports/qb_export_invoices_2026-09-05_125436.csv';
$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

$taxCodes = [];
$items = [];
$vatItems = [];

while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < count($header)) continue;
    $d = array_combine($header, $row);
    $taxCode = trim($d['Sales Tax Code'] ?? '');
    $item = trim($d['Item'] ?? '');
    $desc = trim($d['Description'] ?? '');
    
    $taxCodes[$taxCode] = ($taxCodes[$taxCode] ?? 0) + 1;
    if (stripos($item, 'vat') !== false || stripos($item, 'tax') !== false || stripos($desc, 'vat') !== false) {
        $vatItems[$item . ' | ' . substr($desc, 0, 30)] = ($vatItems[$item . ' | ' . substr($desc, 0, 30)] ?? 0) + 1;
    }
}
fclose($fh);

echo "Tax Codes count:\n";
print_r($taxCodes);

echo "\nVAT/Tax items sample:\n";
print_r(array_slice($vatItems, 0, 30));
