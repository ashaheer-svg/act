<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Sales vat_treatment distribution ===\n";
$rows = $pdo->query("
    SELECT vat_treatment, count(*) as count, round(sum(total_amount), 2) as total, round(sum(base_value), 2) as base, round(sum(vat_component), 2) as vat
    FROM sales 
    GROUP BY vat_treatment
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

echo "\n=== Sales by tax_code and vat_treatment ===\n";
$rows2 = $pdo->query("
    SELECT tax_code, vat_treatment, count(*) as count, round(sum(total_amount), 2) as total, round(sum(base_value), 2) as base, round(sum(vat_component), 2) as vat
    FROM sales 
    GROUP BY tax_code, vat_treatment
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows2);
