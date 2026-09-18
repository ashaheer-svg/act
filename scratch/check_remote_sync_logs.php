<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = new Database(DATABASE_PATH);
$logs = $db->fetchAll("SELECT * FROM activity_log WHERE action = 'QB_API_SYNC' ORDER BY id DESC LIMIT 10");
header('Content-Type: application/json');
echo json_encode($logs, JSON_PRETTY_PRINT);
