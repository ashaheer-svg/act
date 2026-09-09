<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');
$stmt = $db->query("PRAGMA table_info(invoice_items)");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['name'] . "\n";
}
