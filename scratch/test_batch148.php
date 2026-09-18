<?php
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($file);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$data = json_decode($raw, true);

$invoices = $data['invoices'] ?? [];
echo "Total invoices in export: " . count($invoices) . "\n";

// Batch 148 is index 3675 to 3699 (25 invoices)
$batch148 = array_slice($invoices, 3675, 25);
echo "Batch 148 count: " . count($batch148) . "\n";

$payload = [
    'source' => 'test_batch148',
    'timestamp' => date('c'),
    'customers' => [],
    'invoices' => $batch148,
    'credit_memos' => [],
    'payments' => []
];

$rawJson = json_encode($payload);
$compressed = base64_encode(gzdeflate($rawJson));
$wrapper = json_encode(['compressed_payload' => $compressed]);

echo "Raw size: " . round(strlen($rawJson) / 1024, 1) . " KB\n";
echo "Compressed size: " . round(strlen($wrapper) / 1024, 1) . " KB\n";

$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $wrapper);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $apiKey
]);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $code\n";
echo "Response:\n" . substr(strip_tags($resp), 0, 300) . "\n";
