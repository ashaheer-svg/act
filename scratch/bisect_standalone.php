<?php
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($file);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$data = json_decode($raw, true);
$invoices = $data['invoices'] ?? [];

$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

$batch148 = array_slice($invoices, (148 - 1) * 25, 25);

function testSliceDirect($start, $len, $label) {
    global $batch148, $url, $apiKey;
    $slice = array_slice($batch148, $start, $len);
    $payload = [
        'source' => 'test_slice',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => $slice,
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

    echo "$label (len: $len): HTTP $code\n";
    if ($code !== 200) {
        echo "--> FAILED: " . substr(strip_tags($resp), 0, 100) . "\n";
        echo "Base64 payload: " . substr($compressed, 0, 100) . "...\n";
    }
}

testSliceDirect(0, 12, "Items 0..11");
testSliceDirect(12, 13, "Items 12..24");
testSliceDirect(0, 24, "Items 0..23");
testSliceDirect(1, 24, "Items 1..24");
testSliceDirect(0, 25, "Full Batch 148 (0..24)");
