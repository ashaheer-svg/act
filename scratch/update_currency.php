<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$db->setSetting('currency_symbol', 'LKR ');

$val = $db->getSetting('currency_symbol', 'N/A');
echo "Updated currency_symbol in settings table: '$val'\n";
