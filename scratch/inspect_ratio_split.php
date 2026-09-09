<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== Taxable Invoices where Payment == Line Amount (Ratio 1.00) ===\n";
$rows100 = $db->fetchAll("
    SELECT s.invoice_number, s.invoice_date, s.customer_name, s.item_description, s.tax_code, SUM(s.total_amount) as line_tot, p.amount as pmt
    FROM sales s
    JOIN payments p ON p.invoice_num = s.invoice_number
    WHERE s.tax_code = 'Tax' AND s.total_amount > 1000 AND ABS((p.amount / s.total_amount) - 1.0) < 0.01
    GROUP BY s.invoice_number
    ORDER BY s.invoice_date DESC
    LIMIT 10
");
foreach ($rows100 as $r) {
    echo "{$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | Line: {$r['line_tot']} | Pmt: {$r['pmt']}\n";
    echo "  Desc: " . substr($r['item_description'], 0, 70) . "\n";
}

echo "\n=== Taxable Invoices where Payment == Line Amount * 1.18 (Ratio 1.18) ===\n";
$rows118 = $db->fetchAll("
    SELECT s.invoice_number, s.invoice_date, s.customer_name, s.item_description, s.tax_code, SUM(s.total_amount) as line_tot, p.amount as pmt
    FROM sales s
    JOIN payments p ON p.invoice_num = s.invoice_number
    WHERE s.tax_code = 'Tax' AND s.total_amount > 1000 AND ABS((p.amount / (s.total_amount * 1.18)) - 1.0) < 0.01
    GROUP BY s.invoice_number
    ORDER BY s.invoice_date DESC
    LIMIT 10
");
foreach ($rows118 as $r) {
    echo "{$r['invoice_number']} | {$r['invoice_date']} | {$r['customer_name']} | Line: {$r['line_tot']} | Pmt: {$r['pmt']}\n";
    echo "  Desc: " . substr($r['item_description'], 0, 70) . "\n";
}
