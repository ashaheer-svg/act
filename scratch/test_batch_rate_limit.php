<?php
ini_set('memory_limit', '1024M');
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);
$custs = $data['customers'];

$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = $config['server_url'];
$key = $config['api_key'];

echo "Testing 10 consecutive customer batches with 60ms delay:\n";

for ($b = 0; $b < 10; $b++) {
    $batch = array_slice($custs, $b * 25, 25);
    $payload = [
        'source' => 'qb_desktop_sync',
        'timestamp' => date('c'),
        'customers' => $batch,
        'invoices' => [],
        'credit_memos' => [],
        'payments' => []
    ];
    $json = json_encode($payload);
    $b64 = base64_encode(gzdeflate($json, 9));
    $wrapped = json_encode(['compressed_payload' => $b64]);

    $t0 = microtime(true);
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
    $dt = round(microtime(true) - $t0, 2);
    unset($ch);

    echo "Batch " . ($b + 1) . ": HTTP $code ($dt s)\n";
    if ($code !== 200) {
        echo "Response headers/body:\n" . substr($res, 0, 400) . "\n";
        break;
    }
    usleep(60000); // 60ms
}
