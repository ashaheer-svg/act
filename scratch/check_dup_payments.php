<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$dups = $db->fetchAll("
    SELECT reference_num, invoice_num, payment_date, amount, COUNT(*) as cnt 
    FROM payments 
    GROUP BY reference_num, invoice_num, payment_date, amount 
    HAVING cnt > 1
");

echo "Duplicate payment groups in payments table: " . count($dups) . "\n";
$totalDupRows = 0;
foreach ($dups as $d) {
    $totalDupRows += ($d['cnt'] - 1);
}
echo "Total redundant payment rows: $totalDupRows\n";
