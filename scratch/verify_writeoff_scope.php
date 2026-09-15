<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Any EITS in 2026?
$eits2026 = $pdo->query("
    SELECT * FROM sales 
    WHERE customer_name = 'EITS' AND invoice_date >= '2026-01-01'
")->fetchAll(PDO::FETCH_ASSOC);
echo "EITS in 2026: " . count($eits2026) . " rows\n";

// Any 153906 invoices in 2026?
$f = fopen(__DIR__ . '/../Exports/qb_export_invoices_2026-09-09_153906.csv', 'r');
$h = fgetcsv($f, 0, ',', '"', '');
$oldCsv2026 = 0;
while ($row = fgetcsv($f, 0, ',', '"', '')) {
    if (substr($row[1] ?? '', 0, 4) === '2026') $oldCsv2026++;
}
fclose($f);
echo "153906 CSV rows in 2026: $oldCsv2026\n";

// All pre-2026 unpaid in DB:
$pre2026 = $pdo->query("
    SELECT COUNT(DISTINCT invoice_number) as inv_cnt,
           COUNT(*) as line_cnt,
           ROUND(SUM(total_amount), 2) as gross,
           ROUND(SUM(base_value), 2) as base,
           ROUND(SUM(vat_component), 2) as vat
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date < '2026-01-01'
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);
echo "\nPre-2026 Unpaid Invoices Summary:\n";
print_r($pre2026);

// Of which EITS:
$eitsPre2026 = $pdo->query("
    SELECT COUNT(DISTINCT invoice_number) as inv_cnt,
           COUNT(*) as line_cnt,
           ROUND(SUM(total_amount), 2) as gross
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND customer_name = 'EITS'
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);
echo "\nEITS Unpaid Invoices Summary:\n";
print_r($eitsPre2026);

// Non-EITS pre-2026:
$nonEitsPre2026 = $pdo->query("
    SELECT COUNT(DISTINCT invoice_number) as inv_cnt,
           COUNT(*) as line_cnt,
           ROUND(SUM(total_amount), 2) as gross
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND customer_name != 'EITS'
      AND invoice_date < '2026-01-01'
      AND total_amount > 0
")->fetch(PDO::FETCH_ASSOC);
echo "\nNon-EITS Pre-2026 Unpaid Invoices Summary:\n";
print_r($nonEitsPre2026);
