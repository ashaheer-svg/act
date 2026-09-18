<?php
ini_set('memory_limit', '1024M');
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$handle = fopen($file, 'r');
$first500 = fread($handle, 500);
fclose($handle);
echo "Start of file:\n" . substr($first500, 0, 300) . "\n";

// Check if there are unescaped control characters
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$res = json_decode($content, true);
if ($res === null) {
    echo "json_decode error: " . json_last_error_msg() . "\n";
} else {
    echo "SUCCESS after stripping BOM!\n";
    echo "Invoices: " . count($res['invoices']) . "\n";
    echo "Customers: " . count($res['customers']) . "\n";
}
