<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

$f1 = fopen('Exports/qb_export_invoices_2026-09-09_153906.csv', 'r');
$h1 = fgetcsv($f1, null, ',', '"', '');
$dateIdx = 1;
$numIdx = 2;
$nameIdx = 3;
$isPaidIdx = 21;
$balIdx = 20;

$min1 = '9999-99-99'; $max1 = '0000-00-00'; $c1 = 0; $inv1 = []; $inv1Paid = [];
while ($r = fgetcsv($f1, null, ',', '"', '')) {
    $inv = trim($r[$numIdx] ?? '');
    $d = trim($r[$dateIdx] ?? '');
    $isp = trim($r[$isPaidIdx] ?? '');
    $bal = trim($r[$balIdx] ?? '');
    if ($d && $d < $min1) $min1 = $d;
    if ($d && $d > $max1) $max1 = $d;
    if ($inv) {
        $inv1[$inv] = $d;
        $inv1Paid[$inv] = ['is_paid' => $isp, 'balance' => $bal, 'date' => $d];
    }
    $c1++;
}
fclose($f1);

$f2 = fopen('Exports/qb_export_invoices_2026-09-09_160211.csv', 'r');
$h2 = fgetcsv($f2, null, ',', '"', '');
$min2 = '9999-99-99'; $max2 = '0000-00-00'; $c2 = 0; $inv2 = []; $inv2Paid = [];
while ($r = fgetcsv($f2, null, ',', '"', '')) {
    $inv = trim($r[$numIdx] ?? '');
    $d = trim($r[$dateIdx] ?? '');
    $isp = trim($r[$isPaidIdx] ?? '');
    $bal = trim($r[$balIdx] ?? '');
    if ($d && $d < $min2) $min2 = $d;
    if ($d && $d > $max2) $max2 = $d;
    if ($inv) {
        $inv2[$inv] = $d;
        $inv2Paid[$inv] = ['is_paid' => $isp, 'balance' => $bal, 'date' => $d];
    }
    $c2++;
}
fclose($f2);

echo "Export 1 (153906 - Old QB): lines=$c1, unique_inv=" . count($inv1) . ", min=$min1, max=$max1\n";
echo "Export 2 (160211 - New QB): lines=$c2, unique_inv=" . count($inv2) . ", min=$min2, max=$max2\n";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();
$res = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount, paid_date
    FROM sales
    WHERE paid_date IS NULL OR paid_date = ''
    GROUP BY invoice_number
");
$unpaid = [];
while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
    $unpaid[$r['invoice_number']] = $r;
}

echo "\nTotal unpaid invoices in sales_bi.db: " . count($unpaid) . "\n";

$byCategory = [
    'only_in_old' => [],
    'in_both' => [],
    'only_in_new' => [],
    'neither' => []
];

foreach ($unpaid as $inv => $r) {
    $in1 = isset($inv1[$inv]);
    $in2 = isset($inv2[$inv]);
    if ($in1 && !$in2) {
        $byCategory['only_in_old'][] = $r;
    } elseif ($in1 && $in2) {
        $byCategory['in_both'][] = $r;
    } elseif (!$in1 && $in2) {
        $byCategory['only_in_new'][] = $r;
    } else {
        $byCategory['neither'][] = $r;
    }
}

echo "\nUnpaid Breakdown vs Exports:\n";
echo "  Unpaid ONLY in Old QB (153906): " . count($byCategory['only_in_old']) . " invoices\n";
echo "  Unpaid in BOTH Exports: " . count($byCategory['in_both']) . " invoices\n";
echo "  Unpaid ONLY in New QB (160211): " . count($byCategory['only_in_new']) . " invoices\n";
echo "  Unpaid in NEITHER Export: " . count($byCategory['neither']) . " invoices\n";

echo "\nYear breakdown for Unpaid in Old QB (only_in_old + in_both):\n";
$oldUnpaidAll = array_merge($byCategory['only_in_old'], $byCategory['in_both']);
$yrOld = [];
$valOld = [];
foreach ($oldUnpaidAll as $r) {
    $y = substr($r['invoice_date'], 0, 4);
    $yrOld[$y] = ($yrOld[$y] ?? 0) + 1;
    $valOld[$y] = ($valOld[$y] ?? 0) + $r['total_amount'];
}
ksort($yrOld);
foreach ($yrOld as $y => $c) {
    echo "  $y: $c invoices, LKR " . number_format($valOld[$y], 2) . "\n";
}

echo "\nYear breakdown for Unpaid ONLY in New QB:\n";
$yrNew = [];
$valNew = [];
foreach ($byCategory['only_in_new'] as $r) {
    $y = substr($r['invoice_date'], 0, 4);
    $yrNew[$y] = ($yrNew[$y] ?? 0) + 1;
    $valNew[$y] = ($valNew[$y] ?? 0) + $r['total_amount'];
}
ksort($yrNew);
foreach ($yrNew as $y => $c) {
    echo "  $y: $c invoices, LKR " . number_format($valNew[$y], 2) . "\n";
}
