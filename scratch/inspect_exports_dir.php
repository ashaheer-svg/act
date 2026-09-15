<?php
$dir = 'Exports';
$files = glob("$dir/*.csv");

echo "=== INSPECTING FILES IN Exports/ ===\n\n";

foreach ($files as $f) {
    $h = fopen($f, 'r');
    $hdr = fgetcsv($h);
    $dateIdx = -1;
    $invIdx = -1;
    foreach ($hdr as $i => $c) {
        $cLow = strtolower(trim($c, "\xEF\xBB\xBF"));
        if ($cLow === 'txndate' || strpos($cLow, 'date') !== false) {
            $dateIdx = $i;
        }
        if ($cLow === 'refnumber' || strpos($cLow, 'invoice') !== false) {
            $invIdx = $i;
        }
    }
    
    $rowCount = 0;
    $minDate = '9999-99-99';
    $maxDate = '0000-00-00';
    $years = [];
    $sampleInvs = [];
    
    while (($row = fgetcsv($h)) !== false) {
        $rowCount++;
        if ($dateIdx !== -1 && isset($row[$dateIdx])) {
            $d = trim($row[$dateIdx]);
            if (strlen($d) >= 8) {
                if ($d < $minDate) $minDate = $d;
                if ($d > $maxDate) $maxDate = $d;
                $yr = substr($d, 0, 4);
                $years[$yr] = ($years[$yr] ?? 0) + 1;
            }
        }
        if ($invIdx !== -1 && isset($row[$invIdx]) && count($sampleInvs) < 5) {
            $sampleInvs[] = trim($row[$invIdx]);
        }
    }
    fclose($h);
    
    echo basename($f) . ":\n";
    echo "  Rows: $rowCount\n";
    if ($dateIdx !== -1) {
        echo "  Date Range: $minDate to $maxDate\n";
        ksort($years);
        echo "  Year Breakdown: " . json_encode($years) . "\n";
    }
    if (!empty($sampleInvs)) {
        echo "  Sample Invoices: " . implode(', ', $sampleInvs) . "\n";
    }
    echo "\n";
}
