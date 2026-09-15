<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$inv = 'ASN000104';
$salesSum = $pdo->query("SELECT SUM(total_amount), count(*) FROM sales WHERE invoice_number = '$inv'")->fetch(PDO::FETCH_NUM);
echo "Direct sales sum for $inv: Count={$salesSum[1]}, Total={$salesSum[0]}\n";

$joinedSum = $pdo->query("
    SELECT SUM(s.total_amount), count(*) 
    FROM sales s 
    LEFT JOIN invoice_items ii ON s.invoice_number = ii.invoice_number 
    WHERE s.invoice_number = '$inv'
")->fetch(PDO::FETCH_NUM);
echo "Joined with ii sum for $inv: Count={$joinedSum[1]}, Total={$joinedSum[0]}\n";
