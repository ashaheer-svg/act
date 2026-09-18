<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database('data/sales_bi.db');

echo "=== Apex customers ===\n";
$apex = $db->fetchAll("SELECT DISTINCT customer_name FROM sales WHERE customer_name LIKE '%Apex%'");
print_r($apex);

echo "=== Test customers ===\n";
$test = $db->fetchAll("SELECT DISTINCT customer_name FROM sales WHERE customer_name LIKE '%Test%'");
print_r($test);


echo "=== Check for any test or mock names in all tables ===\n";
$tests = ['Apex', 'Matrix', 'CloudScale', 'Mock', 'Test', 'Sample', 'Acme', 'Demo'];
foreach ($tests as $t) {
    $sCount = $db->fetch("SELECT count(*) as c FROM sales WHERE customer_name LIKE '%$t%' OR invoice_number LIKE '%$t%'")['c'] ?? 0;
    $hwCount = $db->fetch("SELECT count(*) as c FROM hardware_assets WHERE customer_name LIKE '%$t%' OR serial_number LIKE '%$t%'")['c'] ?? 0;
    $swCount = $db->fetch("SELECT count(*) as c FROM software_subscriptions WHERE customer_name LIKE '%$t%'")['c'] ?? 0;
    $iiCount = $db->fetch("SELECT count(*) as c FROM invoice_items WHERE customer_name LIKE '%$t%'")['c'] ?? 0;
    echo "Pattern '$t': sales=$sCount, hw=$hwCount, sw=$swCount, items=$iiCount\n";
}
