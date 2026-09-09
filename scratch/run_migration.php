<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$db->syncSchema();

echo "=== Tax Rules ===\n";
$rules = $db->getTaxRules();
foreach ($rules as $r) {
    echo "• [ID: {$r['id']}] {$r['tax_name']}: " . ($r['tax_rate'] * 100) . "% (Effective from: {$r['effective_from']})\n";
}

echo "\n=== New Tables Verification ===\n";
$tables = ['invoice_items', 'hardware_assets', 'software_subscriptions', 'ai_extraction_logs'];
foreach ($tables as $t) {
    $c = $db->fetch("SELECT count(*) as c FROM $t");
    echo "• Table $t exists, record count: " . $c['c'] . "\n";
}
