<?php
$f = __DIR__ . '/../docs/2024-2026-sales.csv';
$fh = fopen($f, 'r');
$header = fgetcsv($fh);
$count = 0;
while (($row = fgetcsv($fh)) !== false && $count < 10) {
    $filtered = array_filter($row, function($v) { return trim($v) !== ''; });
    if (!empty($filtered)) {
        echo implode(' | ', $filtered) . "\n";
        $count++;
    }
}
fclose($fh);
