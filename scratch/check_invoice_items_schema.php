<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== invoice_items columns ===\n";
$cols = $pdo->query("PRAGMA table_info(invoice_items)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['name']} ({$c['type']})\n";
}

echo "\n=== invoice_items vat_treatment count ===\n";
$treat = $pdo->query("SELECT vat_treatment, count(*), sum(total_amount), sum(base_value), sum(vat_component) FROM invoice_items GROUP BY vat_treatment")->fetchAll(PDO::FETCH_ASSOC);
print_r($treat);
