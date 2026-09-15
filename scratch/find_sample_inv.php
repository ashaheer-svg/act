<?php
$f = fopen(__DIR__ . '/../Exports/qb_export_invoices_2026-09-09_153906.csv', 'r');
$h = fgetcsv($f);
$found = [];
while ($r = fgetcsv($f)) {
    if (strpos($r[2] ?? '', 'AS009292') !== false || strpos($r[2] ?? '', '9292') !== false) {
        $found[] = $r;
    }
}
fclose($f);
echo "Found in 153906: " . count($found) . "\n";
if (count($found) > 0) {
    print_r($found[0]);
}

$f2 = fopen(__DIR__ . '/../Exports/qb_export_invoices_2026-09-09_160211.csv', 'r');
$h2 = fgetcsv($f2);
$found2 = [];
while ($r = fgetcsv($f2)) {
    if (strpos($r[2] ?? '', 'AS009292') !== false || strpos($r[2] ?? '', '9292') !== false) {
        $found2[] = $r;
    }
}
fclose($f2);
echo "Found in 160211: " . count($found2) . "\n";
if (count($found2) > 0) {
    print_r($found2[0]);
}
