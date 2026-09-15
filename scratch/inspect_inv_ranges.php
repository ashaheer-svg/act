<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');

echo "=== Sales Invoice Number Ranges ===\n";
$stats = $db->fetch("SELECT count(*) as total, count(distinct invoice_number) as distinct_inv, min(invoice_date) as min_date, max(invoice_date) as max_date FROM sales");
print_r($stats);

echo "\n=== Distinct Prefixes in Invoice Numbers ===\n";
$invoices = $db->fetchAll("SELECT DISTINCT invoice_number FROM sales WHERE invoice_number != ''");
$prefixes = [];
$minByPrefix = [];
$maxByPrefix = [];
$countByPrefix = [];

foreach ($invoices as $row) {
    $inv = $row['invoice_number'];
    if (preg_match('/^([A-Za-z]+)-?(\d+)$/', $inv, $m)) {
        $prefix = strtoupper($m[1]);
        $num = intval($m[2]);
    } else {
        $prefix = 'NON_STANDARD';
        $num = $inv;
    }
    $prefixes[$prefix] = true;
    $countByPrefix[$prefix] = ($countByPrefix[$prefix] ?? 0) + 1;
    if (!isset($minByPrefix[$prefix]) || $num < $minByPrefix[$prefix]) {
        $minByPrefix[$prefix] = $num;
    }
    if (!isset($maxByPrefix[$prefix]) || $num > $maxByPrefix[$prefix]) {
        $maxByPrefix[$prefix] = $num;
    }
}

foreach (array_keys($prefixes) as $p) {
    echo "Prefix: $p | Invoices: {$countByPrefix[$p]} | Min: {$minByPrefix[$p]} | Max: {$maxByPrefix[$p]}\n";
}

echo "\n=== Invoices without Number or Non-Standard ===\n";
$nonStd = $db->fetchAll("SELECT DISTINCT invoice_number, invoice_date, customer_name, qb_amount FROM sales WHERE invoice_number NOT LIKE 'AS%' LIMIT 10");
print_r($nonStd);
