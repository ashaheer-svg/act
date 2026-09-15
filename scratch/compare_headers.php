<?php
$fOld = 'Exports/qb_export_invoices_2026-09-09_153906.csv';
$fNew = 'Exports/qb_export_invoices_2026-09-09_160211.csv';

$h1 = fopen($fOld, 'r');
$hdrOld = fgetcsv($h1);
fclose($h1);

$h2 = fopen($fNew, 'r');
$hdrNew = fgetcsv($h2);
fclose($h2);

echo "Old Columns (" . count($hdrOld) . "):\n" . implode(', ', $hdrOld) . "\n\n";
echo "New Columns (" . count($hdrNew) . "):\n" . implode(', ', $hdrNew) . "\n\n";

$diff1 = array_diff($hdrOld, $hdrNew);
$diff2 = array_diff($hdrNew, $hdrOld);

echo "In Old but not New: " . json_encode($diff1) . "\n";
echo "In New but not Old: " . json_encode($diff2) . "\n";
