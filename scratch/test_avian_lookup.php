<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$q = '20C0SKRCXAQ44';
$assets = $pdo->query("SELECT * FROM hardware_assets WHERE serial_number = '$q'")->fetchAll(PDO::FETCH_ASSOC);
echo "=== Hardware Asset ===\n";
print_r($assets);

$cust = $assets[0]['customer_name'];
$mas = $pdo->query("SELECT * FROM software_subscriptions WHERE customer_name = '$cust'")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== Customer Subscriptions / MAs ===\n";
print_r($mas);

$invoices = $pdo->query("SELECT invoice_number, invoice_date, customer_name, item_description, total_amount FROM sales WHERE item_description LIKE '%$q%'")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== Sales Lines with Serial ===\n";
print_r($invoices);
