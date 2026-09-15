<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);

$_GET['ajax_warranty_lookup'] = '20C0SKRCXAQ44';
$_GET['status'] = 'all';

$res = $reports->lookupWarrantySerial($_GET['ajax_warranty_lookup'], $_GET['status']);

echo "JSON Payload preview:\n";
$json = json_encode(['results' => $res], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
echo substr($json, 0, 1000) . "...\n";
echo "Total results: " . count($res) . "\n";
