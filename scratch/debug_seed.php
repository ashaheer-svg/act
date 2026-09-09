<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
try {
    echo "Current count: " . $db->fetch("SELECT count(*) as c FROM tax_rules")['c'] . "\n";
    $db->seedDefaultTaxRules();
    echo "After count: " . $db->fetch("SELECT count(*) as c FROM tax_rules")['c'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
