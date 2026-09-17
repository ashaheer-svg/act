<?php
$jsonFile = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
if (!file_exists($jsonFile)) {
    $jsonFile = 'Exports/qb_export_2026-09-09_160211.json';
}
if (!file_exists($jsonFile)) {
    $matches = glob('app/exports/*.json');
    $jsonFile = $matches[0] ?? '';
}

if ($jsonFile && file_exists($jsonFile)) {
    $raw = file_get_contents($jsonFile);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true);
    $invoices = $data['invoices'] ?? [];
    echo "Found " . count($invoices) . " invoices in $jsonFile\n";

    foreach ([10, 25, 50, 100, 250, 500] as $cnt) {
        $sample = array_slice($invoices, 0, $cnt);
        $payload = [
            'source' => 'test',
            'timestamp' => date('c'),
            'invoices' => $sample,
            'customers' => [],
            'credit_memos' => [],
            'payments' => []
        ];
        $encoded = json_encode($payload);
        echo "Invoices: $cnt -> Size: " . round(strlen($encoded) / 1024, 1) . " KB\n";
    }
} else {
    echo "No export JSON found to test.\n";
}
