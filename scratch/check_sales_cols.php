<?php
require_once 'config.php';
$db = new PDO('sqlite:' . DATABASE_PATH);

$stmt = $db->query("SELECT invoice_number, invoice_date, customer_name, item_description, product_category, imported_at, qb_txn_id FROM sales WHERE item_description LIKE '%Sinology%' LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo json_encode($r, JSON_PRETTY_PRINT) . "\n";
}
