<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$today = date('Y-m-d');
$sql = "
    SELECT 
        s.customer_name,
        COALESCE(p.customer_type, 'End Customer') as customer_type,
        s.sales_rep_code,
        COUNT(DISTINCT s.invoice_number) as unpaid_invoices_count,
        SUM(s.base_value) as total_base_due,
        SUM(s.vat_component) as total_vat_due,
        SUM(s.total_amount) as total_gross_due,
        MIN(s.invoice_date) as oldest_invoice_date,
        MAX(s.invoice_date) as newest_invoice_date,
        CAST((julianday('$today') - julianday(MIN(s.invoice_date))) AS INT) as max_aging_days
    FROM sales s
    LEFT JOIN customer_profiles p ON s.customer_name = p.customer_name
    WHERE s.invoice_type = 'Invoice'
      AND (s.paid_date IS NULL OR s.paid_date = '')
      AND s.total_amount > 0
      AND s.invoice_date > '2021-12-31'
    GROUP BY s.customer_name
    ORDER BY s.customer_name ASC
    LIMIT 5
";

$res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Customer Level Sample (A-Z):\n";
print_r($res);

// Check first customer's invoices
if (!empty($res)) {
    $firstCust = $res[0]['customer_name'];
    $invSql = "
        SELECT 
            s.invoice_number,
            MIN(s.invoice_date) as invoice_date,
            MAX(s.po_number) as po_number,
            COUNT(*) as line_count,
            SUM(s.base_value) as base_value,
            SUM(s.vat_component) as vat_component,
            SUM(s.total_amount) as total_amount,
            CAST((julianday('$today') - julianday(MIN(s.invoice_date))) AS INT) as aging_days
        FROM sales s
        WHERE s.customer_name = :cust
          AND s.invoice_type = 'Invoice'
          AND (s.paid_date IS NULL OR s.paid_date = '')
          AND s.total_amount > 0
          AND s.invoice_date > '2021-12-31'
        GROUP BY s.invoice_number
        ORDER BY s.invoice_date ASC
    ";
    $stmt = $pdo->prepare($invSql);
    $stmt->execute([':cust' => $firstCust]);
    echo "\nInvoices for '$firstCust':\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
