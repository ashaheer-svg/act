<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

$c = $db->fetch("SELECT * FROM customer_profiles WHERE customer_name LIKE '%Network Information Technologies%'");
foreach ($c as $k => $v) {
    if (!empty($v)) {
        echo "$k: $v\n";
    }
}
