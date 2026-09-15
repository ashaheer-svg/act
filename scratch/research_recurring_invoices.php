<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Table List ===\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n=== Inspect Recurring Keywords in sales ===\n";
$keywords = [
    '%maintenance%',
    '%acronis%',
    '%hosting%',
    '%annual%',
    '%subscription%',
    '%license%',
    '%sla%',
    '%contract%',
    '%cloud backup%',
    '%antivirus%',
    '%domain%'
];
foreach ($keywords as $kw) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE item_description LIKE ? OR product_category LIKE ?");
    $stmt->execute([$kw, $kw]);
    $cnt = $stmt->fetchColumn();
    echo sprintf("%-20s: %d rows\n", $kw, $cnt);
}

echo "\n=== Check existing Contracts logic in Reports.php ===\n";
$contractRows = $pdo->query("
    SELECT item_description, product_category, COUNT(*) as cnt
    FROM sales 
    WHERE item_description LIKE '%maintenance%' 
       OR item_description LIKE '%acronis%' 
       OR item_description LIKE '%hosting%'
       OR item_description LIKE '%sla%'
    GROUP BY item_description
    ORDER BY cnt DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($contractRows as $r) {
    echo sprintf("%-60s | %-20s | %d\n", substr($r['item_description'], 0, 60), $r['product_category'], $r['cnt']);
}
