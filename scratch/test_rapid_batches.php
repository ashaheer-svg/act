<?php
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($file);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$data = json_decode($raw, true);
$invoices = $data['invoices'] ?? [];

$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

echo "Testing batches 145 to 155...\n";
for ($b = 145; $b <= 155; $b++) {
    $batch = array_slice($invoices, ($b - 1) * 25, 25);
    $payload = [
        'source' => 'test_rapid',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => $batch,
        'credit_memos' => [],
        'payments' => []
    ];
    $compressed = base64_encode(gzdeflate(json_encode($payload)));
    $wrapper = json_encode(['compressed_payload' => $compressed]);

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

    echo "Batch $b (" . count($batch) . " lines): HTTP $code\n";
    if ($code !== 200) {
        echo "Response: " . substr(strip_tags($resp), 0, 150) . "\n";
    }
}
