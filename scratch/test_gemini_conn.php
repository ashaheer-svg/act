<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/AiExtractor.php';

$db = new Database(DATABASE_PATH);
$extractor = new AiExtractor($db);
$res = $extractor->testConnection();
echo "Connection result: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";
