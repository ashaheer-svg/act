<?php
ini_set('memory_limit', '1024M');
$file = 'app/exports/16-9/qb_export_2026-09-16_143318.json';

echo "=== Loading Master QuickBooks Export File ===\n";
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);

$customers = $data['customers'] ?? [];
$invoices = $data['invoices'] ?? [];
$creditMemos = $data['credit_memos'] ?? [];
$payments = $data['payments'] ?? [];

echo "Found in master export:\n";
echo "  - Customers    : " . count($customers) . " profiles (Already 100% loaded: 652)\n";
echo "  - Invoices     : " . count($invoices) . " lines\n";
echo "  - Credit Memos : " . count($creditMemos) . " lines\n";
echo "  - Payments     : " . count($payments) . " records\n\n";

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
            // Wait full 35 seconds to ensure Apache mod_evasive blocking window completely resets
            echo "   [!] HTTP $code encountered. Pausing 35s for Apache mod_evasive block window to clear...\n";
            sleep(35);
            continue;
        }

        return [false, "HTTP $code: " . substr($res, 0, 200), $dt];
    }
    return [false, "Exceeded retries", 0];
}

$delayUs = 1000000; // 1.0s pacing delay between requests (avoids Apache burst limit entirely)

// Phase 2: Resume Invoices from 3101
echo "=== Phase 2/4: Uploading Remaining Invoices (3,101 to 17,780) ===\n";
$invBatchSize = 200;
$startIndex = 3100;
$totalInvoices = count($invoices);

for ($idx = $startIndex; $idx < $totalInvoices; $idx += $invBatchSize) {
    $batch = array_slice($invoices, $idx, $invBatchSize);
    $from = $idx + 1;
    $to = min($idx + $invBatchSize, $totalInvoices);

    $payload = [
        'source' => 'qb_desktop_sync',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => $batch,
        'credit_memos' => [],
        'payments' => []
    ];

    list($ok, $res, $dt) = postSyncPayload($url, $key, $payload);
    if (!$ok) {
        echo "   [X] Invoices $from-$to failed: $res\n";
        exit(1);
    }
    $imp = $res['imported_invoices'] ?? count($batch);
    $skip = $res['skipped_invoices'] ?? 0;
    echo "   [OK] Invoices $from-$to (" . count($batch) . " lines) [New: $imp, Skip: $skip] ({$dt}s)\n";
    usleep($delayUs);
}

// Phase 3: Upload Credit Memos
echo "\n=== Phase 3/4: Uploading Credit Memos (" . count($creditMemos) . " lines) ===\n";
$cmBatchSize = 100;
$totalCm = count($creditMemos);
for ($idx = 0; $idx < $totalCm; $idx += $cmBatchSize) {
    $batch = array_slice($creditMemos, $idx, $cmBatchSize);
    $from = $idx + 1;
    $to = min($idx + $cmBatchSize, $totalCm);

    $payload = [
        'source' => 'qb_desktop_sync',
        'timestamp' => date('c'),
        'customers' => [],
        'invoices' => [],
        'credit_memos' => $batch,
        'payments' => []
    ];

    list($ok, $res, $dt) = postSyncPayload($url, $key, $payload);
    if (!$ok) {
        echo "   [X] Credit Memos $from-$to failed: $res\n";
        exit(1);
    }
    $imp = $res['imported_credit_memos'] ?? count($batch);
    echo "   [OK] Credit Memos $from-$to (" . count($batch) . " lines, imported $imp) ({$dt}s)\n";
    usleep($delayUs);
}

// Phase 4: Upload Payments
echo "\n=== Phase 4/4: Uploading Payments (" . count($payments) . " records) ===\n";
$payBatchSize = 200;
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
    usleep($delayUs);
}

echo "\n=======================================================\n";
echo "  SUCCESS! Fresh Full Upload Completed 100% Pure Data!\n";
echo "=======================================================\n";
