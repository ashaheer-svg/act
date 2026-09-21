<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

$invs = $db->fetchAll("SELECT DISTINCT invoice_number, invoice_date FROM sales WHERE invoice_date LIKE '2026-09%' ORDER BY invoice_number");
echo "Invoices currently in DB for Sep 2026 (" . count($invs) . "):\n";
foreach ($invs as $i) {
    echo "  - {$i['invoice_number']} ({{$i['invoice_date']}})\n";
}
