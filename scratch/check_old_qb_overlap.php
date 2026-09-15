<?php
ini_set('display_errors', '0');
error_reporting(0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Let's read the old QB CSV first 50 lines to see if there's an identifying column or pattern
$fOld = __DIR__ . '/../Exports/qb_export_invoices_2026-09-09_153906.csv';
$h = fopen($fOld, 'r');
$hdrOld = fgetcsv($h);
fclose($h);

$fNew = __DIR__ . '/../Exports/qb_export_invoices_2026-09-09_160211.csv';
$h2 = fopen($fNew, 'r');
$hdrNew = fgetcsv($h2);
fclose($h2);

echo "Old CSV Headers:\n" . implode(', ', $hdrOld) . "\n\n";
echo "New CSV Headers:\n" . implode(', ', $hdrNew) . "\n\n";

// Let's check the 62 unpaid invoices in 2022-2025:
$unpaidOlder = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount, qb_txn_id
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date < '2026-01-01'
      AND total_amount > 0
    ORDER BY invoice_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total unpaid invoices before 2026: " . count($unpaidOlder) . "\n";
$customersUnpaid = [];
foreach ($unpaidOlder as $u) {
    $c = $u['customer_name'];
    $customersUnpaid[$c] = ($customersUnpaid[$c] ?? 0) + $u['total_amount'];
}
arsort($customersUnpaid);
echo "\nTop debtor customers before 2026:\n";
foreach (array_slice($customersUnpaid, 0, 10, true) as $cust => $amt) {
    echo sprintf(" - %-35s: LKR %12s\n", $cust, number_format($amt, 2));
}

// Check remote database if accessible or compare remote unpaid
