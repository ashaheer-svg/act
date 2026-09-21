<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$rules = $db->fetchAll("SELECT * FROM tax_rules ORDER BY id ASC");
echo "=== TAX RULES ===\n";
print_r($rules);

echo "\n=== SETTINGS (vat/tax) ===\n";
$settings = $db->fetchAll("SELECT * FROM settings WHERE key LIKE '%vat%' OR key LIKE '%tax%'");
print_r($settings);
