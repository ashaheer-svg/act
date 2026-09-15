<?php
ini_set('display_errors', '0');
error_reporting(0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Let's find all reports that use `paid_date`
echo "=== AUDIT OF ALL PLACES USING paid_date IN CODEBASE ===\n";
// We'll search php files for paid_date
?>
