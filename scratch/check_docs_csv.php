<?php
$files = [
    __DIR__ . '/../docs/2024-2026-sales.csv',
    __DIR__ . '/../docs/Book6.csv',
    __DIR__ . '/../docs/2024-2026-payment.csv'
];

foreach ($files as $f) {
    if (!file_exists($f)) continue;
    echo "=== File: " . basename($f) . " ===\n";
    $fh = fopen($f, 'r');
    $header = fgetcsv($fh);
    if ($header) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        echo "Header: " . implode(' | ', $header) . "\n";
        
        // Search for AS000064 and AS000072
        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $line = implode(' | ', $row);
            if (strpos($line, '000064') !== false || strpos($line, '000072') !== false || strpos($line, 'AS000064') !== false || strpos($line, 'AS000072') !== false) {
                echo "  Row: " . substr($line, 0, 150) . "\n";
                $count++;
            }
        }
        echo "Found matches: $count\n\n";
    }
    fclose($fh);
}
