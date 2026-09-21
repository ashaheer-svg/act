<?php
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($file);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$data = json_decode($raw, true);

$customers = $data['customers'] ?? [];
$batch1 = array_slice($customers, 0, 25);

$payload = [
    'source' => 'test_compressed_batch1',
    'timestamp' => date('c'),
    'customers' => $batch1,
    'invoices' => [],
    'credit_memos' => [],
    'payments' => []
];

$rawJson = json_encode($payload);
$rawSize = strlen($rawJson);

$compressed = base64_encode(gzdeflate($rawJson));
$wrapper = json_encode(['compressed_payload' => $compressed]);
$compSize = strlen($wrapper);

echo "Raw JSON size: " . round($rawSize / 1024, 1) . " KB\n";
echo "Compressed Base64 size: " . round($compSize / 1024, 1) . " KB (Saved " . round((1 - $compSize / $rawSize) * 100) . "%)\n";

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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
