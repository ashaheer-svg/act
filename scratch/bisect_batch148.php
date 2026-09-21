<?php
require_once __DIR__ . '/isolate_batch148_item.php';

function testSlice($start, $len, $label) {
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
    unset($ch);

    echo "$label (len: $len): HTTP $code\n";
    if ($code !== 200) {
        echo "--> FAILED: " . substr(strip_tags($resp), 0, 100) . "\n";
    }
}

testSlice(0, 12, "Items 0..11");
testSlice(12, 13, "Items 12..24");
testSlice(0, 24, "Items 0..23");
testSlice(1, 24, "Items 1..24");
testSlice(0, 25, "Full Batch 148 (0..24)");
