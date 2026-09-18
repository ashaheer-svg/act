<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== Invoices for Avian ===\n";
$invs = $db->fetchAll("SELECT invoice_number, invoice_date, invoice_type, total_amount, applied_amount, balance_remaining, is_paid FROM sales WHERE customer_name = 'Avian' GROUP BY invoice_number ORDER BY invoice_date ASC");
foreach ($invs as $inv) {
    echo "• [{$inv['invoice_type']}] {$inv['invoice_number']} ({$inv['invoice_date']}): Total: " . number_format($inv['total_amount'], 2) . " | Applied: " . number_format($inv['applied_amount'], 2) . " | Bal: " . number_format($inv['balance_remaining'], 2) . " | Paid: {$inv['is_paid']}\n";
}

echo "\n=== Payments for Avian ===\n";
$pays = $db->fetchAll("SELECT payment_date, reference_num, amount, invoice_num, payment_method FROM payments WHERE customer_name = 'Avian' ORDER BY payment_date ASC");
foreach ($pays as $p) {
    echo "• Payment {$p['reference_num']} ({$p['payment_date']}): Amount: " . number_format($p['amount'], 2) . " | Inv: {$p['invoice_num']} | Method: {$p['payment_method']}\n";
}
