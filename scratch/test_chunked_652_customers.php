<?php
$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

// Generate 652 realistic mock customers
$allCustomers = [];
for ($i = 1; $i <= 652; $i++) {
    $allCustomers[] = [
        'list_id' => "CUST-$i",
        'name' => "Customer Sample $i Ltd",
        'full_name' => "Customer Sample $i Ltd",
        'company_name' => "Customer Sample $i Corporation",
        'contact_name' => "Director $i",
        'email' => "contact$i@samplecorp.lk",
        'phone' => "+94 11 20000$i",
        'bill_address' => "No. $i, Galle Road\nColombo 03",
        'bill_city' => "Colombo",
        'sales_rep' => "AS",
        'balance' => 12500.0,
        'total_balance' => 12500.0,
        'credit_limit' => 500000.0,
        'terms' => "Net 30",
        'resale_number' => "114000$i-7000",
        'vat_number' => "114000$i-7000",
        'tin_number' => "1000000$i",
        'is_vat_registered' => true,
        'tax_item_ref' => "VAT 18%",
        'notes' => "Customer profile batch test $i."
    ];
}

$batchSize = 25;
$totalBatches = ceil(count($allCustomers) / $batchSize);

echo "Starting upload of 652 customers in $totalBatches batches of $batchSize...\n";

$importedTotal = 0;
for ($b = 0; $b < $totalBatches; $b++) {
    $batch = array_slice($allCustomers, $b * $batchSize, $batchSize);
    $payload = [
        'source' => 'test_cli',
        'timestamp' => date('c'),
        'customers' => $batch,
        'invoices' => [],
        'credit_memos' => [],
        'payments' => []
    ];
    $json = json_encode($payload);
    $sizeKB = round(strlen($json) / 1024, 1);

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

    if ($code !== 200) {
        echo "[FAILED] Batch " . ($b + 1) . "/$totalBatches (Size: {$sizeKB} KB): HTTP $code - " . substr(strip_tags($resp), 0, 100) . "\n";
        exit(1);
    }

    $importedTotal += count($batch);
    echo "  [OK] Batch " . ($b + 1) . "/$totalBatches ({$sizeKB} KB) -> Imported $importedTotal / 652\n";
}

echo "\nSUCCESS! All 652 customers successfully uploaded in chunked batches!\n";
