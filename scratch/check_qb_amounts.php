<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);

$res = $db->fetch("SELECT COUNT(*) as c FROM sales WHERE qb_amount != 0 AND ABS(total_amount - qb_amount) > 0.05");
echo "Inflated rows with qb_amount != 0: " . $res['c'] . "\n";

$resZero = $db->fetch("SELECT COUNT(*) as c FROM sales WHERE (qb_amount == 0 OR qb_amount IS NULL) AND ABS(total_amount) > 0");
echo "Rows with qb_amount == 0 but total_amount > 0: " . $resZero['c'] . "\n";
