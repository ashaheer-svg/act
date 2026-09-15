<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$higherAs = $pdo->query("
    SELECT invoice_number, invoice_date, customer_name, total_amount
    FROM sales
    WHERE invoice_number >= 'AS000103' AND invoice_number <= 'AS000200'
      AND strftime('%Y', invoice_date) = '2026'
")->fetchAll(PDO::FETCH_ASSOC);

echo "2026 AS invoices between AS000103 and AS000200: " . count($higherAs) . "\n";
print_r($higherAs);
