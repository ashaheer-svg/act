<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);
$db->syncCustomerProfiles();
echo "Count: " . $db->countCustomers() . "\n";
?>
