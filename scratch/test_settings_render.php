<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

session_name(SESSION_NAME);
session_start();
$db = new Database(DATABASE_PATH);
$admin = $db->fetch("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
$_SESSION['user_id'] = $admin['id'];
$_SESSION['username'] = $admin['username'];
$_SESSION['role'] = $admin['role'];
$_SESSION['last_activity'] = time();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/settings.php';
$_SERVER['HTTP_HOST'] = 'localhost';

ob_start();
include __DIR__ . '/../settings.php';
$out = ob_get_clean();

echo "Render Length: " . strlen($out) . " bytes\n";
echo "Has id='team': " . (strpos($out, 'id="team"') !== false ? 'YES' : 'NO') . "\n";
echo "Has 'System Users': " . (strpos($out, 'System Users') !== false ? 'YES' : 'NO') . "\n";
echo "Has 'Granular Report Permissions Matrix': " . (strpos($out, 'Granular Report Permissions Matrix') !== false ? 'YES' : 'NO') . "\n";
