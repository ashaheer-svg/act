<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$stmt = $db->query("SELECT invoice_number, product_name, brand, model_sku, serial_number, notes FROM hardware_assets WHERE brand = 'Statutory Levy'");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
