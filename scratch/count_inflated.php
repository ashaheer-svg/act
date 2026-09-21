<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$c = $db->fetch("SELECT COUNT(*) as cnt FROM sales WHERE qb_amount != 0 AND ABS(total_amount - qb_amount) > 0.05");
echo "Total inflated rows where total_amount != qb_amount: " . $c['cnt'] . "\n";
