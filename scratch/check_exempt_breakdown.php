<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Non-zero VAT_EXEMPT rows by year and tax_code ===\n";
$rows = $pdo->query("
    SELECT strftime('%Y', invoice_date) as yr, tax_code, count(*) as count, round(sum(total_amount), 2) as total
    FROM sales 
    WHERE vat_treatment = 'VAT_EXEMPT' AND total_amount != 0
    GROUP BY yr, tax_code
    ORDER BY yr ASC
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
