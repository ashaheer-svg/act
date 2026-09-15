<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$rules = $db->fetchAll('SELECT * FROM tax_rules ORDER BY id ASC');

echo "=== Tax Rules Table ===\n";
foreach ($rules as $r) {
    echo sprintf(
        "ID: %d | Name: %-25s | Rate: %-5s | Ranges: %s - %s | Dates: %s to %s | Default: %s\n",
        $r['id'],
        $r['tax_name'],
        $r['tax_rate'],
        $r['invoice_range_start'] ?? 'NONE',
        $r['invoice_range_end'] ?? 'NONE',
        $r['effective_from'] ?? 'OPEN',
        $r['effective_to'] ?? 'OPEN',
        $r['is_inclusive_default'] ? 'INCLUSIVE' : 'EXCLUSIVE'
    );
}

echo "\n=== Testing Invoice & Date Scenarios ===\n";
$testCases = [
    // Old system invoices (2009 - 2015)
    ['inv' => 'AS000120', 'date' => '2010-05-15'],
    ['inv' => 'AS002500', 'date' => '2012-08-20'],
    ['inv' => 'AS004500', 'date' => '2014-03-10'], // In range AS004001 - AS005147 (12%)
    ['inv' => 'AS005500', 'date' => '2015-06-15'], // In range AS005148 - AS006560 (0%)
    ['inv' => 'AS007000', 'date' => '2018-01-20'], // In range AS006561 - AS008154 (15%)
    ['inv' => 'AS008180', 'date' => '2019-12-01'], // In range AS008155 - AS008211 (8%)
    ['inv' => 'AS009000', 'date' => '2020-05-15'], // In range AS008212 - AS010020 (0%)
    ['inv' => 'AS010100', 'date' => '2022-09-01'], // In range AS010021 - AS010183 (15%)
    ['inv' => 'AS010200', 'date' => '2023-05-01'], // In range AS010184 - AS010550 (18%)
    ['inv' => 'AS010600', 'date' => '2024-04-01'], // In range AS010551+ (18%)
    // Unnumbered or differently prefixed invoices from old system
    ['inv' => '1025', 'date' => '2011-06-01'],
    ['inv' => 'INV-0042', 'date' => '2013-09-15'],
    ['inv' => '', 'date' => '2014-11-20'],
];

foreach ($testCases as $tc) {
    $r = $db->getTaxRuleForInvoice($tc['inv'], $tc['date']);
    echo sprintf(
        "Inv: %-10s | Date: %-10s -> Matched By: %-13s | Rate: %-5s | Rule Name: %s\n",
        $tc['inv'],
        $tc['date'],
        $r['matched_by'],
        $r['rate'],
        $r['name']
    );
}
