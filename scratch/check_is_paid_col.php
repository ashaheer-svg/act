<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$res = $pdo->query("
    SELECT 
        COUNT(*) as total_rows,
        COUNT(DISTINCT invoice_number) as total_invs,
        SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) as is_paid_1,
        SUM(CASE WHEN is_paid = 0 THEN 1 ELSE 0 END) as is_paid_0,
        SUM(CASE WHEN is_paid IS NULL THEN 1 ELSE 0 END) as is_paid_null,
        SUM(CASE WHEN paid_date IS NOT NULL AND paid_date != '' THEN 1 ELSE 0 END) as has_paid_date,
        SUM(CASE WHEN paid_date IS NULL OR paid_date = '' THEN 1 ELSE 0 END) as no_paid_date
    FROM sales
")->fetch(PDO::FETCH_ASSOC);

print_r($res);
