<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== software_subscriptions schema ===\n";
$cols = $pdo->query("PRAGMA table_info(software_subscriptions)")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

$subCount = $pdo->query("SELECT COUNT(*) FROM software_subscriptions")->fetchColumn();
echo "software_subscriptions row count: $subCount\n";

if ($subCount > 0) {
    echo "Sample software_subscriptions rows:\n";
    $samples = $pdo->query("SELECT * FROM software_subscriptions LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($samples);
}
