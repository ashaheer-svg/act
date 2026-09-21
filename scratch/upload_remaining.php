<?php
ini_set('memory_limit', '1024M');
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);

$invoices = $data['invoices'];
$creditMemos = $data['credit_memos'] ?? [];
$payments = $data['payments'] ?? [];
$customers = $data['customers'] ?? [];

$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = $config['server_url'];
$key = $config['api_key'];

echo "=== Uploading Remaining QB Data to act.active.lk ===\n";
echo "Total in file: " . count($invoices) . " Invoices, " . count($creditMemos) . " Credit Memos, " . count($payments) . " Payments\n";

function postPayload($url, $key, $payload) {
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
            $wait = $attempt * 4;
            echo "   [!] HTTP $code encountered. Pausing {$wait}s for rate limiter to reset (attempt $attempt/5)...\n";
            sleep($wait);
            continue;
        }

        return [false, "HTTP $code: " . substr($res, 0, 200), $dt];
    }
    return [false, "Exceeded retries", 0];
}

// 1. Invoices are 100% completed!
$invBatchSize = 75;
$totalBatches = (int)ceil(count($invoices) / $invBatchSize);
$startBatch = $totalBatches; // All 17,780 invoices already imported!

echo "\n--- Uploading Remaining Invoices (Batches " . ($startBatch + 1) . " to $totalBatches) ---\n";
for ($b = $startBatch; $b < $totalBatches; $b++) {
    $batch = array_slice($invoices, $b * $invBatchSize, $invBatchSize);
    $from = $b * $invBatchSize + 1;
    $to = min(($b + 1) * $invBatchSize, count($invoices));

    $payload = [
        'source' => 'qb_desktop_sync',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => $batch,
        'credit_memos' => [],
        'payments' => []
    ];

    list($ok, $res, $dt) = postPayload($url, $key, $payload);
    if (!$ok) {
        echo "   [X] Invoices $from-$to failed: $res\n";
        exit(1);
    }
    $imp = $res['imported_invoices'] ?? 0;
    $skip = $res['skipped_invoices'] ?? 0;
    echo "   [OK] Invoices $from-$to (" . count($batch) . " lines) [New: $imp, Skipped: $skip] ({$dt}s)\n";
    usleep(400000); // 400ms spacing to prevent mod_evasive trigger
}

// 2. Credit Memos
$cmBatchSize = 75;
$totalCmBatches = (int)ceil(count($creditMemos) / $cmBatchSize);
echo "\n--- Uploading Credit Memos ($totalCmBatches batches) ---\n";
for ($b = 0; $b < $totalCmBatches; $b++) {
    $batch = array_slice($creditMemos, $b * $cmBatchSize, $cmBatchSize);
    $from = $b * $cmBatchSize + 1;
    $to = min(($b + 1) * $cmBatchSize, count($creditMemos));

    $payload = [
        'source' => 'qb_desktop_sync',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => [],
        'credit_memos' => $batch,
        'payments' => []
    ];

    list($ok, $res, $dt) = postPayload($url, $key, $payload);
    if (!$ok) {
        echo "   [X] Credit Memos $from-$to failed: $res\n";
        exit(1);
    }
    $imp = $res['imported_credit_memos'] ?? 0;
    echo "   [OK] Credit Memos $from-$to (" . count($batch) . " lines) [New: $imp] ({$dt}s)\n";
    usleep(400000);
}

// 3. Payments
$payBatchSize = 100;
$totalPayBatches = (int)ceil(count($payments) / $payBatchSize);
echo "\n--- Uploading Payments ($totalPayBatches batches) ---\n";
for ($b = 0; $b < $totalPayBatches; $b++) {
    $batch = array_slice($payments, $b * $payBatchSize, $payBatchSize);
    $from = $b * $payBatchSize + 1;
    $to = min(($b + 1) * $payBatchSize, count($payments));

    $payload = [
        'source' => 'qb_desktop_sync',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => [],
        'credit_memos' => [],
        'payments' => $batch
    ];

    list($ok, $res, $dt) = postPayload($url, $key, $payload);
    if (!$ok) {
        echo "   [X] Payments $from-$to failed: $res\n";
        exit(1);
    }
    $imp = $res['imported_payments'] ?? 0;
    echo "   [OK] Payments $from-$to (" . count($batch) . " records) [New: $imp] ({$dt}s)\n";
    usleep(400000);
}

echo "\n=== ALL REMAINING DATA SUCCESSFULLY UPLOADED! ===\n";
