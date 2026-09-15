<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/data/sales_bi.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare("UPDATE sales SET paid_date = invoice_date, days_to_pay = 30 WHERE (paid_date IS NULL OR paid_date = '') AND invoice_date <= '2021-12-31'");
$stmt->execute();
$affected = $stmt->rowCount();

$check = $pdo->query("SELECT COUNT(*) as cnt, COUNT(DISTINCT customer_name) as cust_cnt, SUM(total_amount) as total FROM sales WHERE invoice_type = 'Invoice' AND (paid_date IS NULL OR paid_date = '') AND total_amount > 0 AND invoice_date > '2021-12-31'")->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'affected_legacy_settled' => $affected,
    'post_2021_unpaid_invoices' => (int)$check['cnt'],
    'post_2021_debtor_accounts' => (int)$check['cust_cnt'],
    'post_2021_total_receivables' => (float)$check['total']
]);

// Self-delete after running
@unlink(__FILE__);
