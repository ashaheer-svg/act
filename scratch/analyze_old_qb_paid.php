<?php
error_reporting(0);
ini_set('display_errors', '0');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Load old invoice numbers from 153906
$oldCsv = __DIR__ . '/../Exports/qb_export_invoices_2026-09-09_153906.csv';
$fh = fopen($oldCsv, 'r');
$hdr = fgetcsv($fh);
$numIdx = 0;
$dateIdx = 1;
foreach ($hdr as $i => $col) {
    $c = strtolower(trim($col, "\xEF\xBB\xBF"));
    if ($c === 'refnumber' || strpos($c, 'num') !== false) $numIdx = $i;
    if ($c === 'txndate' || strpos($c, 'date') !== false) $dateIdx = $i;
}
$oldInvoices = [];
while (($r = fgetcsv($fh)) !== false) {
    $num = trim($r[$numIdx] ?? '');
    $date = trim($r[$dateIdx] ?? '');
    if (!empty($num)) {
        $oldInvoices[$num] = $date;
    }
}
fclose($fh);
echo "Total distinct invoice numbers in Old QB export (153906): " . count($oldInvoices) . "\n";

// Load new invoice numbers from 160211
$newCsv = __DIR__ . '/../Exports/qb_export_invoices_2026-09-09_160211.csv';
$fh2 = fopen($newCsv, 'r');
$hdr2 = fgetcsv($fh2);
$numIdx2 = 0;
$dateIdx2 = 1;
foreach ($hdr2 as $i => $col) {
    $c = strtolower(trim($col, "\xEF\xBB\xBF"));
    if ($c === 'refnumber' || strpos($c, 'num') !== false) $numIdx2 = $i;
    if ($c === 'txndate' || strpos($c, 'date') !== false) $dateIdx2 = $i;
}
$newInvoices = [];
while (($r = fgetcsv($fh2)) !== false) {
    $num = trim($r[$numIdx2] ?? '');
    $date = trim($r[$dateIdx2] ?? '');
    if (!empty($num)) {
        $newInvoices[$num] = $date;
    }
}
fclose($fh2);
echo "Total distinct invoice numbers in New QB export (160211): " . count($newInvoices) . "\n";

// Only in Old QB
$onlyOld = array_diff_key($oldInvoices, $newInvoices);
echo "Invoices ONLY in Old QB: " . count($onlyOld) . "\n";
$inBoth = array_intersect_key($oldInvoices, $newInvoices);
echo "Invoices in BOTH Old and New QB: " . count($inBoth) . "\n";
$onlyNew = array_diff_key($newInvoices, $oldInvoices);
echo "Invoices ONLY in New QB: " . count($onlyNew) . "\n";

// Check in sales table: how many invoices currently in DB match Old QB
// And how many are UNPAID
$allDbInvoices = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount, paid_date
    FROM sales
")->fetchAll(PDO::FETCH_ASSOC);

$oldInDb = [];
$oldUnpaidInDb = [];
$newInDb = [];
$newUnpaidInDb = [];

foreach ($allDbInvoices as $r) {
    $num = $r['invoice_number'];
    $isPaid = (!empty($r['paid_date']));
    
    if (isset($oldInvoices[$num])) {
        $oldInDb[$num] = true;
        if (!$isPaid) {
            $oldUnpaidInDb[$num] = $r;
        }
    }
    if (isset($newInvoices[$num])) {
        $newInDb[$num] = true;
        if (!$isPaid) {
            $newUnpaidInDb[$num] = $r;
        }
    }
}

echo "\nIn DB matching Old QB: " . count($oldInDb) . " invoices\n";
echo "Of those, currently UNPAID in DB: " . count($oldUnpaidInDb) . " invoices\n";
if (count($oldUnpaidInDb) > 0) {
    echo "Sample unpaid Old QB invoices:\n";
    $i = 0;
    foreach ($oldUnpaidInDb as $num => $row) {
        echo " - Inv: $num | Date: {$row['invoice_date']} | Customer: {$row['customer_name']} | Amount: {$row['total_amount']}\n";
        $i++;
        if ($i >= 15) break;
    }
}

// Check all unpaid invoices currently in DB
$allUnpaid = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
    GROUP BY invoice_number
    ORDER BY invoice_date ASC
")->fetchAll(PDO::FETCH_ASSOC);
echo "\nTotal UNPAID invoices in entire DB: " . count($allUnpaid) . "\n";
echo "Unpaid by year in DB:\n";
$unpaidByYear = [];
foreach ($allUnpaid as $u) {
    $yr = substr($u['invoice_date'], 0, 4);
    $unpaidByYear[$yr] = ($unpaidByYear[$yr] ?? 0) + 1;
}
ksort($unpaidByYear);
foreach ($unpaidByYear as $yr => $cnt) {
    echo "  $yr: $cnt invoices\n";
}
