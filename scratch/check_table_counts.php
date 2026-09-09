<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

foreach (['sales', 'invoice_items', 'hardware_assets', 'software_subscriptions'] as $t) {
    $c = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "$t: $c rows\n";
}
