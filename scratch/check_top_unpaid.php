<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$res = $pdo->query("
    SELECT customer_name, 
           COUNT(DISTINCT invoice_number) as inv_cnt, 
           ROUND(SUM(total_amount), 2) as total_amt,
           MIN(invoice_date) as oldest_date,
           MAX(invoice_date) as newest_date
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date < '2026-01-01'
      AND total_amount > 0
    GROUP BY customer_name
    ORDER BY total_amt DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Top Unpaid Accounts in 2022-2025 (Total Accounts: " . count($res) . "):\n";
foreach ($res as $r) {
    echo sprintf(
        "%-35s | %2d invs | LKR %12s | %s to %s\n",
        substr($r['customer_name'], 0, 35),
        $r['inv_cnt'],
        number_format($r['total_amt'], 2),
        $r['oldest_date'],
        $r['newest_date']
    );
}
