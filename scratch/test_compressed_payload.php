<?php
$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

// Test string with degrees(
$testData = [
    'customers' => [
        [
            'name' => '361 Degrees (Pvt) Ltd',
            'bill_address' => '361 Degrees (Pvt) Ltd, 185/1 Sri Gnanendra Mawatha'
        ]
    ]
];

$rawJson = json_encode($testData);
$compressed = base64_encode(gzdeflate($rawJson));

echo "Raw size: " . strlen($rawJson) . " B\n";
echo "Base64 gzdeflate size: " . strlen($compressed) . " B\n";

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

echo "HTTP Code: $code\n";
echo "Response: " . substr(strip_tags($resp), 0, 200) . "\n";
