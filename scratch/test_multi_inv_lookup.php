<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$serial = '0W20011600261';

$sql = "
    SELECT 
        s.invoice_number,
        MIN(s.invoice_date) as invoice_date,
        s.customer_name,
        MAX(CASE WHEN s.item_description LIKE :sn_wild THEN s.item_description ELSE NULL END) as matching_line_desc,
        SUM(s.total_amount) as total_invoice_amount,
        MAX(s.paid_date) as paid_date,
        MAX(s.days_to_pay) as days_to_pay,
        MAX(s.sales_rep_code) as sales_rep_code
    FROM sales s
    WHERE s.invoice_number IN (SELECT invoice_number FROM hardware_assets WHERE serial_number = :sn)
       OR s.item_description LIKE :sn_wild
    GROUP BY s.invoice_number
    ORDER BY invoice_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':sn' => $serial, ':sn_wild' => '%' . $serial . '%']);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($invoices);
