<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$invoicesWithExplicitVat = $pdo->query("
    SELECT DISTINCT invoice_number 
    FROM sales 
    WHERE total_amount > 0 AND (
        item_description LIKE 'VAT%' 
        OR item_description LIKE '%Value Added Tax%'
        OR item_description LIKE '18% VAT%'
        OR item_description LIKE '15% VAT%'
        OR item_description LIKE '12% VAT%'
        OR item_description LIKE '8% VAT%'
    )
")->fetchAll(PDO::FETCH_COLUMN);

echo "Invoices with explicit separate VAT line: " . count($invoicesWithExplicitVat) . "\n";
print_r(array_slice($invoicesWithExplicitVat, 0, 10));
