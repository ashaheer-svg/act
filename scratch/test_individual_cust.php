<?php
require_once __DIR__ . '/test_real_cust_batch1.php';

echo "=== TESTING CUSTOMERS 1 TO 25 INDIVIDUALLY ===\n";

for ($i = 0; $i < count($batch1); $i++) {
    $single = [$batch1[$i]];
    $payload = [
        'source' => 'test_real',
        'timestamp' => date('c'),
        'customers' => $single,
        'invoices' => [],
        'credit_memos' => [],
        'payments' => []
    ];
    $json = json_encode($payload);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    $name = $batch1[$i]['Name'] ?? $batch1[$i]['name'] ?? 'unknown';
    echo "[$i] $name (" . strlen($json) . " B) -> HTTP $code\n";
    if ($code === 400) {
        echo "--> TRIGGERED ON CUSTOMER $i: $name!\n";
        print_r($batch1[$i]);
    }
}
