<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);

// Set session keys
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['last_activity'] = time();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['inv'] = 'ASN000104';

ob_start();
include __DIR__ . '/../invoice_edit.php';
$out = ob_get_clean();

echo "Rendered length: " . strlen($out) . "\n";
echo "Contains Edit Commercial Invoice: " . (strpos($out, 'Edit Commercial Invoice') !== false ? 'YES' : 'NO') . "\n";
echo "Contains ASN000104: " . (strpos($out, 'ASN000104') !== false ? 'YES' : 'NO') . "\n";
echo "Contains item-unit-cost: " . (strpos($out, 'item-unit-cost') !== false ? 'YES' : 'NO') . "\n";
echo "Contains End Customer: " . (strpos($out, 'End Customer (End-Client Entity)') !== false ? 'YES' : 'NO') . "\n";
