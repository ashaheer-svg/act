<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/sales_bi.db');
$count = $pdo->query("SELECT count(*) FROM hardware_assets WHERE product_name LIKE '%End Customer%' OR serial_number LIKE '%End Customer%'")->fetchColumn();
echo "Bogus hardware assets: $count\n";
if ($count > 0) {
    $stmt = $pdo->query("SELECT id, invoice_number, product_name, serial_number FROM hardware_assets WHERE product_name LIKE '%End Customer%' OR serial_number LIKE '%End Customer%'");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  #{$r['id']} | Inv: {$r['invoice_number']} | Product: {$r['product_name']} | S/N: {$r['serial_number']}\n";
    }
}
