<?php
$fOld = 'Exports/qb_export_invoices_2026-09-09_153906.csv';
$fNew = 'Exports/qb_export_invoices_2026-09-09_160211.csv';

function getInvoiceSummary($file) {
    $h = fopen($file, 'r');
    $hdr = fgetcsv($h);
    $dateIdx = 1;
    $numIdx = 0;
    foreach ($hdr as $i => $col) {
        $c = strtolower(trim($col, "\xEF\xBB\xBF"));
        if ($c === 'txndate' || strpos($c, 'date') !== false) $dateIdx = $i;
        if ($c === 'refnumber' || strpos($c, 'num') !== false) $numIdx = $i;
    }
    
    $invs = [];
    while (($r = fgetcsv($h)) !== false) {
        $num = trim($r[$numIdx] ?? '');
        $date = trim($r[$dateIdx] ?? '');
        if (!empty($num)) {
            if (!isset($invs[$num])) {
                $invs[$num] = ['min_d' => $date, 'max_d' => $date, 'count' => 0];
            }
            $invs[$num]['count']++;
            if ($date < $invs[$num]['min_d']) $invs[$num]['min_d'] = $date;
            if ($date > $invs[$num]['max_d']) $invs[$num]['max_d'] = $date;
        }
    }
    fclose($h);
    return $invs;
}

echo "Scanning Old System Invoices (153906)...\n";
$oldInvs = getInvoiceSummary($fOld);
echo "Scanning New System Invoices (160211)...\n";
$newInvs = getInvoiceSummary($fNew);

echo "Total distinct invoice numbers in Old: " . count($oldInvs) . "\n";
echo "Total distinct invoice numbers in New: " . count($newInvs) . "\n";

$overlap = array_intersect_key($oldInvs, $newInvs);
echo "Total overlapping invoice numbers: " . count($overlap) . "\n";

if (!empty($overlap)) {
    echo "Sample Overlaps (first 15):\n";
    $i = 0;
    foreach ($overlap as $num => $oldData) {
        $newData = $newInvs[$num];
        echo sprintf("  Invoice: %-12s | Old Dates: %s to %s | New Dates: %s to %s\n", 
            $num, $oldData['min_d'], $oldData['max_d'], $newData['min_d'], $newData['max_d']);
        $i++;
        if ($i >= 15) break;
    }
}
