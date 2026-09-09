<?php
$csvPath = __DIR__ . '/../app/exports/qb_export_invoices_2026-09-05_125436.csv';
if (!file_exists($csvPath)) {
    die("CSV not found: $csvPath\n");
}

$fh = fopen($csvPath, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
echo "CSV Columns: " . implode(', ', $header) . "\n\n";

$targets = ['AS000064', 'AS000072', 'AS0000064', 'AS0000072', '64', '72'];
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < count($header)) continue;
    $data = array_combine($header, $row);
    $num = trim($data['Num'] ?? '');
    if (in_array($num, $targets) || strpos($num, '64') !== false || strpos($num, '72') !== false) {
        if ($num === 'AS000064' || $num === 'AS000072') {
            echo "--- INVOICE $num ---\n";
            foreach ($data as $k => $v) {
                if ($v !== '') echo "  $k: $v\n";
            }
        }
    }
}
fclose($fh);
