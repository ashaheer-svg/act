<?php
$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

function testRawBody($body, $desc) {
    global $url, $apiKey;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    echo "$desc | Size: " . strlen($body) . " B | HTTP: $httpCode | Body: " . substr(strip_tags($response), 0, 80) . "\n";
}

// Test dummy payload with different string lengths
testRawBody(json_encode(['invoices' => [], 'payments' => [], 'customers' => []]), "Empty arrays");

// Test with 1KB dummy string
testRawBody(json_encode(['dummy' => str_repeat('A', 1000), 'invoices' => [], 'payments' => []]), "1 KB dummy");

// Test with 10KB dummy string
testRawBody(json_encode(['dummy' => str_repeat('A', 10000), 'invoices' => [], 'payments' => []]), "10 KB dummy");

// Test with 30KB dummy string
testRawBody(json_encode(['dummy' => str_repeat('A', 30000), 'invoices' => [], 'payments' => []]), "30 KB dummy");

// Test with 60KB dummy string
testRawBody(json_encode(['dummy' => str_repeat('A', 60000), 'invoices' => [], 'payments' => []]), "60 KB dummy");

// Test with 120KB dummy string
testRawBody(json_encode(['dummy' => str_repeat('A', 120000), 'invoices' => [], 'payments' => []]), "120 KB dummy");
