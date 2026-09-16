<?php
$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

function testBatch($count) {
    global $url, $apiKey;
    $customers = [];
    for ($i = 1; $i <= $count; $i++) {
        $customers[] = [
            'list_id' => "CUST-$i",
            'name' => "Customer Test $i Ltd",
            'full_name' => "Customer Test $i Ltd",
            'company_name' => "Customer Test $i Corporation",
            'contact_name' => "John Doe $i",
            'first_name' => "John",
            'last_name' => "Doe $i",
            'job_title' => "Procurement Manager",
            'email' => "finance$i@example.com",
            'phone' => "+94 11 234567$i",
            'alt_phone' => "+94 77 123456$i",
            'fax' => "+94 11 987654$i",
            'bill_address' => "123 Business Avenue, Level $i",
            'bill_address_2' => "Commercial Towers",
            'bill_address_3' => "Colombo 03",
            'bill_city' => "Colombo",
            'bill_state' => "Western",
            'bill_postal_code' => "00300",
            'bill_country' => "Sri Lanka",
            'ship_address' => "Warehouse 4B, Industrial Park",
            'ship_city' => "Kelaniya",
            'ship_state' => "Western",
            'ship_postal_code' => "11600",
            'ship_country' => "Sri Lanka",
            'customer_type' => "Corporate",
            'terms' => "Net 30",
            'sales_rep' => "AS",
            'tax_code' => "VAT",
            'item_sales_tax' => "VAT 18%",
            'resale_number' => "123456789-7000",
            'account_number' => "ACC-$i",
            'credit_limit' => 1000000.0,
            'balance' => 54000.0,
            'total_balance' => 54000.0,
            'is_active' => true,
            'custom_fields' => ['VAT No' => "123456789-7000", 'TIN' => "987654321"],
            'notes' => "Regular corporate client with VAT registration."
        ];
    }

    $payload = [
        'source' => 'test_cli',
        'timestamp' => date('c'),
        'customers' => $customers,
        'invoices' => [],
        'credit_memos' => [],
        'payments' => []
    ];

    $json = json_encode($payload);
    $payloadSize = strlen($json);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Count: $count | Size: " . round($payloadSize / 1024, 1) . " KB | HTTP: $httpCode | Body: " . substr(strip_tags($response), 0, 80) . "\n";
}

testBatch(50);
testBatch(100);
testBatch(200);
testBatch(500);
testBatch(652);
