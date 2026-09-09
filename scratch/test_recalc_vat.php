<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "Starting historical VAT recalculation...\n";
$start = microtime(true);
$count = $db->recalculateHistoricalVat();
$duration = round(microtime(true) - $start, 2);

echo "Recalculated $count sales lines in {$duration}s.\n";

echo "\n--- Sample 2021 records (should have applied_tax_rate = 0, base = total, vat = 0) ---\n";
$stmt = $db->query("SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, applied_tax_rate FROM sales WHERE invoice_date < '2022-09-01' AND total_amount > 0 LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Sample 2023 records (should have applied_tax_rate = 0.15) ---\n";
$stmt = $db->query("SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, applied_tax_rate FROM sales WHERE invoice_date BETWEEN '2022-09-01' AND '2023-12-31' AND total_amount > 0 LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Sample 2024 records (should have applied_tax_rate = 0.18) ---\n";
$stmt = $db->query("SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, applied_tax_rate FROM sales WHERE invoice_date >= '2024-01-01' AND total_amount > 0 LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
