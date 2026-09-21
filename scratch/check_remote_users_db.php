<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$verifyScript = "remote_check_users_db.php";
$verifyCode = <<<'PHP'
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = new Database(DATABASE_PATH);
$users = $db->fetchAll("SELECT id, username, role, last_login, created_at FROM users");

header('Content-Type: application/json');
echo json_encode(['users' => $users], JSON_PRETTY_PRINT);
PHP;

file_put_contents(__DIR__ . "/remote_check_users_temp.php", $verifyCode);
$remoteVerifyUrl = "$ftpBase/$verifyScript";
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"" . __DIR__ . "/remote_check_users_temp.php\" \"$remoteVerifyUrl\" 2>&1");

$ch = curl_init("https://act.active.lk/$verifyScript");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
unset($ch);

echo "Production Users:\n$resp\n";

exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE /act/$verifyScript\" \"$ftpBase/\" 2>&1");
@unlink(__DIR__ . "/remote_check_users_temp.php");
