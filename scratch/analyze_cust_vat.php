<?php
$f = __DIR__ . '/../app/exports/qb_export_customers_2026-09-05_125436.csv';
$fh = fopen($f, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
echo "Customer CSV Header: " . implode(' | ', $header) . "\n\n";

// Check first 15 rows for any tax/vat fields
$rowsWithVat = 0;
$rowsWithTin = 0;
$total = 0;
$samples = [];

while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < count($header)) continue;
    $d = array_combine($header, $row);
    $total++;
    
    $fullText = implode(' ', $d);
    $hasVat = preg_match('/(VAT\s*(?:No\.?|#|Registration)?\s*[:.-]?\s*[0-9A-Z\-\/]+)/i', $fullText, $mVat);
    $hasTin = preg_match('/(TIN\s*(?:No\.?|#)?\s*[:.-]?\s*[0-9A-Z\-\/]+)/i', $fullText, $mTin);
    
    if ($hasVat) {
        $rowsWithVat++;
        if (count($samples) < 10) {
            $samples[] = "Customer: {$d['Name']} => VAT match: {$mVat[1]} | In: " . substr($fullText, 0, 100);
        }
    }
    if ($hasTin) {
        $rowsWithTin++;
        if (count($samples) < 15 && !$hasVat) {
            $samples[] = "Customer: {$d['Name']} => TIN match: {$mTin[1]} | In: " . substr($fullText, 0, 100);
        }
    }
}
fclose($fh);

echo "Total Customers: $total\n";
echo "Customers with VAT pattern: $rowsWithVat\n";
echo "Customers with TIN pattern: $rowsWithTin\n";
echo "\nSamples found:\n" . implode("\n", $samples) . "\n";
