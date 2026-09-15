<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "Starting remote hosting subscriptions synchronization...\n";

$rows = $pdo->query("
    SELECT s.invoice_number, s.customer_name, s.invoice_date, s.item_description, s.total_amount, s.end_customer
    FROM sales s
    LEFT JOIN software_subscriptions ss ON s.invoice_number = ss.invoice_number
    WHERE (LOWER(s.item_description) LIKE '%hosting%' OR LOWER(s.item_description) LIKE '%domain%')
      AND s.invoice_date >= '2025-01-01'
      AND ss.id IS NULL
    ORDER BY s.invoice_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($rows) . " hosting lines to register.\n";

$insertStmt = $pdo->prepare("
    INSERT INTO software_subscriptions (
        invoice_number, customer_name, software_name, edition_tier,
        license_seats, period_start_date, period_end_date, term_months,
        renewal_status, renewal_opportunity_value, end_customer
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$inserted = 0;
$todayStr = "2026-09-13";

foreach ($rows as $r) {
    $invNum = $r['invoice_number'];
    $cust = $r['customer_name'];
    $invDateStr = $r['invoice_date'];
    $desc = $r['item_description'];
    $amount = (float)$r['total_amount'];
    $endCust = $r['end_customer'];

    $isMonthly = (stripos($desc, 'month') !== false || stripos($desc, 'email account') !== false || $amount < 10000);
    $termMonths = $isMonthly ? 1 : 12;

    $startDate = new DateTime($invDateStr);
    $endDate = clone $startDate;
    $endDate->modify("+{$termMonths} month");
    $endDateStr = $endDate->format('Y-m-d');

    $status = 'ACTIVE';
    if ($endDateStr < $todayStr) {
        $status = 'EXPIRED';
    } elseif ((strtotime($endDateStr) - strtotime($todayStr)) / 86400 <= 60) {
        $status = 'DUE_SOON';
    }

    $tier = 'Web & Email Hosting';
    $seats = 1;
    if (stripos($desc, '10 email') !== false || stripos($desc, '10 e-mail') !== false) {
        $seats = 10;
    } elseif (stripos($desc, '20 email') !== false) {
        $seats = 20;
    }

    $insertStmt->execute([
        $invNum, $cust, $desc, $tier,
        $seats, $invDateStr, $endDateStr, $termMonths,
        $status, $amount, $endCust
    ]);
    $inserted++;
}

echo "Inserted $inserted hosting subscriptions.\n";
$totalCount = $pdo->query("SELECT count(*) FROM software_subscriptions")->fetchColumn();
echo "Total software_subscriptions count on server: $totalCount\n";
