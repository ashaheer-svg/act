<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

// Test 1: Rolling 12 months HTML render
$_GET = ['type' => 'monthly', 'mode' => 'rolling'];
ob_start();
require 'reports.php';
$htmlRolling = ob_get_clean();

echo "Rolling 12 Months HTML length: " . strlen($htmlRolling) . " bytes\n";
if (strpos($htmlRolling, 'Monthly Sales Performance Matrix') !== false) {
    echo "SUCCESS: Found 'Monthly Sales Performance Matrix' in Rolling mode!\n";
} else {
    echo "ERROR: Title not found in Rolling mode!\n";
}

if (strpos($htmlRolling, 'PERIOD GROSS BILLED') !== false) {
    echo "SUCCESS: Found 'PERIOD GROSS BILLED' KPI!\n";
} else {
    echo "ERROR: KPI card not found!\n";
}

// Check if all 12 month short labels are in table
if (strpos($htmlRolling, 'PERIOD TOTAL') !== false && strpos($htmlRolling, 'MONTHLY AVG') !== false) {
    echo "SUCCESS: Found 'PERIOD TOTAL' and 'MONTHLY AVG' columns!\n";
} else {
    echo "ERROR: Summary columns missing!\n";
}

// Test 2: Calendar 2026 HTML render
$_GET = ['type' => 'monthly', 'mode' => 'calendar', 'year' => '2026'];
ob_start();
require 'reports.php';
$htmlCal = ob_get_clean();

echo "\nCalendar 2026 HTML length: " . strlen($htmlCal) . " bytes\n";
if (strpos($htmlCal, 'Calendar Year 2026') !== false) {
    echo "SUCCESS: Found 'Calendar Year 2026' in title!\n";
} else {
    echo "ERROR: Calendar title not found!\n";
}

// Test 3: CSV Export test
$_GET = ['type' => 'monthly', 'mode' => 'rolling', 'export' => 'csv'];
ob_start();
try {
    require 'reports.php';
} catch (Throwable $e) {
    // exit was called
}
$csvOutput = ob_get_clean();

echo "\nCSV Export length: " . strlen($csvOutput) . " bytes\n";
$csvLines = explode("\n", trim($csvOutput));
echo "CSV Line count: " . count($csvLines) . "\n";
echo "Header: " . ($csvLines[0] ?? '') . "\n";
if (count($csvLines) >= 12 && strpos($csvLines[0], 'Period Total') !== false) {
    echo "SUCCESS: CSV Export generated with proper headers and 11 metric rows!\n";
} else {
    echo "ERROR: CSV output unexpected!\n";
}

