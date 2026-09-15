<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');

echo "=== Testing Old QB Invoice Scenarios ===\n";

// Scenario 1: Invoice AS000001 from 2009 vs AS000001 from 2026
$rule2009 = $db->getTaxRuleForInvoice('AS000001', '2009-08-15');
$rule2026 = $db->getTaxRuleForInvoice('AS000001', '2026-07-02');

echo "1. Invoice Number Overlap (AS000001):\n";
echo "   2009-08-15 rule: " . json_encode($rule2009) . "\n";
echo "   2026-07-02 rule: " . json_encode($rule2026) . "\n";

// Scenario 2: Unnumbered / Opening balance invoice
$ruleOB = $db->getTaxRuleForInvoice('OB-001', '2010-01-01');
echo "\n2. Opening balance / Unnumbered (OB-001, 2010-01-01):\n";
echo "   Rule: " . json_encode($ruleOB) . "\n";

// Scenario 3: Export tax code
$taxCodes = ['Tax', 'Non', 'Non VAT', 'VAT15%', 'NBT 1%', 'Export', 'Zero'];
echo "\n3. Tax Code Evaluation:\n";
foreach ($taxCodes as $tc) {
    $isExempt = ($tc == '' || stripos($tc, 'Non') !== false || stripos($tc, 'Zero') !== false || stripos($tc, 'Exempt') !== false || stripos($tc, 'Export') !== false);
    echo sprintf("   Tax Code: %-10s -> Exempt/0%%: %s\n", $tc, $isExempt ? 'YES' : 'NO');
}

// Scenario 4: Historical invoices in sequence AS004001 - AS010020
echo "\n4. Historical Sequence Rules Check:\n";
$samples = [
    ['AS004500', '2014-05-01'], // 12%
    ['AS005500', '2015-08-01'], // 0%
    ['AS007200', '2017-03-01'], // 15%
    ['AS008180', '2019-12-15'], // 8%
    ['AS009500', '2020-08-01'], // 0%
];
foreach ($samples as $s) {
    $r = $db->getTaxRuleForInvoice($s[0], $s[1]);
    echo sprintf("   Inv: %-10s (%s) -> Rate: %-5s (%s)\n", $s[0], $s[1], $r['rate'], $r['name']);
}
