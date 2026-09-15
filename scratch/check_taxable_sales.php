<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Non-zero Taxable Sales rows by rate ===\n";
$rows = $pdo->query("
    SELECT applied_tax_rate, count(*) as count, round(sum(total_amount), 2) as total, round(sum(base_value), 2) as base, round(sum(vat_component), 2) as vat
    FROM sales 
    WHERE tax_code = 'Taxable Sales' AND total_amount != 0
    GROUP BY applied_tax_rate
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\n=== Zero rows count ===\n";
$zero = $pdo->query("
    SELECT count(*) FROM sales WHERE total_amount = 0
")->fetchColumn();
echo "Zero total_amount rows: $zero\n";

echo "\n=== Non-zero rows in VAT_EXEMPT ===\n";
$exemptNonZero = $pdo->query("
    SELECT count(*), round(sum(total_amount), 2) as total
    FROM sales 
    WHERE vat_treatment = 'VAT_EXEMPT' AND total_amount != 0
")->fetchAll(PDO::FETCH_ASSOC);
print_r($exemptNonZero);
