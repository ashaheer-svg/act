<?php
error_reporting(0);
ini_set('display_errors', '0');

$f1 = fopen(__DIR__ . '/../Exports/qb_export_invoices_2026-09-09_153906.csv', 'r');
$h1 = fgetcsv($f1, 0, ',', '"', '');
$y1 = [];
while ($r = fgetcsv($f1, 0, ',', '"', '')) {
    $y = substr($r[1] ?? '', 0, 4);
    if ($y) $y1[$y] = ($y1[$y] ?? 0) + 1;
}
fclose($f1);

$f2 = fopen(__DIR__ . '/../Exports/qb_export_invoices_2026-09-09_160211.csv', 'r');
$h2 = fgetcsv($f2, 0, ',', '"', '');
$y2 = [];
while ($r = fgetcsv($f2, 0, ',', '"', '')) {
    $y = substr($r[1] ?? '', 0, 4);
    if ($y) $y2[$y] = ($y2[$y] ?? 0) + 1;
}
fclose($f2);

echo "Lines by year in 153906:\n";
ksort($y1);
print_r($y1);

echo "Lines by year in 160211:\n";
ksort($y2);
print_r($y2);
