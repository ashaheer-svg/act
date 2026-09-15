<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$matches = $pdo->query("
    SELECT h.customer_name, h.serial_number, h.product_name, h.invoice_number as hw_inv, sub.software_name, sub.invoice_number as ma_inv
    FROM hardware_assets h
    JOIN software_subscriptions sub ON h.customer_name = sub.customer_name
    WHERE h.serial_number != '' AND h.serial_number != 'UNASSIGNED'
      AND (sub.software_name LIKE '%Maintenance%' OR sub.edition_tier LIKE '%Maintenance%')
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

print_r($matches);
