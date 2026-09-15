<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$pdo = $db->getConnection();

$rulesToAdd = [
    ['Legacy 12% VAT (2009-2013)', 0.12, '2009-01-01', '2013-12-31', 'AS000001', 'AS004000', 1, 'Historical statutory 12% VAT (Old Seq AS000001-AS004000)'],
    ['Historical Date 12% VAT', 0.12, '2009-01-01', '2014-12-31', null, null, 1, 'Statutory 12% VAT date fallback (2009-2014)'],
    ['Historical Date 0% VAT', 0.00, '2015-01-01', '2016-10-31', null, null, 0, 'Statutory exempt date fallback (2015-2016)'],
    ['Historical Date 15% VAT', 0.15, '2016-11-01', '2019-11-30', null, null, 1, 'Statutory 15% VAT date fallback (2016-2019)'],
    ['Historical Date 8% VAT', 0.08, '2019-12-01', '2022-05-31', null, null, 1, 'Statutory 8% VAT date fallback (2019-2022)'],
    ['Historical Date 12% VAT', 0.12, '2022-06-01', '2022-08-31', null, null, 1, 'Statutory 12% VAT date fallback (mid-2022)'],
    ['Historical Date 15% VAT', 0.15, '2022-09-01', '2023-12-31', null, null, 1, 'Statutory 15% VAT date fallback (late 2022-2023)']
];

$stmt = $pdo->prepare("
    INSERT INTO tax_rules (tax_name, tax_rate, effective_from, effective_to, invoice_range_start, invoice_range_end, is_inclusive_default, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($rulesToAdd as $r) {
    $exists = $db->fetch("SELECT id FROM tax_rules WHERE tax_name = ?", [$r[0]]);
    if (!$exists) {
        $stmt->execute($r);
        echo "Added rule: {$r[0]}\n";
    } else {
        echo "Rule already exists: {$r[0]}\n";
    }
}

echo "\nRe-running Test Scenarios:\n";
$testCases = [
    // Old system invoices (2009 - 2015)
    ['inv' => 'AS000001', 'date' => '2009-08-15'], // Old system AS000001
    ['inv' => 'AS000001', 'date' => '2026-07-02'], // New system AS000001
    ['inv' => 'AS000120', 'date' => '2010-05-15'], // Old system in 2009-2013 range
    ['inv' => 'AS002500', 'date' => '2012-08-20'], // Old system in 2009-2013 range
    ['inv' => 'AS004500', 'date' => '2014-03-10'], // In range AS004001 - AS005147 (12%)
    ['inv' => 'AS005500', 'date' => '2015-06-15'], // In range AS005148 - AS006560 (0%)
    ['inv' => 'AS007000', 'date' => '2018-01-20'], // In range AS006561 - AS008154 (15%)
    ['inv' => 'AS008180', 'date' => '2019-12-01'], // In range AS008155 - AS008211 (8%)
    ['inv' => 'AS009000', 'date' => '2020-05-15'], // In range AS008212 - AS010020 (0%)
    ['inv' => 'AS010100', 'date' => '2022-09-01'], // 15%
    ['inv' => 'AS010600', 'date' => '2024-04-01'], // 18%
    // Unnumbered or differently prefixed invoices from old system
    ['inv' => '1025', 'date' => '2011-06-01'], // 12% by date
    ['inv' => 'INV-0042', 'date' => '2017-09-15'], // 15% by date
    ['inv' => 'OB-001', 'date' => '2020-02-20'], // 8% by date
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
