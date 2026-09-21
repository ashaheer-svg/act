<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$cms = $db->fetchAll("SELECT invoice_number, invoice_date, customer_name, total_amount FROM sales WHERE invoice_date LIKE '2026-09%' AND invoice_type = 'Credit Memo'");
echo "Credit memos in Sep 2026 in DB: " . count($cms) . "\n";

$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);
$rawCms = 0;
foreach ($json['credit_memos'] ?? [] as $cm) {
    if (strpos($cm['Date'] ?? '', '2026-09') === 0) {
        $rawCms++;
    }
}
echo "Credit memos in Sep 2026 in raw export: $rawCms\n";
