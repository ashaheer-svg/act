<?php
$f = __DIR__ . '/../docs/Book6.csv';
$fh = fopen($f, 'r');
$header = fgetcsv($fh);
echo "Header: " . implode(' | ', $header) . "\n";
$firstFew = [];
$lastFew = [];
while (($row = fgetcsv($fh)) !== false) {
    if (count($firstFew) < 5) $firstFew[] = $row;
    $lastFew[] = $row;
    if (count($lastFew) > 10) array_shift($lastFew);
}
fclose($fh);

echo "\nFirst 5 rows of Book6.csv:\n";
foreach ($firstFew as $r) echo implode(' | ', $r) . "\n";

echo "\nLast 10 rows of Book6.csv:\n";
foreach ($lastFew as $r) echo implode(' | ', $r) . "\n";
