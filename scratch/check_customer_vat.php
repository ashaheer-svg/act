<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

echo "=== customer_profiles Schema ===\n";
$cols = $db->fetchAll("PRAGMA table_info(customer_profiles)");
foreach ($cols as $c) {
    echo "  Column: {$c['name']} ({$c['type']})\n";
}

echo "\n=== Check customer records for ARC ONE and Network Information Technologies ===\n";
$custs = $db->fetchAll("SELECT * FROM customer_profiles WHERE customer_name LIKE '%ARC ONE%' OR customer_name LIKE '%Network Information Technologies%' OR customer_name LIKE '%Darshana Traders%' OR customer_name LIKE '%Cyrus%' LIMIT 10");
foreach ($custs as $cust) {
    echo "\nCustomer: {$cust['customer_name']}\n";
    foreach ($cust as $k => $v) {
        if ($v !== null && $v !== '') {
            echo "  $k: $v\n";
        }
    }
}
