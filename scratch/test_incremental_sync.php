<?php
$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = $config['server_url'];
$key = $config['api_key'];

$payload = [
    'invoices' => [],
    'credit_memos' => [],
    'payments' => [],
    'customers' => []
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-KEY: ' . $key
    ]
]);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $res\n";
