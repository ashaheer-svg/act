<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Testing Tax Rule Resolution ===\n";
$testInvs = [
    'ASN000108' => '2026-09-08',
    'ASN000104' => '2026-09-08',
    'AS010996'  => '2026-01-02',
    'AS008000'  => '2018-05-15',
    'AS004500'  => '2014-03-10',
    'AS005500'  => '2015-06-15'
];

foreach ($testInvs as $inv => $date) {
    $rule = $db->getTaxRuleForInvoice($inv, $date);
    echo sprintf("Invoice: %-10s | Date: %s | Rate: %-4s | Matched By: %-13s | Rule: %s\n",
        $inv, $date, $rule['rate'], $rule['matched_by'], $rule['name']
    );
}

echo "\n=== Testing Calculation for ASN000108 Lines ===\n";
$rows = $pdo->query("SELECT id, item_description, tax_code, qb_amount, total_amount FROM sales WHERE invoice_number = 'ASN000108'")->fetchAll(PDO::FETCH_ASSOC);
$rule = $db->getTaxRuleForInvoice('ASN000108', '2026-09-08');
$rate = $rule['rate'];

$totGross = 0;
$totBase = 0;
$totVat = 0;

foreach ($rows as $r) {
    $amt = floatval($r['total_amount'] != 0 ? $r['total_amount'] : $r['qb_amount']);
    if ($amt == 0) {
        $b = 0; $v = 0; $t = 0;
    } else {
        $t = $amt;
        $b = round($amt / (1 + $rate), 2);
        $v = round($t - $b, 2);
    }
    $totGross += $t;
    $totBase += $b;
    $totVat += $v;
    echo sprintf("  Item: %-30s | Raw: %10.2f | Base: %10.2f | VAT: %8.2f | Total: %10.2f\n",
        substr($r['item_description'], 0, 30), $amt, $b, $v, $t
    );
}
echo sprintf("TOTAL: Gross=%10.2f | Base=%10.2f | VAT=%10.2f | Base+VAT=%10.2f\n",
    $totGross, $totBase, $totVat, ($totBase + $totVat)
);

echo "\n=== Testing Calculation for ASN000104 Lines ===\n";
$rows = $pdo->query("SELECT id, item_description, tax_code, qb_amount, total_amount FROM sales WHERE invoice_number = 'ASN000104'")->fetchAll(PDO::FETCH_ASSOC);
$totGross = 0; $totBase = 0; $totVat = 0;
foreach ($rows as $r) {
    $amt = floatval($r['total_amount'] != 0 ? $r['total_amount'] : $r['qb_amount']);
    if ($amt == 0) {
        $b = 0; $v = 0; $t = 0;
    } else {
        $t = $amt;
        $b = round($amt / (1 + $rate), 2);
        $v = round($t - $b, 2);
    }
    $totGross += $t;
    $totBase += $b;
    $totVat += $v;
    echo sprintf("  Item: %-30s | Raw: %10.2f | Base: %10.2f | VAT: %8.2f | Total: %10.2f\n",
        substr($r['item_description'], 0, 30), $amt, $b, $v, $t
    );
}
echo sprintf("TOTAL: Gross=%10.2f | Base=%10.2f | VAT=%10.2f | Base+VAT=%10.2f\n",
    $totGross, $totBase, $totVat, ($totBase + $totVat)
);
