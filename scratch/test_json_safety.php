<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$data = $reports->getInvoiceDetails('ASN000104');
$json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
echo "JSON length: " . strlen($json) . "\n";
echo "JSON error: " . json_last_error_msg() . "\n";
