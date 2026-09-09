<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== Comparing Invoice Amount vs Payment Received for Taxable Invoices ===\n";

$cols = $db->fetchAll("PRAGMA table_info(payments)");
echo "Payments columns: " . implode(', ', array_column($cols, 'name')) . "\n\n";

$query = "
    SELECT 
        s.invoice_number,
        s.invoice_date,
        s.customer_name,
        s.tax_code,
        SUM(s.total_amount) as recorded_inv_amount,
        p.amount as payment_amount,
        p.payment_date
    FROM sales s
    JOIN payments p ON p.invoice_num = s.invoice_number
    WHERE s.tax_code = 'Tax' AND s.total_amount > 1000
    GROUP BY s.invoice_number
    ORDER BY s.invoice_date DESC
    LIMIT 25
";

$rows = $db->fetchAll($query);
foreach ($rows as $r) {
    $invAmt = (float)$r['recorded_inv_amount'];
    $payAmt = (float)$r['payment_amount'];
    $ratio = $invAmt > 0 ? round($payAmt / $invAmt, 4) : 0;
    
    // Check if payment == invAmt * 1.18 (meaning invoice line was Plus VAT!)
    // Or if payment == invAmt (meaning invoice line was VAT inclusive!)
    $plusVatExpected = round($invAmt * 1.18, 2);
    $diffFromInclusive = abs($payAmt - $invAmt);
    $diffFromPlusVat = abs($payAmt - $plusVatExpected);
    
    $verdict = "UNKNOWN";
    if ($diffFromPlusVat < 2.0) {
        $verdict = "PROVEN PLUS VAT (Payment = Line + 18% VAT)";
    } elseif ($diffFromInclusive < 2.0) {
        $verdict = "PROVEN VAT INCLUSIVE (Payment = Line Total)";
    }
    
    echo "Inv #{$r['invoice_number']} ({$r['invoice_date']}) - {$r['customer_name']}\n";
    echo "  Line Amt in DB: " . number_format($invAmt, 2) . " | Payment: " . number_format($payAmt, 2) . " (Ratio: $ratio)\n";
    echo "  Expected if Plus VAT (x1.18): " . number_format($plusVatExpected, 2) . "\n";
    echo "  Verdict: $verdict\n\n";
}
