<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

// Check schema of sales, invoice_items, hardware_assets, software_subscriptions
echo "=== TABLES SCHEMA ===\n";
foreach (['sales', 'invoice_items', 'hardware_assets', 'software_subscriptions'] as $tbl) {
    echo "\nTable: $tbl\n";
    $cols = $db->query("PRAGMA table_info($tbl)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['name']} ({$c['type']})\n";
    }
}
