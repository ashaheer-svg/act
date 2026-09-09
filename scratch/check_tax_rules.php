<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

$rules = $db->fetchAll("SELECT * FROM tax_rules");
echo "=== tax_rules table ===\n";
foreach ($rules as $r) {
    echo "ID: {$r['id']} | Name: {$r['tax_name']} | Rate: {$r['tax_rate']} | From: {$r['effective_from']} | To: {$r['effective_to']} | Range: {$r['invoice_range_start']} - {$r['invoice_range_end']} | InclDefault: {$r['is_inclusive_default']} | Notes: {$r['notes']}\n";
}
