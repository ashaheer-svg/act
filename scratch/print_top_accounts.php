<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$accounts = $db->query("
    SELECT 
        s.customer_name,
        COUNT(DISTINCT s.invoice_number) as invoice_count,
        SUM(DISTINCT s.total_amount) as total_revenue,
        (SELECT COUNT(*) FROM hardware_assets ha WHERE ha.customer_name = s.customer_name) as hw_count,
        (SELECT COUNT(*) FROM software_subscriptions ss WHERE ss.customer_name = s.customer_name) as sub_count
    FROM sales s
    INNER JOIN invoice_items ii ON s.invoice_number = ii.invoice_number
    GROUP BY s.customer_name
    ORDER BY total_revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total Unique Accounts in 100 Sample Invoices: " . count($accounts) . "\n\n";
echo sprintf("%-42s | %-8s | %-16s | %-8s | %-8s\n", "Customer / Account Name", "Invoices", "Revenue (LKR)", "Hardware", "Subs/MAs");
echo str_repeat('-', 90) . "\n";
foreach ($accounts as $acc) {
    echo sprintf("%-42s | %8d | %16s | %8d | %8d\n", substr($acc['customer_name'], 0, 42), $acc['invoice_count'], number_format($acc['total_revenue'], 2), $acc['hw_count'], $acc['sub_count']);
}
