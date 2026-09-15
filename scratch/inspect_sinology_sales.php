<?php
require_once 'config.php';
$db = new PDO('sqlite:' . DATABASE_PATH);

$stmt = $db->query("SELECT invoice_number, invoice_date, customer_name, item_description FROM sales WHERE item_description LIKE '%Sinology%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total matching rows in 'sales': " . count($rows) . "\n\n";
foreach ($rows as $r) {
    echo "Invoice: " . $r['invoice_number'] . " | Date: " . $r['invoice_date'] . " | Customer: " . $r['customer_name'] . "\n";
    echo "Description: " . str_replace("\n", " ", substr($r['item_description'], 0, 100)) . "\n";
    echo "--------------------------------------------------------\n";
}

$stmt2 = $db->query("SELECT COUNT(*) FROM sales WHERE item_description LIKE '%Synology%'");
$synologyCount = $stmt2->fetchColumn();
echo "\nTotal rows with correct spelling 'Synology' in sales: $synologyCount\n";
