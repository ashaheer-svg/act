<?php
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($file);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$data = json_decode($raw, true);

$customers = $data['customers'] ?? [];
echo "Total customers: " . count($customers) . "\n";

$batch1 = array_slice($customers, 0, 25);
$json1 = json_encode([
    'source' => 'test_real',
    'timestamp' => date('c'),
    'customers' => $batch1,
    'invoices' => [],
    'credit_memos' => [],
    'payments' => []
]);

echo "Batch 1 JSON size: " . round(strlen($json1) / 1024, 1) . " KB\n";

$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n$response\n";
