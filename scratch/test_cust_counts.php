<?php
$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

function testFullCustCount($count) {
    global $url, $apiKey;
    $customers = [];
    for ($i = 1; $i <= $count; $i++) {
        $customers[] = [
            'list_id' => "CUST-$i",
            'name' => "Customer Test $i Ltd",
            'full_name' => "Customer Test $i Ltd",
            'company_name' => "Customer Test $i Corporation",
            'contact_name' => "John Doe $i",
            'email' => "finance$i@example.com",
            'phone' => "+94 11 234567$i",
            'bill_address' => "123 Business Avenue, Level $i\nCommercial Towers\nColombo 03",
            'bill_city' => "Colombo",
            'sales_rep' => "AS",
            'balance' => 54000.0,
            'total_balance' => 54000.0,
            'credit_limit' => 1000000.0,
            'terms' => "Net 30",
            'resale_number' => "123456789-7000",
            'vat_number' => "123456789-7000",
            'tin_number' => "987654321",
            'is_vat_registered' => true,
            'tax_item_ref' => "VAT 18%",
            'notes' => "Regular corporate client with VAT registration."
        ];
    }

    $json = json_encode(['customers' => $customers]);
    $size = strlen($json);

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

    echo "Count: $count | Size: " . round($size / 1024, 1) . " KB | HTTP: $code | Resp: " . substr(strip_tags($resp), 0, 80) . "\n";
}

foreach ([10, 20, 25, 30, 35, 40, 50] as $cnt) {
    testFullCustCount($cnt);
}
