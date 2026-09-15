<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$res = $pdo->query("SELECT customer_name, company_name, contact_name, total_balance FROM customer_profiles WHERE customer_name LIKE '%EITS%' OR customer_name LIKE '%E-Net%'")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
