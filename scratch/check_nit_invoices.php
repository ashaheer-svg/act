<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== All Invoices and Payments for Network Information Technologies ===\n";
$rows = $db->fetchAll("
    SELECT 
        s.invoice_number,
        s.invoice_date,
        s.tax_code,
        SUM(s.total_amount) as line_tot,
        p.amount as pmt_amt,
        p.payment_date
    FROM sales s
    LEFT JOIN payments p ON p.invoice_num = s.invoice_number
    WHERE s.customer_name LIKE '%Network Information Technologies%'
    GROUP BY s.invoice_number
    ORDER BY s.invoice_date DESC
    LIMIT 20
");

foreach ($rows as $r) {
    $line = (float)$r['line_tot'];
    $pmt = (float)$r['pmt_amt'];
    $ratio = $line > 0 && $pmt > 0 ? round($pmt / $line, 4) : 0;
    echo "Inv #{$r['invoice_number']} | Date: {$r['invoice_date']} | Code: {$r['tax_code']} | Line: " . number_format($line, 2) . " | Pmt: " . number_format($pmt, 2) . " | Ratio: $ratio\n";
}
