<?php
ini_set('memory_limit', '1024M');
// 1. Get list of invoice numbers from the export file
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);

$exportInvNums = [];
foreach ($data['invoices'] as $inv) {
    $num = trim($inv['Num'] ?? '');
    if ($num) $exportInvNums[$num] = true;
}
echo "Export file has " . count($exportInvNums) . " unique invoice numbers.\n";

// Now check what's in local database (which was cloned/created earlier)
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database('data/sales_bi.db');

$dbInvNums = $db->fetchAll("SELECT DISTINCT invoice_number, invoice_date, customer_name FROM sales");
echo "Database has " . count($dbInvNums) . " unique invoice numbers.\n";

$notInExport = [];
foreach ($dbInvNums as $row) {
    $num = $row['invoice_number'];
    if (!isset($exportInvNums[$num])) {
        $notInExport[] = $row;
    }
}

echo "Found " . count($notInExport) . " invoices in database that are NOT in the export file!\n";
echo "First 20 invoices not in export file:\n";
for ($i = 0; $i < min(20, count($notInExport)); $i++) {
    echo "  " . $notInExport[$i]['invoice_number'] . " | " . $notInExport[$i]['invoice_date'] . " | " . $notInExport[$i]['customer_name'] . "\n";
}
