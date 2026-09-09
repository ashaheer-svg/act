<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$row = $db->fetch("SELECT id, invoice_number, invoice_date, total_amount, tax_code, item_description FROM sales WHERE id = 4546");
$date = $row['invoice_date'];
$amount = floatval($row['total_amount']);
$taxCode = trim($row['tax_code'] ?? '');
$rate = $db->getTaxRateForDate($date);

$isNonTax = (
    stripos($taxCode, 'Non') !== false || 
    stripos($taxCode, 'Zero') !== false || 
    stripos($taxCode, 'Exempt') !== false || 
    stripos($row['item_description'], 'Non VAT') !== false
);

echo "Date: $date\n";
echo "Amount: $amount\n";
echo "TaxCode: '$taxCode'\n";
echo "Rate: $rate\n";
echo "isNonTax: " . ($isNonTax ? 'YES' : 'NO') . "\n";
