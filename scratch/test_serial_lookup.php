<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$q = '2110RXRC';
echo "=== Searching for partial serial: '$q' ===\n";

// 1. Search in hardware_assets
$assets = $pdo->prepare("
    SELECT h.*, s.invoice_date, s.total_amount as invoice_amount, s.sales_rep_code
    FROM hardware_assets h
    LEFT JOIN sales s ON h.invoice_number = s.invoice_number
    WHERE h.serial_number LIKE ?
    GROUP BY h.id
");
$assets->execute(["%$q%"]);
$resAssets = $assets->fetchAll(PDO::FETCH_ASSOC);
echo "hardware_assets matches: " . count($resAssets) . "\n";
print_r($resAssets);

// 2. Search in sales table item_description
$salesMatches = $pdo->prepare("
    SELECT s.invoice_number, s.invoice_date, s.customer_name, s.item_description, s.total_amount, s.paid_date, s.sales_rep_code
    FROM sales s
    WHERE s.item_description LIKE ?
");
$salesMatches->execute(["%$q%"]);
$resSales = $salesMatches->fetchAll(PDO::FETCH_ASSOC);
echo "\nsales matches: " . count($resSales) . "\n";
print_r($resSales);

// 3. Search for maintenance contracts for the customer
if (!empty($resAssets)) {
    $cust = $resAssets[0]['customer_name'];
    echo "\n=== Checking Maintenance Agreements for customer: '$cust' ===\n";
    $mas = $pdo->prepare("
        SELECT sub.*, s.total_amount as invoice_gross
        FROM software_subscriptions sub
        LEFT JOIN sales s ON sub.invoice_number = s.invoice_number
        WHERE sub.customer_name = ? AND (sub.software_name LIKE '%Maintenance%' OR sub.edition_tier LIKE '%Maintenance%')
        GROUP BY sub.id
    ");
    $mas->execute([$cust]);
    $resMas = $mas->fetchAll(PDO::FETCH_ASSOC);
    print_r($resMas);
}
