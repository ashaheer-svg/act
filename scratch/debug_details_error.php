<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$res = $reports->getInvoiceDetails('ASN000104');
$json = json_encode($res);
if ($json === false) {
    echo "json_encode FAILED: " . json_last_error_msg() . "\n";
} else {
    echo "json_encode SUCCESS. Length: " . strlen($json) . "\n";
    echo substr($json, 0, 300) . "...\n";
}
