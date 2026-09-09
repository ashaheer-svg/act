<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$stmt = $db->query("SELECT product_name, brand, serial_number, warranty_status FROM hardware_assets WHERE invoice_number = 'AS010867'");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  • {$r['brand']} - {$r['product_name']} | S/N: {$r['serial_number']} [{$r['warranty_status']}]\n";
}
