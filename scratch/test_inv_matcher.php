<?php

function parseInvoiceNumber($inv) {
    $inv = trim($inv);
    if (preg_match('/^([A-Za-z]+)-?(\d+)$/', $inv, $m)) {
        return ['prefix' => strtoupper($m[1]), 'num' => intval($m[2]), 'raw' => $inv];
    }
    return ['prefix' => '', 'num' => 0, 'raw' => $inv];
}

function matchesInvoiceRange($inv, $rangeStart, $rangeEnd) {
    if (empty($rangeStart) || empty($rangeEnd)) return false;
    $pInv = parseInvoiceNumber($inv);
    $pStart = parseInvoiceNumber($rangeStart);
    $pEnd = parseInvoiceNumber($rangeEnd);

    if ($pInv['prefix'] === $pStart['prefix']) {
        return ($pInv['num'] >= $pStart['num'] && $pInv['num'] <= $pEnd['num']);
    }
    return false;
}

$rules = [
    ['name' => 'Legacy 12% VAT', 'rate' => 0.12, 'start' => 'AS004001', 'end' => 'AS005147'],
    ['name' => 'Legacy 0% Exempt', 'rate' => 0.00, 'start' => 'AS005148', 'end' => 'AS006560'],
    ['name' => 'Legacy 15% VAT', 'rate' => 0.15, 'start' => 'AS006561', 'end' => 'AS008154'],
    ['name' => 'Legacy 8% VAT', 'rate' => 0.08, 'start' => 'AS008155', 'end' => 'AS008211'],
    ['name' => 'Exempt 0% VAT', 'rate' => 0.00, 'start' => 'AS008212', 'end' => 'AS010020'],
    ['name' => '18% Statutory VAT', 'rate' => 0.18, 'start' => 'AS010021', 'end' => 'AS011260'],
    ['name' => 'New Seq ASN 18% VAT', 'rate' => 0.18, 'start' => 'ASN000001', 'end' => 'ASN000102'],
    ['name' => 'New Seq AS 18% VAT', 'rate' => 0.18, 'start' => 'AS000001', 'end' => 'AS000102'],
];

$testInvoices = [
    'AS004500' => 0.12,
    'AS005147' => 0.12,
    'AS005148' => 0.00,
    'AS006000' => 0.00,
    'AS007000' => 0.15,
    'AS008155' => 0.08,
    'AS008200' => 0.08,
    'AS008212' => 0.00,
    'AS009461' => 0.00, // In DB: 2022-09-01
    'AS010020' => 0.00, // In DB: 2023-12-30
    'AS010021' => 0.18, // In DB: 2024-01-13
    'AS010500' => 0.18,
    'AS011260' => 0.18,
    'AS000001' => 0.18, // In DB: 2026-07-02
    'AS000102' => 0.18, // In DB: 2026-09-05
    'ASN000050' => 0.18,
];

$allPassed = true;
foreach ($testInvoices as $inv => $expectedRate) {
    $matchedRate = null;
    $matchedName = null;
    foreach ($rules as $r) {
        if (matchesInvoiceRange($inv, $r['start'], $r['end'])) {
            $matchedRate = $r['rate'];
            $matchedName = $r['name'];
            break;
        }
    }
    $status = ($matchedRate === $expectedRate) ? "PASS" : "FAIL (got $matchedRate)";
    if ($matchedRate !== $expectedRate) $allPassed = false;
    echo sprintf("%-12s => Expected: %-5s | Matched: %-5s | %-20s | %s\n", $inv, $expectedRate, $matchedRate, $matchedName, $status);
}

echo "\nResult: " . ($allPassed ? "ALL TESTS PASSED!" : "SOME TESTS FAILED!") . "\n";
