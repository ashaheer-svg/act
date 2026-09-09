<?php
$f = __DIR__ . '/../docs/2024-2026-sales.csv';
$fh = fopen($f, 'r');
$lastRows = [];
while (($row = fgetcsv($fh)) !== false) {
    $filtered = array_filter($row, function($v) { return trim($v) !== ''; });
    if (!empty($filtered)) {
        $lastRows[] = $filtered;
        if (count($lastRows) > 20) {
            array_shift($lastRows);
        }
    }
}
fclose($fh);

echo "Last 20 rows of docs/2024-2026-sales.csv:\n";
foreach ($lastRows as $r) {
    echo implode(' | ', $r) . "\n";
}
