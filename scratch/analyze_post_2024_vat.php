<?php
require_once __DIR__ . '/../config.php';
$pdo = new PDO('sqlite:' . DATABASE_PATH);

echo "=== Inspecting the 4 Mixed Invoices ===\n";
$q = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount, base_value, vat_component, vat_treatment, sales_tax_item, sales_tax_total
    FROM sales
    WHERE invoice_number IN ('AS010317', 'AS010583', 'AS010832', 'AS011047')
");
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
