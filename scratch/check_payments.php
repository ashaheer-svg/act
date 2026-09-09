<?php
$payCsv = __DIR__ . '/../app/exports/qb_export_payments_2026-09-05_125436.csv';
$fh = fopen($payCsv, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
echo "Payment Columns: " . implode(', ', $header) . "\n";

while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < count($header)) continue;
    $d = array_combine($header, $row);
    $inv = trim($d['InvoiceNum'] ?? '');
    if ($inv === 'AS000064' || $inv === 'AS000072') {
        echo "Payment match: " . json_encode($d) . "\n";
    }
}
fclose($fh);
