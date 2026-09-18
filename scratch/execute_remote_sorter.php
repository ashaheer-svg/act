<?php
/**
 * Run DataSorter over entire remote production database in batches
 */

$apiUrl = 'https://act.active.lk/api/run_sorter.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';
$batchSize = 400;
$offset = 0;
$hasMore = true;
$totalProcessed = 0;
$isFirst = true;

echo "=== Starting Full Remote DataSorter Normalization ===\n\n";

while ($hasMore) {
    $url = $apiUrl . "?offset=$offset&limit=$batchSize" . ($isFirst ? "&reset=1" : "");
    $isFirst = false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["X-API-KEY: $apiKey"],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $start = microtime(true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $elapsed = round(microtime(true) - $start, 2);

    if ($httpCode === 403) {
        echo "[!] Rate limit (403) encountered. Waiting 35 seconds to cool down...\n";
        sleep(35);
        continue; // Retry this offset
    }

    if ($httpCode !== 200 || !$response) {
        $curlErr = curl_error($ch);
        echo "[ERROR] HTTP $httpCode: $curlErr. Response: $response\n";
        break;
    }

    $data = json_decode($response, true);
    if (!$data || !($data['success'] ?? false)) {
        echo "[ERROR] Invalid response: $response\n";
        break;
    }

    $processed = $data['processed_this_batch'];
    $items = $data['items_created_this_batch'];
    $hw = $data['hardware_created_this_batch'];
    $subs = $data['subs_created_this_batch'];
    $cumItems = $data['cumulative_counts']['invoice_items'];
    $cumHw = $data['cumulative_counts']['hardware_assets'];
    $cumSerials = $data['cumulative_counts']['hardware_with_serials'];
    $cumSubs = $data['cumulative_counts']['software_subscriptions'];
    $totalInvoices = $data['total_invoices_in_db'];
    
    $totalProcessed += $processed;
    $offset = $data['next_offset'];
    $hasMore = $data['has_more'];

    echo sprintf(
        "[%s] Invoices %4d-%-4d of %d (Processed: %d) | Items: %4d | HW: %4d | Subs: %3d | (Cumulative HW Serials: %d) (%.1fs)\n",
        date('H:i:s'),
        $offset - $processed + 1,
        $offset,
        $totalInvoices,
        $processed,
        $cumItems,
        $cumHw,
        $cumSubs,
        $cumSerials,
        $elapsed
    );

    if ($hasMore) {
        usleep(1500000); // 1.5s delay to keep Apache happy
    }
}

echo "\n=== Normalization Complete! ===\n";
echo "Total Invoices Processed: $totalProcessed\n";
echo "Cumulative Invoice Items: $cumItems\n";
echo "Cumulative Hardware Assets: $cumHw (with valid serial numbers: $cumSerials)\n";
echo "Cumulative Software Subscriptions: $cumSubs\n";
