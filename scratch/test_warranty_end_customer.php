<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$reports = new Reports($db);

$res = $reports->lookupWarrantySerial('Lanka Hospital');
echo "Warranty lookup for 'Lanka Hospital': " . count($res) . " results found.\n";
foreach ($res as $r) {
    echo "S/N: {$r['serial_number']} | Product: {$r['product_name']}\n";
    echo "  Partner: {$r['current_customer']} | End Customer: {$r['end_customer']}\n";
    echo "  Warranty Expiry: {$r['warranty_expiry_date']} ({$r['status_label']})\n\n";
}

$res2 = $reports->lookupWarrantySerial('Singer');
echo "Warranty lookup for 'Singer': " . count($res2) . " results found.\n";
foreach (array_slice($res2, 0, 3) as $r) {
    echo "S/N: {$r['serial_number']} | Product: {$r['product_name']}\n";
    echo "  Partner: {$r['current_customer']} | End Customer: {$r['end_customer']}\n";
}
