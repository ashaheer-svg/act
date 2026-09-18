<?php
ini_set('memory_limit', '1024M');
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);

$payments = $data['payments'];
$batch = array_slice($payments, 0, 100);
echo "Payments batch count: " . count($batch) . "\n";

$payload = [
    'source' => 'qb_desktop_sync',
    'timestamp' => date('c'),
    'customers' => [],
    'invoices' => [],
    'credit_memos' => [],
    'payments' => $batch
];

$json = json_encode($payload);
$b64 = base64_encode(gzdeflate($json, 9));
$wrapped = json_encode(['compressed_payload' => $b64]);

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

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo substr($res, 0, 500) . "\n";
