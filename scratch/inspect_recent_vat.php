<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== CHECKING RECENT INVOICES (2024+) FOR VAT BREAKUP VS NO BREAKUP ===\n";
// Let's find invoices with Tax tax_code
$invoices = $db->fetchAll("
    SELECT invoice_number, invoice_date, customer_name, COUNT(*) as cnt, SUM(total_amount) as total
    FROM sales
    WHERE invoice_date >= '2024-01-01'
    GROUP BY invoice_number
    ORDER BY invoice_date DESC
    LIMIT 10
");

foreach ($invoices as $inv) {
    echo "\n-----------------------------------------\n";
    echo "Invoice: {$inv['invoice_number']} | Date: {$inv['invoice_date']} | Customer: {$inv['customer_name']} | Total: {$inv['total']}\n";
    $lines = $db->fetchAll("
        SELECT item_description, tax_code, quantity, total_amount, base_value, vat_component, applied_tax_rate
        FROM sales
        WHERE invoice_number = ?
    ", [$inv['invoice_number']]);
    foreach ($lines as $l) {
        echo sprintf("  [%-10s] %-50s | Qty: %2s | Amt: %10.2f | Base: %10.2f | VAT: %10.2f | Rate: %s\n",
            $l['tax_code'],
            substr(str_replace("\n", " ", $l['item_description']), 0, 50),
            $l['quantity'],
            $l['total_amount'],
            $l['base_value'],
            $l['vat_component'],
            $l['applied_tax_rate']
        );
    }
}
