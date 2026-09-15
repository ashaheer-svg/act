<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Let's find every invoice in 153906 (Old QB)
$f = fopen(__DIR__ . '/../Exports/qb_export_invoices_2026-09-09_153906.csv', 'r');
$hdr = fgetcsv($f, 0, ',', '"', '\\');
$oldInvs = [];
while ($row = fgetcsv($f, 0, ',', '"', '\\')) {
    if (count($row) < 3) continue;
    $inv = trim($row[2] ?? '');
    $date = trim($row[1] ?? '');
    $cust = trim($row[3] ?? '');
    $isPaid = trim($row[21] ?? '');
    $bal = trim($row[20] ?? '');
    if (!empty($inv)) {
        $oldInvs[$inv] = [
            'date' => $date,
            'cust' => $cust,
            'qb_is_paid' => $isPaid,
            'qb_bal' => $bal
        ];
    }
}
fclose($f);

echo "Total unique invoice numbers in Old QB export (153906): " . count($oldInvs) . "\n";

// Let's check which invoices in DB came from Old QB export (153906)
$stmt = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, 
           ROUND(SUM(total_amount), 2) as gross_amount,
           ROUND(SUM(base_value), 2) as base_amount,
           ROUND(SUM(vat_component), 2) as vat_amount,
           paid_date, days_to_pay
    FROM sales
    WHERE paid_date IS NULL OR paid_date = ''
    GROUP BY invoice_number
");
$dbUnpaid = $stmt->fetchAll(PDO::FETCH_ASSOC);

$oldUnpaidInDb = [];
$otherUnpaidInDb = [];

foreach ($dbUnpaid as $row) {
    $inv = $row['invoice_number'];
    if (isset($oldInvs[$inv])) {
        $row['old_qb_info'] = $oldInvs[$inv];
        $oldUnpaidInDb[] = $row;
    } else {
        $otherUnpaidInDb[] = $row;
    }
}

echo "Total unpaid invoices in DB: " . count($dbUnpaid) . "\n";
echo "  Unpaid invoices present in Old QB export (153906): " . count($oldUnpaidInDb) . "\n";
echo "  Unpaid invoices NOT in Old QB export: " . count($otherUnpaidInDb) . "\n\n";

if (count($oldUnpaidInDb) > 0) {
    echo "=== UNPAID INVOICES FROM OLD QB EXPORT (153906) ===\n";
    $totGross = 0;
    foreach ($oldUnpaidInDb as $u) {
        $totGross += $u['gross_amount'];
        echo sprintf(
            "Inv: %-12s | Date: %s | Gross: LKR %12s | QB_IsPaid: %-5s | QB_Bal: %-10s | Cust: %s\n",
            $u['invoice_number'],
            $u['invoice_date'],
            number_format($u['gross_amount'], 2),
            $u['old_qb_info']['qb_is_paid'],
            $u['old_qb_info']['qb_bal'],
            $u['customer_name']
        );
    }
    echo "TOTAL Gross for Old QB Unpaid: LKR " . number_format($totGross, 2) . "\n\n";
}

echo "=== UNPAID INVOICES BY YEAR IN DB ===\n";
$unpaidByYear = $pdo->query("
    SELECT substr(invoice_date, 1, 4) as yr,
           COUNT(DISTINCT invoice_number) as inv_count,
           ROUND(SUM(total_amount), 2) as gross_total
    FROM sales
    WHERE paid_date IS NULL OR paid_date = ''
    GROUP BY yr
    ORDER BY yr
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($unpaidByYear as $y) {
    echo sprintf("Year %s: %3d invoices | Total Gross: LKR %14s\n", $y['yr'], $y['inv_count'], number_format($y['gross_total'], 2));
}
