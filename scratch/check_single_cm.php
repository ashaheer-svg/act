<?php
ini_set('memory_limit', '1024M');
$file = __DIR__ . '/../app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file));
$data = json_decode($content, true);

foreach ($data['credit_memos'] as $cm) {
    if (trim($cm['Num'] ?? '') === 'AS/CRM/0001') {
        echo "Line: " . ($cm['Item'] ?? '') . " | Desc: " . substr($cm['Description'] ?? '', 0, 30) . " | Amount: " . ($cm['Amount'] ?? '') . "\n";
        echo "  applied_to_invoice: " . ($cm['applied_to_invoice'] ?? 'N/A') . " | applied_amount: " . ($cm['applied_amount'] ?? 'N/A') . "\n";
        if (!empty($cm['linked_txns'])) {
            echo "  linked_txns: " . json_encode($cm['linked_txns']) . "\n";
        }
    }
}
