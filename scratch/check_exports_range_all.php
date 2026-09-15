<?php
$files = glob('Exports/*.csv');
$files = array_merge($files, glob('app/exports/*.csv'));

foreach ($files as $f) {
    if (stripos($f, 'invoice') === false) continue;
    $h = fopen($f, 'r');
    $hdr = fgetcsv($h);
    $dateIdx = 1;
    foreach ($hdr as $i => $col) {
        if (stripos($col, 'date') !== false) { $dateIdx = $i; break; }
    }
    $min = '9999-99-99';
    $max = '0000-00-00';
    $count = 0;
    while (($r = fgetcsv($h)) !== false) {
        $count++;
        if (isset($r[$dateIdx]) && strlen(trim($r[$dateIdx])) >= 8) {
            $d = trim($r[$dateIdx]);
            if ($d < $min) $min = $d;
            if ($d > $max) $max = $d;
        }
    }
    fclose($h);
    echo basename($f) . " -> Rows: $count | Date Range: $min to $max\n";
}
