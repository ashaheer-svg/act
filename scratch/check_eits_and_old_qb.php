<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Check EITS invoices
$eits = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount, paid_date
    FROM sales
    WHERE customer_name LIKE '%EITS%' OR customer_name LIKE '%E-Net%'
    ORDER BY invoice_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total EITS/E-Net invoice rows: " . count($eits) . "\n";
$eitsUnpaid = [];
foreach ($eits as $r) {
    if (empty($r['paid_date'])) {
        $eitsUnpaid[$r['invoice_number']] = $r;
    }
}
echo "Unpaid EITS/E-Net distinct invoices: " . count($eitsUnpaid) . "\n";
foreach ($eitsUnpaid as $inv => $r) {
    echo " - Inv: $inv | Date: {$r['invoice_date']} | Cust: {$r['customer_name']} | Amt: {$r['total_amount']}\n";
}

// Check older QB file (153906)
$f = fopen(__DIR__ . '/../Exports/qb_export_invoices_2026-09-09_153906.csv', 'r');
$h = fgetcsv($f, 0, ',', '"', '');
$oldCsvInvs = [];
while ($row = fgetcsv($f, 0, ',', '"', '')) {
    $inv = trim($row[2] ?? '');
    if (!empty($inv)) {
        $oldCsvInvs[$inv] = true;
    }
}
fclose($f);

echo "\nUnique invoices in 153906 CSV: " . count($oldCsvInvs) . "\n";

// Check which invoices in DB match 153906 and are currently unpaid
$oldCsvUnpaid = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, SUM(total_amount) as gross
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
    GROUP BY invoice_number
")->fetchAll(PDO::FETCH_ASSOC);

$matchOldCsv = [];
$matchPre2026 = [];
foreach ($oldCsvUnpaid as $r) {
    if (isset($oldCsvInvs[$r['invoice_number']])) {
        $matchOldCsv[] = $r;
    }
    if ($r['invoice_date'] < '2026-01-01') {
        $matchPre2026[] = $r;
    }
}

echo "Unpaid in DB matching 153906 CSV directly: " . count($matchOldCsv) . "\n";
foreach ($matchOldCsv as $m) {
    echo " - Match 153906: {$m['invoice_number']} | Date: {$m['invoice_date']} | Cust: {$m['customer_name']} | Gross: {$m['gross']}\n";
}

echo "\nUnpaid in DB with invoice_date < '2026-01-01': " . count($matchPre2026) . " invoices\n";
