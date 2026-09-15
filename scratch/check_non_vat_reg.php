<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$rows = $pdo->query("
    SELECT s.invoice_number, s.customer_name, p.is_vat_registered, s.tax_code, count(*) as lines, sum(s.total_amount) as total
    FROM sales s
    JOIN customer_profiles p ON s.customer_name = p.customer_name
    WHERE s.tax_code = 'Non' AND p.is_vat_registered = 1 AND s.total_amount > 0
    GROUP BY s.invoice_number
")->fetchAll(PDO::FETCH_ASSOC);

echo "Invoices with tax_code = 'Non' where customer is_vat_registered = 1: " . count($rows) . "\n";
print_r(array_slice($rows, 0, 5));
