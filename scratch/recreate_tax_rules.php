<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "Re-creating tax_rules table with nullable effective_from...\n";
$pdo->exec("DROP TABLE IF EXISTS tax_rules");
$pdo->exec("
    CREATE TABLE tax_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tax_name TEXT NOT NULL,
        tax_rate REAL NOT NULL,
        effective_from DATE,
        effective_to DATE,
        invoice_range_start TEXT,
        invoice_range_end TEXT,
        is_inclusive_default INTEGER DEFAULT 1,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$db->seedDefaultTaxRules();
echo "Rules count now: " . $db->fetch("SELECT count(*) as c FROM tax_rules")['c'] . "\n";
