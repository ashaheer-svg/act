<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Let's inspect all 2026 unpaid invoices:
$inv2026 = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, ROUND(SUM(total_amount),2) as gross
    FROM sales
    WHERE (paid_date IS NULL OR paid_date = '')
      AND invoice_date >= '2026-01-01'
      AND total_amount > 0
    GROUP BY invoice_number
    ORDER BY invoice_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total 2026 Unpaid Invoices: " . count($inv2026) . "\n";
$tot2026 = 0;
foreach ($inv2026 as $r) {
    $tot2026 += $r['gross'];
    if (stripos($r['customer_name'], 'EITS') !== false || stripos($r['customer_name'], 'E-Net') !== false) {
        echo " - 2026 E-Net/EITS: {$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | LKR {$r['gross']}\n";
    }
}
echo "Total 2026 Unpaid Gross: LKR " . number_format($tot2026, 2) . "\n";
