<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== Checking Payment Ratios Across Eras and Tax Codes ===\n";

$eras = [
    '2026 AS000xxx' => "invoice_number LIKE 'AS0000%'",
    '2024-2026 AS010xxx (18% era)' => "invoice_number BETWEEN 'AS010021' AND 'AS011260'",
    '2021-2023 Exempt Era' => "invoice_number BETWEEN 'AS008212' AND 'AS010020'",
    '2018-2020 15% Era' => "invoice_number BETWEEN 'AS006561' AND 'AS008154'",
];

foreach ($eras as $eraName => $where) {
    echo "\n--- ERA: $eraName ---\n";
    $query = "
        SELECT 
            s.tax_code,
            COUNT(DISTINCT s.invoice_number) as inv_count,
            ROUND(AVG(p.amount / s.tot_amt), 4) as avg_ratio,
            SUM(CASE WHEN ABS((p.amount / s.tot_amt) - 1.0) < 0.01 THEN 1 ELSE 0 END) as count_ratio_1_00,
            SUM(CASE WHEN ABS((p.amount / s.tot_amt) - 1.18) < 0.01 THEN 1 ELSE 0 END) as count_ratio_1_18,
            SUM(CASE WHEN ABS((p.amount / s.tot_amt) - 1.15) < 0.01 THEN 1 ELSE 0 END) as count_ratio_1_15,
            SUM(CASE WHEN ABS((p.amount / s.tot_amt) - 1.12) < 0.01 THEN 1 ELSE 0 END) as count_ratio_1_12
        FROM (
            SELECT invoice_number, tax_code, SUM(total_amount) as tot_amt
            FROM sales
            WHERE $where AND total_amount > 100
            GROUP BY invoice_number
        ) s
        JOIN payments p ON p.invoice_num = s.invoice_number
        GROUP BY s.tax_code
    ";
    $res = $db->fetchAll($query);
    foreach ($res as $r) {
        echo "  TaxCode: {$r['tax_code']} | Invs with Pmt: {$r['inv_count']} | Avg Ratio: {$r['avg_ratio']} | Ratio 1.00 (Inclusive/Exempt): {$r['count_ratio_1_00']} | Ratio 1.18 (Plus 18%): {$r['count_ratio_1_18']} | Ratio 1.15: {$r['count_ratio_1_15']} | Ratio 1.12: {$r['count_ratio_1_12']}\n";
    }
}
