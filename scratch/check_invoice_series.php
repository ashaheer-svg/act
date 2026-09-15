<?php
ini_set('display_errors', '0');
error_reporting(0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== UNPAID INVOICES DETAILS ===\n";
$unpaid = $pdo->query("
    SELECT 
        strftime('%Y', invoice_date) as yr,
        COUNT(DISTINCT invoice_number) as inv_count,
        COUNT(*) as line_count,
        ROUND(SUM(total_amount), 2) as total_unpaid_gross,
        ROUND(SUM(base_value), 2) as total_unpaid_base,
        ROUND(SUM(vat_component), 2) as total_unpaid_vat
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND total_amount > 0
    GROUP BY yr
    ORDER BY yr ASC
")->fetchAll(PDO::FETCH_ASSOC);

print_r($unpaid);

echo "\n=== UNPAID INVOICE NUMBERS SAMPLE (2022-2025) ===\n";
$samples = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount, paid_date, qb_txn_id
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date < '2026-01-01'
      AND total_amount > 0
    ORDER BY invoice_date ASC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($samples as $s) {
    echo "{$s['invoice_number']} | Date: {$s['invoice_date']} | Cust: {$s['customer_name']} | Amt: {$s['total_amount']} | TxnID: {$s['qb_txn_id']}\n";
}

echo "\n=== CHECK PRE-2022 (OLD QB 2009-2021) ===\n";
$pre2022 = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as total_inv,
        COUNT(DISTINCT CASE WHEN paid_date IS NOT NULL AND paid_date != '' THEN invoice_number END) as paid_inv,
        COUNT(DISTINCT CASE WHEN paid_date IS NULL OR paid_date = '' THEN invoice_number END) as unpaid_inv
    FROM sales
    WHERE invoice_date <= '2021-12-31'
")->fetch(PDO::FETCH_ASSOC);
print_r($pre2022);

echo "\n=== CHECK REMOTE LIVE PRODUCTION UNPAID INVOICES ===\n";
// Let's see if we can check act.active.lk or if remote DB has different stats
