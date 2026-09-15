<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$res = $pdo->query("
    SELECT serial_number, COUNT(DISTINCT invoice_number) as inv_c, GROUP_CONCAT(DISTINCT invoice_number) as invoices, customer_name, product_name
    FROM hardware_assets 
    WHERE serial_number != '' AND serial_number != 'UNASSIGNED'
    GROUP BY serial_number 
    HAVING inv_c > 1 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
