<?php
ini_set('memory_limit', '1024M');
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);

$payments = $data['payments'] ?? [];
echo "Total Payments to upload: " . count($payments) . "\n";

$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = $config['server_url'];
$key = $config['api_key'];

function postSyncPayload($url, $key, $payload) {
    $json = json_encode($payload);
    $b64 = base64_encode(gzdeflate($json, 9));
    $wrapped = json_encode(['compressed_payload' => $b64]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $t0 = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $wrapped);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-KEY: ' . $key
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $dt = round(microtime(true) - $t0, 2);
        unset($ch);

        if ($code === 200) {
            $parsed = json_decode($res, true);
            return [true, $parsed, $dt];
        }

        if (($code === 403 || $code === 429 || $code >= 500) && $attempt < 5) {
            echo "   [!] HTTP $code encountered. Pausing 35s for Apache mod_evasive block window to clear...\n";
            sleep(35);
            continue;
        }

        return [false, "HTTP $code: " . substr($res, 0, 200), $dt];
    }
    return [false, "Exceeded retries", 0];
}

$payBatchSize = 150;
$totalPay = count($payments);
for ($idx = 0; $idx < $totalPay; $idx += $payBatchSize) {
    $batch = array_slice($payments, $idx, $payBatchSize);
    $from = $idx + 1;
    $to = min($idx + $payBatchSize, $totalPay);

    $payload = [
        'source' => 'qb_desktop_sync',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => [],
        'credit_memos' => [],
        'payments' => $batch
    ];

    list($ok, $res, $dt) = postSyncPayload($url, $key, $payload);
    if (!$ok) {
        echo "   [X] Payments $from-$to failed: $res\n";
        exit(1);
    }
    $imp = $res['imported_payments'] ?? count($batch);
    echo "   [OK] Payments $from-$to (" . count($batch) . " records, imported $imp) ({$dt}s)\n";
    usleep(1200000); // 1.2s delay
}

echo "\n=== ALL PAYMENTS COMPLETED! ===\n";
