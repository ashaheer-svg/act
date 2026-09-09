<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$stmt = $db->query("SELECT * FROM sales WHERE invoice_number = 'AS010867'");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
