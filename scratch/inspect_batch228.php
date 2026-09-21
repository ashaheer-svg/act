<?php
ini_set('memory_limit', '1024M');
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);
$invoices = $data['invoices'];
echo "Total invoices: " . count($invoices) . "\n";

// Batch 228 with batch size 75
$invBatchSize = 75;
$batch228 = array_slice($invoices, 227 * $invBatchSize, $invBatchSize);
echo "Batch 228 count: " . count($batch228) . "\n";
echo "First item: Num=" . ($batch228[0]['Num'] ?? '') . " Name=" . ($batch228[0]['Name'] ?? '') . "\n";
echo "Last item: Num=" . (end($batch228)['Num'] ?? '') . " Name=" . (end($batch228)['Name'] ?? '') . "\n";

$payload = [
    'source' => 'qb_desktop_sync',
    'timestamp' => date('c'),
    'customers' => [],
    'invoices' => $batch228,
    'credit_memos' => [],
    'payments' => []
];

$json = json_encode($payload);
$b64 = base64_encode(gzdeflate($json, 9));
$wrapped = json_encode(['compressed_payload' => $b64]);

echo "Raw JSON length: " . strlen($json) . "\n";
echo "B64 length: " . strlen($b64) . "\n";

$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = $config['server_url'];
$key = $config['api_key'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $wrapped);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $key
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

echo "HTTP Code: $code\n";
echo "Response: \n" . substr($response, 0, 500) . "\n";
