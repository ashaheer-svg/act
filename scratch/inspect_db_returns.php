<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/sales_bi.db');
echo "=== INVOICE TYPES IN SALES ===\n";
$stmt = $pdo->query("SELECT invoice_type, count(*) as count FROM sales GROUP BY invoice_type");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['invoice_type']}: {$row['count']}\n";
}

echo "\n=== PAYMENT METHODS / TYPES ===\n";
$stmt = $pdo->query("SELECT payment_method, count(*) as count FROM payments GROUP BY payment_method");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['payment_method']}: {$row['count']}\n";
}

echo "\n=== PAYMENTS WITH MEMO OR REF REFERENCING CREDIT / RETURN ===\n";
$stmt = $pdo->query("SELECT * FROM payments WHERE memo LIKE '%credit%' OR memo LIKE '%return%' OR reference_num LIKE '%CR%' OR reference_num LIKE '%CN%' OR reference_num LIKE '%CM%' LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
