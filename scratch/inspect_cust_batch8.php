<?php
ini_set('memory_limit', '1024M');
$content = file_get_contents('app/exports/16-9/qb_export_2026-09-16_143318.json');
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);
if (!$data) {
    echo "JSON error: " . json_last_error_msg() . PHP_EOL;
    exit(1);
}
$custs = $data['customers'];
echo "Total customers: " . count($custs) . PHP_EOL;
$batch8 = array_slice($custs, 7 * 25, 25);
echo "Batch 8 count: " . count($batch8) . PHP_EOL;

$payload = [
    'source' => 'qb_desktop_sync',
    'timestamp' => date('c'),
    'customers' => $batch8,
    'invoices' => [],
    'credit_memos' => [],
    'payments' => []
];

$json = json_encode($payload);
$compressed = gzdeflate($json, 9);
$b64 = base64_encode($compressed);
$wrapped = json_encode(['compressed_payload' => $b64]);

echo "Raw JSON length: " . strlen($json) . PHP_EOL;
echo "Compressed B64 length: " . strlen($b64) . PHP_EOL;

// Let's test posting this directly to act.active.lk/api/sync.php
$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = $config['server_url'];
$key = $config['api_key'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $key
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $code" . PHP_EOL;
echo "Curl Error: $curlErr" . PHP_EOL;
echo "Response: " . substr($response, 0, 500) . PHP_EOL;
